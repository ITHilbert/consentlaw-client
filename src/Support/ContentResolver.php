<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Support;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Log;
use ITHilbert\ConsentLaw\Contracts\FallbackStore;
use ITHilbert\ConsentLaw\Contracts\Transport;
use ITHilbert\ConsentLaw\Data\Abruf;
use ITHilbert\ConsentLaw\Exceptions\ConsentLawException;
use ITHilbert\ConsentLaw\Exceptions\ConsentLawResponseException;

/**
 * Regelt den Ablauf jedes Abrufs:
 *
 *   1. Cache frisch (TTL)?  → sofort ausliefern, kein HTTP-Request
 *   2. Sperrvermerk aktiv?  → kein HTTP-Request, direkt weiter zu 4.
 *   3. Sonst API abrufen    → Erfolg: Cache + Fallback-Datei aktualisieren
 *                           → 404: „nicht veröffentlicht“, nichts ausliefern
 *                           → alles andere (Timeout, 5xx, 429, 401 …) gilt als Störung:
 *                             Sperrvermerk setzen, weiter zu 4.
 *   4. Störung              → letzten Stand aus dem Fallback ausliefern, sonst nichts
 *
 * Punkt 4 ist der Kern: Rechtstexte dürfen nicht von der Kundenseite
 * verschwinden, nur weil ein API-Aufruf fehlschlägt. Punkt 2 sorgt dafür, dass
 * die Seite dabei nicht langsam wird: Ohne Sperrvermerk würde nach jeder
 * abgelaufenen TTL jeder Seitenaufruf erneut auf den API-Timeout warten.
 *
 * Der Sperrvermerk liegt im Cache-Store (je Inhalt), aber unabhängig von
 * `cache.enabled` – er hängt nicht am Inhalts-Cache.
 */
final readonly class ContentResolver
{
    public function __construct(
        private Transport $transport,
        private CacheRepository $cache,
        private FallbackStore $fallback,
        private bool $cacheEnabled,
        private int $ttl,
        private string $cachePrefix,
        private bool $fallbackEnabled,
        private int $warnAfter,
        /** Sekunden, die nach einer Störung nicht erneut die API gefragt wird (0 = keine Sperre). */
        private int $retryAfter = 300,
    ) {}

    /**
     * @param  array<string, scalar|null>  $query
     */
    public function resolve(string $key, string $endpoint, array $query = []): Abruf
    {
        // 1. Frischer Cache-Treffer – ohne HTTP-Request.
        if ($this->cacheEnabled && $this->ttl > 0) {
            $cached = $this->cache->get($this->cacheKey($key));

            if (is_array($cached) && is_array($cached['payload'] ?? null)) {
                return Abruf::aktuell($cached['payload'], $this->parseDate($cached['fetched_at'] ?? null));
            }
        }

        // 2. Sperrvermerk nach einer Störung – die API wird nicht erneut gefragt.
        if ($this->retryAfter > 0 && $this->cache->has($this->sperreKey($key))) {
            return $this->fromFallback($key, null);
        }

        // 3. API abrufen.
        try {
            $payload = $this->transport->get($endpoint, $query);
            $now = CarbonImmutable::now();

            if ($this->cacheEnabled && $this->ttl > 0) {
                $this->cache->put(
                    $this->cacheKey($key),
                    ['payload' => $payload, 'fetched_at' => $now->toIso8601String()],
                    $this->ttl,
                );
            }

            if ($this->fallbackEnabled) {
                $this->fallback->put($key, $payload);
            }

            return Abruf::aktuell($payload, $now);
        } catch (ConsentLawException $e) {
            if ($e instanceof ConsentLawResponseException && $e->status === 404) {
                // Der Server HAT geantwortet: Es gibt hier nichts (Token ungültig oder
                // Inhalt nicht veröffentlicht). Das ist keine Störung, sondern eine
                // gültige Aussage – ein alter Stand wäre falsch (er könnte zu einem
                // anderen Token gehören).
                Log::warning('[consentlaw] Inhalt nicht veröffentlicht (Server antwortete mit 404).', [
                    'key' => $key,
                    'error' => $e->getMessage(),
                ]);

                return Abruf::nichtVeroeffentlicht();
            }

            // Alles andere ist eine Störung (Timeout, DNS, 5xx, 429, 401 …):
            // der letzte Stand rettet die Seite.
            return $this->beiStoerung($key, $e);
        }
    }

    /** Verwirft Cache-Eintrag und Sperrvermerk – der nächste Zugriff fragt die API. */
    public function forget(string $key): void
    {
        $this->cache->forget($this->cacheKey($key));
        $this->cache->forget($this->sperreKey($key));
    }

    private function beiStoerung(string $key, ConsentLawException $error): Abruf
    {
        if ($this->retryAfter > 0) {
            $this->cache->put($this->sperreKey($key), true, $this->retryAfter);
        }

        return $this->fromFallback($key, $error);
    }

    /**
     * Liefert den letzten Stand aus dem Fallback.
     *
     * @param  ConsentLawException|null  $error  null = Sperrvermerk aktiv: bewusst ohne
     *                                           Log, sonst schriebe jeder Seitenaufruf einen Eintrag
     */
    private function fromFallback(string $key, ?ConsentLawException $error): Abruf
    {
        if (! $this->fallbackEnabled) {
            if ($error !== null) {
                Log::warning('[consentlaw] Abruf fehlgeschlagen, Fallback deaktiviert.', [
                    'key' => $key,
                    'error' => $error->getMessage(),
                ]);
            }

            return Abruf::stoerung();
        }

        $entry = $this->fallback->get($key);

        if ($entry === null) {
            if ($error !== null) {
                Log::error('[consentlaw] Abruf fehlgeschlagen und kein Fallback vorhanden.', [
                    'key' => $key,
                    'error' => $error->getMessage(),
                ]);
            }

            return Abruf::stoerung();
        }

        if ($error !== null) {
            $age = $entry->ageInSeconds();

            // Inhalt wird IMMER ausgeliefert – die Warnung dient nur der Diagnose.
            if ($this->warnAfter > 0 && $age > $this->warnAfter) {
                Log::warning('[consentlaw] Liefere veralteten Stand aus (API nicht erreichbar).', [
                    'key' => $key,
                    'age_hours' => (int) round($age / 3600),
                    'error' => $error->getMessage(),
                ]);
            } else {
                Log::info('[consentlaw] API nicht erreichbar, Fallback genutzt.', [
                    'key' => $key,
                    'age_seconds' => $age,
                ]);
            }
        }

        return Abruf::ausFallback($entry->payload, $entry->fetchedAt);
    }

    private function cacheKey(string $key): string
    {
        return $this->cachePrefix.':'.$key;
    }

    private function sperreKey(string $key): string
    {
        return $this->cachePrefix.':sperre:'.$key;
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
