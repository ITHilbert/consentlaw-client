<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Data;

use Carbon\CarbonImmutable;

/**
 * Ein gespeicherter Stand aus dem Fallback-Speicher.
 */
final readonly class FallbackEntry
{
    public function __construct(
        /** @var array<string, mixed> */
        public array $payload,
        public CarbonImmutable $fetchedAt,
    ) {}

    public function ageInSeconds(): int
    {
        // diffInSeconds() liefert float – explizit casten, sonst TypeError.
        return max(0, (int) $this->fetchedAt->diffInSeconds(CarbonImmutable::now()));
    }
}
