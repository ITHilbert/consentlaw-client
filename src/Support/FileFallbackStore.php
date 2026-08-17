<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Support;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use ITHilbert\ConsentLaw\Contracts\FallbackStore;
use ITHilbert\ConsentLaw\Data\FallbackEntry;
use Throwable;

/**
 * Ablage des letzten erfolgreichen Abrufs als JSON-Datei
 * (Standard: storage/app/consentlaw/<key>.json).
 *
 * Bewusst dateibasiert: funktioniert in jedem Kundenprojekt ohne Migration,
 * überlebt `cache:clear` und braucht keine Datenbank.
 */
final readonly class FileFallbackStore implements FallbackStore
{
    public function __construct(
        private Filesystem $disk,
        private string $path,
    ) {}

    public function put(string $key, array $payload): void
    {
        $json = json_encode([
            'fetched_at' => CarbonImmutable::now()->toIso8601String(),
            'payload' => $payload,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return;
        }

        // Schreibfehler dürfen die Seite nicht abbrechen – der Fallback ist
        // eine Absicherung, kein kritischer Pfad.
        try {
            $this->disk->put($this->file($key), $json);
        } catch (Throwable) {
            // absichtlich ignoriert; Ausgabe funktioniert weiterhin über die API
        }
    }

    public function get(string $key): ?FallbackEntry
    {
        try {
            if (! $this->disk->exists($this->file($key))) {
                return null;
            }

            $raw = $this->disk->get($this->file($key));
        } catch (Throwable) {
            return null;
        }

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded) || ! is_array($decoded['payload'] ?? null)) {
            return null;
        }

        try {
            $fetchedAt = CarbonImmutable::parse((string) ($decoded['fetched_at'] ?? 'now'));
        } catch (Throwable) {
            $fetchedAt = CarbonImmutable::now();
        }

        return new FallbackEntry($decoded['payload'], $fetchedAt);
    }

    public function forget(string $key): void
    {
        try {
            $this->disk->delete($this->file($key));
        } catch (Throwable) {
            // ignoriert
        }
    }

    public function flush(): void
    {
        try {
            $this->disk->deleteDirectory($this->path);
        } catch (Throwable) {
            // ignoriert
        }
    }

    private function file(string $key): string
    {
        // Key kann Locale enthalten (z.B. "impressum.de") – auf sichere Zeichen begrenzen.
        $safe = preg_replace('/[^a-z0-9._-]/i', '_', $key) ?? 'unknown';

        return trim($this->path, '/')."/{$safe}.json";
    }
}
