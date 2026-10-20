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
        public ?HiringOrganization $hiringOrganization = null,
        /** Homeoffice-Stelle (Schema.org jobLocationType TELECOMMUTE). */
        public bool $remote = false,
        /** @var list<string> ISO-3166-1-alpha-2-Codes, aus denen Bewerbungen möglich sind. */
        public array $applicantCountries = [],
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
            descriptionFormat: Inhaltsformat::ausApi($payload['description_format'] ?? null)->value,
            location: self::nullableString($payload['location'] ?? null),
            employmentType: self::nullableString($payload['employment_type'] ?? null),
            employmentTypeLabel: self::nullableString($payload['employment_type_label'] ?? null),
            employmentTypeSchema: self::nullableString($payload['employment_type_schema'] ?? null),
            datePosted: self::parseDate($payload['date_posted'] ?? null),
            validThrough: self::parseDate($payload['valid_through'] ?? null),
            isGlobal: (bool) ($payload['is_global'] ?? false),
            hiringOrganization: HiringOrganization::fromApi($payload['hiring_organization'] ?? null),
            remote: ($payload['remote'] ?? false) === true,
            applicantCountries: self::countries($payload['applicant_countries'] ?? null),
        );
    }

    public function isMarkdown(): bool
    {
        return Inhaltsformat::ausApi($this->descriptionFormat) === Inhaltsformat::Markdown;
    }

    /** Bewerbungsfrist abgelaufen? */
    public function isExpired(): bool
    {
        return $this->validThrough !== null && $this->validThrough->isPast();
    }

    /**
     * @return list<string>
     */
    private static function countries(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $codes = [];
        foreach ($value as $code) {
            if (is_string($code) && preg_match('/^[A-Za-z]{2}$/', $code) === 1) {
                $codes[] = strtoupper($code);
            }
        }

        return array_values(array_unique($codes));
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
