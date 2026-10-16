<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Data;

use Carbon\CarbonImmutable;

/**
 * Rohergebnis des ContentResolvers: API-Payload samt Status.
 *
 * Nur bei Aktuell und Fallback ist ein Payload vorhanden.
 */
final readonly class Abruf
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function __construct(
        public Abrufstatus $status,
        public ?array $payload = null,
        public ?CarbonImmutable $fetchedAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function aktuell(array $payload, ?CarbonImmutable $fetchedAt): self
    {
        return new self(Abrufstatus::Aktuell, $payload, $fetchedAt);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function ausFallback(array $payload, CarbonImmutable $fetchedAt): self
    {
        return new self(Abrufstatus::Fallback, $payload, $fetchedAt);
    }

    public static function nichtVeroeffentlicht(): self
    {
        return new self(Abrufstatus::NichtVeroeffentlicht);
    }

    public static function stoerung(): self
    {
        return new self(Abrufstatus::Stoerung);
    }

    public function isStale(): bool
    {
        return $this->status === Abrufstatus::Fallback;
    }
}
