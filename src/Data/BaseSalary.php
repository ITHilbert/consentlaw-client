<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Data;

/**
 * Gehalt einer Stelle (Schema.org baseSalary): Einzelbetrag oder Spanne.
 */
final readonly class BaseSalary
{
    /** Erlaubte Einheiten (Schema.org unitText). */
    public const UNITS = ['HOUR', 'DAY', 'WEEK', 'MONTH', 'YEAR'];

    public function __construct(
        /** ISO-4217-Code, z. B. EUR. */
        public string $currency,
        public string $unit,
        public int|float|null $value = null,
        public int|float|null $minValue = null,
        public int|float|null $maxValue = null,
    ) {}

    /**
     * Liefert null, wenn Währung, Einheit oder Betrag fehlen oder ungültig sind.
     */
    public static function fromApi(mixed $payload): ?self
    {
        if (! is_array($payload)) {
            return null;
        }

        $currency = $payload['currency'] ?? null;
        $unit = $payload['unit'] ?? null;
        if (! is_string($currency) || preg_match('/^[A-Z]{3}$/', $currency) !== 1
            || ! is_string($unit) || ! in_array($unit, self::UNITS, true)) {
            return null;
        }

        // Eine angegebene, aber ungültige Zahl macht das ganze Gehalt ungültig.
        foreach (['value', 'min_value', 'max_value'] as $feld) {
            if (($payload[$feld] ?? null) !== null && self::amount($payload[$feld]) === null) {
                return null;
            }
        }

        $value = self::amount($payload['value'] ?? null);
        $min = self::amount($payload['min_value'] ?? null);
        $max = self::amount($payload['max_value'] ?? null);
        if ($value === null && $min === null && $max === null) {
            return null;
        }

        return new self($currency, $unit, $value, $min, $max);
    }

    private static function amount(mixed $value): int|float|null
    {
        return (is_int($value) || is_float($value)) && is_finite((float) $value) && $value >= 0 ? $value : null;
    }
}
