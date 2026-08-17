<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Support;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Log;
use ITHilbert\ConsentLaw\Contracts\FallbackStore;
use ITHilbert\ConsentLaw\Contracts\Transport;
use ITHilbert\ConsentLaw\Exceptions\ConsentLawException;
use ITHilbert\ConsentLaw\Exceptions\ConsentLawResponseException;

/**
 * Regelt den Ablauf jedes Abrufs:
 *
 *   1. Cache frisch (TTL)?  → sofort ausliefern, kein HTTP-Request
 *   2. Sonst API abrufen    → Erfolg: Cache + Fallback-Datei aktualisieren
 *   3. API-Fehler           → letzten Stand aus dem Fallback ausliefern
 *   4. Kein Fallback        → null (die Komponente rendert dann nichts)
 *
 * Punkt 3 ist der Kern: Rechtstexte dürfen nicht von der Kundenseite
 * verschwinden, nur weil ein API-Aufruf fehlschlägt.
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
    ) {}

    /**
     * @param  array<string, scalar|null>  $query
     * @return array{payload: array<string, mixed>, stale: bool, fetched_at: CarbonImmutable|null}|null
     */
    public function resolve(string $key, string $endpoint, array $query = []): ?array
    {
        $cacheKey = $this->cachePrefix.':'.$key;

        // 1. Frischer Cache-Treffer – ohne HTTP-Request.
        if ($this->cacheEnabled && $this->ttl > 0) {
            $cached = $this->cache->get($cacheKey);

            if (is_array($cached) && is_array($cached['payload'] ?? null)) {
                return [
                    'payload' => $cached['payload'],
                    'stale' => false,
                    'fetched_at' => $this->parseDate($cached['fetched_at'] ?? null),
                ];
            }
        }

        // 2. API abrufen.
        try {
            $payload = $this->transport->get($endpoint, $query);
            $now = CarbonImmutable::now();

            if ($this->cacheEnabled && $this->ttl > 0) {
                $this->cache->put(
                    $cacheKey,
                    ['payload' => $payload, 'fetched_at' => $now->toIso8601String()],
                    $this->ttl,
                );
            }

            if ($this->fallbackEnabled) {
                $this->fallback->put($key, $payload);
            }

            return ['payload' => $payload, 'stale' => false, 'fetched_at' => $now];
        } catch (ConsentLawResponseException $e) {
            // Der Server HAT geantwortet – nur ablehnend (404 = Token ungültig
            // oder Inhalt nicht veröffentlicht). Das ist keine Störung, sondern
            // eine gültige Aussage: Es gibt hier nichts. Einen alten Stand
            // auszuliefern wäre falsch (er könnte zu einem anderen Token gehören).
            Log::warning('[consentlaw] Inhalt nicht verfügbar (Server antwortete ablehnend).', [
                'key' => $key,
                'status' => $e->status,
                'error' => $e->getMessage(),
            ]);

            return null;
        } catch (ConsentLawException $e) {
            // Netzwerk-/Serverstörung (Timeout, DNS, 5xx) → letzter Stand rettet die Seite.
            return $this->fromFallback($key, $e);
        }
    }

    /**
     * @return array{payload: array<string, mixed>, stale: bool, fetched_at: CarbonImmutable|null}|null
     */
    private function fromFallback(string $key, ConsentLawException $error): ?array
    {
        if (! $this->fallbackEnabled) {
            Log::warning('[consentlaw] Abruf fehlgeschlagen, Fallback deaktiviert.', [
                'key' => $key,
                'error' => $error->getMessage(),
            ]);

            return null;
        }

        $entry = $this->fallback->get($key);

        if ($entry === null) {
            Log::error('[consentlaw] Abruf fehlgeschlagen und kein Fallback vorhanden.', [
                'key' => $key,
                'error' => $error->getMessage(),
            ]);

            return null;
        }

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

        return ['payload' => $entry->payload, 'stale' => true, 'fetched_at' => $entry->fetchedAt];
    }

    public function forget(string $key): void
    {
        $this->cache->forget($this->cachePrefix.':'.$key);
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
