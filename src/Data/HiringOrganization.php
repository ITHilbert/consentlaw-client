<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Data;

/**
 * Arbeitgeber einer Stelle (Schema.org hiringOrganization).
 */
final readonly class HiringOrganization
{
    public function __construct(
        public string $name,
        public ?string $url = null,
        public ?string $logo = null,
    ) {}

    /**
     * Liefert null, wenn die Angabe kein Objekt mit nicht leerem Namen ist.
     */
    public static function fromApi(mixed $payload): ?self
    {
        if (! is_array($payload)) {
            return null;
        }

        $name = $payload['name'] ?? null;
        if (! is_string($name) || trim($name) === '') {
            return null;
        }

        return new self(
            name: $name,
            url: self::text($payload['url'] ?? null),
            logo: self::text($payload['logo'] ?? null),
        );
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
