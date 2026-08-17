<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Data;

use Carbon\CarbonImmutable;

/**
 * Ein Stellenangebot aus consentLaw.
 */
final readonly class Job
{
    public function __construct(
        public string $id,
        public string $title,
        public string $description,
        public string $descriptionFormat,
        public ?string $location,
        public ?string $employmentType,
        public ?string $employmentTypeLabel,
        /** Schema.org-Wert für JobPosting.employmentType (z.B. CONTRACTOR). */
        public ?string $employmentTypeSchema,
        public ?CarbonImmutable $datePosted,
        public ?CarbonImmutable $validThrough,
        public bool $isGlobal = false,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromApi(array $payload): self
    {
        return new self(
            id: (string) ($payload['id'] ?? ''),
            title: (string) ($payload['title'] ?? ''),
            description: (string) ($payload['description'] ?? ''),
            descriptionFormat: (string) ($payload['description_format'] ?? 'markdown'),
            location: self::nullableString($payload['location'] ?? null),
            employmentType: self::nullableString($payload['employment_type'] ?? null),
            employmentTypeLabel: self::nullableString($payload['employment_type_label'] ?? null),
            employmentTypeSchema: self::nullableString($payload['employment_type_schema'] ?? null),
            datePosted: self::parseDate($payload['date_posted'] ?? null),
            validThrough: self::parseDate($payload['valid_through'] ?? null),
            isGlobal: (bool) ($payload['is_global'] ?? false),
        );
    }

    public function isMarkdown(): bool
    {
        return $this->descriptionFormat === 'markdown' || $this->descriptionFormat === 'md';
    }

    /** Bewerbungsfrist abgelaufen? */
    public function isExpired(): bool
    {
        return $this->validThrough !== null && $this->validThrough->isPast();
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function parseDate(mixed $value): ?CarbonImmutable
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
