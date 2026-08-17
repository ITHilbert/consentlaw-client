<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Endpoints;

use Illuminate\Support\Collection;
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
        $result = $this->resolver->resolve(
            key: $this->cacheKey(),
            endpoint: "api/jobs/{$this->token}",
            query: ['locale' => $this->locale],
        );

        if ($result === null) {
            return collect();
        }

        $jobs = $result['payload']['jobs'] ?? [];

        if (! is_array($jobs)) {
            return collect();
        }

        return collect($jobs)
            ->filter(static fn (mixed $row): bool => is_array($row))
            ->map(static fn (array $row): Job => Job::fromApi($row))
            ->values();
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

    /** Verwirft nur den Cache-Eintrag (Fallback-Datei bleibt erhalten). */
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
