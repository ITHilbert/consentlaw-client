<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Data;

/**
 * Arbeitsort einer Stelle (Schema.org PostalAddress).
 */
final readonly class JobAddress
{
    public function __construct(
        public ?string $streetAddress = null,
        public ?string $postalCode = null,
        public ?string $addressLocality = null,
        /** ISO-3166-1-alpha-2-Code, großgeschrieben. */
        public ?string $addressCountry = null,
    ) {}

    /**
     * Liefert null, wenn die Angabe kein Objekt ist oder keinen verwertbaren Teil enthält.
     */
    public static function fromApi(mixed $payload): ?self
    {
        if (! is_array($payload) || array_is_list($payload)) {
            return null;
        }

        $land = self::text($payload['address_country'] ?? null);
        $land = $land !== null && preg_match('/^[A-Za-z]{2}$/', $land) === 1 ? strtoupper($land) : null;

        $adresse = new self(
            streetAddress: self::text($payload['street_address'] ?? null),
            postalCode: self::text($payload['postal_code'] ?? null),
            addressLocality: self::text($payload['address_locality'] ?? null),
            addressCountry: $land,
        );

        return $adresse->isEmpty() ? null : $adresse;
    }

    public function isEmpty(): bool
    {
        return $this->streetAddress === null && $this->postalCode === null
            && $this->addressLocality === null && $this->addressCountry === null;
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
