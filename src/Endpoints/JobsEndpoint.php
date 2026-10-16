<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Endpoints;

use Illuminate\Support\Collection;
use ITHilbert\ConsentLaw\Data\Abrufergebnis;
use ITHilbert\ConsentLaw\Data\Job;
use ITHilbert\ConsentLaw\Support\ContentResolver;

/**
 * Zugriff auf die Stellenangebote einer Domain.
 *
 * Die API liefert ausschliesslich aktive Jobs – eine Filterung nach „aktiv"
 * ist clientseitig daher nicht nötig.
 */
final readonly class JobsEndpoint
{
    public function __construct(
        private ContentResolver $resolver,
        private string $token,
        private ?string $locale,
    ) {}

    /**
     * Alle Stellenangebote, neueste zuerst.
     *
     * @return Collection<int, Job>
     */
    public function all(): Collection
    {
        return $this->abrufen()->inhalt;
    }

    /**
     * Wie all(), nennt aber zusätzlich den Grund: aktuell, aus dem Fallback,
     * nicht veröffentlicht (404) oder Störung ohne Fallback. Der Inhalt ist in
     * jedem Fall eine Collection (bei fehlendem Inhalt leer).
     *
     * @return Abrufergebnis<Collection<int, Job>>
     */
    public function abrufen(): Abrufergebnis
    {
        $abruf = $this->resolver->resolve(
            key: $this->cacheKey(),
            endpoint: "api/jobs/{$this->token}",
            query: ['locale' => $this->locale],
        );

        $jobs = $abruf->payload['jobs'] ?? [];

        if (! is_array($jobs)) {
            $jobs = [];
        }

        return new Abrufergebnis($abruf->status, collect($jobs)
            ->filter(static fn (mixed $row): bool => is_array($row))
            ->map(static fn (array $row): Job => Job::fromApi($row))
            ->values());
    }

    /**
     * Nur Stellen, deren Bewerbungsfrist noch nicht abgelaufen ist.
     *
     * @return Collection<int, Job>
     */
    public function current(): Collection
    {
        return $this->all()->reject(static fn (Job $job): bool => $job->isExpired())->values();
    }

    public function find(string $id): ?Job
    {
        return $this->all()->firstWhere('id', $id);
    }

    public function count(): int
    {
        return $this->all()->count();
    }

    public function isEmpty(): bool
    {
        return $this->all()->isEmpty();
    }

    /**
     * @return Collection<int, Job>
     */
    public function refresh(): Collection
    {
        $this->forget();

        return $this->all();
    }

    /** Verwirft Cache-Eintrag und Sperrvermerk (Fallback-Datei bleibt erhalten). */
    public function forget(): void
    {
        $this->resolver->forget($this->cacheKey());
    }

    /**
     * Der Token geht (gekürzt gehasht) in den Key ein: Nach einem Token-Wechsel
     * darf kein Inhalt der vorherigen Domain wiederverwendet werden.
     */
    private function cacheKey(): string
    {
        $parts = array_filter(['jobs', $this->locale, substr(sha1($this->token), 0, 8)]);

        return implode('.', $parts);
    }
}
