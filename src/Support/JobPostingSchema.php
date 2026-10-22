<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Support;

use ITHilbert\ConsentLaw\Data\BaseSalary;
use ITHilbert\ConsentLaw\Data\Job;

/**
 * Baut das Schema.org-JobPosting (Google for Jobs) aus einem Job.
 *
 * Bewusst in PHP statt im Blade-Template: Der Schlüssel `@context` würde dort
 * von Laravels `@context`-Direktive kompiliert und das JSON-LD zerstören.
 */
final class JobPostingSchema
{
    /**
     * @return array<string, mixed>
     */
    public static function array(Job $job): array
    {
        // Bisherige Felder: wie zuvor entfallen leere Werte (null, '').
        $schema = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'JobPosting',
            'title' => $job->title,
            'description' => $job->descriptionHtml(),
            'employmentType' => $job->employmentTypeSchema,
            'datePosted' => $job->datePosted?->toIso8601String(),
            'validThrough' => $job->validThrough?->toIso8601String(),
        ]);

        // Arbeitsort: 'work_location' gewinnt, sonst gilt das alte remote-Flag.
        $remote = match ($job->workLocation) {
            'remote' => true,
            'vor_ort' => false,
            default => $job->remote,
        };

        if ($job->workLocation !== 'remote') {
            $ort = self::jobLocation($job);
            if ($ort !== null) {
                $schema['jobLocation'] = $ort;
            }
        }

        if ($job->baseSalary !== null) {
            $schema['baseSalary'] = self::baseSalary($job->baseSalary);
        }

        if ($job->hiringOrganization !== null) {
            $schema['hiringOrganization'] = array_filter([
                '@type' => 'Organization',
                'name' => $job->hiringOrganization->name,
                'sameAs' => self::absoluteUrl($job->hiringOrganization->url),
                'logo' => self::absoluteUrl($job->hiringOrganization->logo),
            ]);
        }

        if ($remote) {
            $schema['jobLocationType'] = 'TELECOMMUTE';
            if ($job->applicantCountries !== []) {
                $schema['applicantLocationRequirements'] = array_map(
                    static fn (string $land): array => ['@type' => 'Country', 'name' => $land],
                    $job->applicantCountries,
                );
            }
        }

        return $schema;
    }

    /** JSON für ein `<script type="application/ld+json">`; `<`/`>` sind escaped. */
    public static function json(Job $job): string
    {
        return (string) json_encode(
            self::array($job),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG,
        );
    }

    /** @return array<string, mixed>|null */
    private static function jobLocation(Job $job): ?array
    {
        if ($job->address !== null) {
            return [
                '@type' => 'Place',
                'address' => array_filter([
                    '@type' => 'PostalAddress',
                    'streetAddress' => $job->address->streetAddress,
                    'postalCode' => $job->address->postalCode,
                    'addressLocality' => $job->address->addressLocality,
                    'addressCountry' => $job->address->addressCountry,
                ]),
            ];
        }

        return $job->location ? [
            '@type' => 'Place',
            'address' => ['@type' => 'PostalAddress', 'addressLocality' => $job->location],
        ] : null;
    }

    /** @return array<string, mixed> */
    private static function baseSalary(BaseSalary $gehalt): array
    {
        $betrag = $gehalt->value !== null
            ? ['value' => $gehalt->value]
            : ['minValue' => $gehalt->minValue, 'maxValue' => $gehalt->maxValue];

        return [
            '@type' => 'MonetaryAmount',
            'currency' => $gehalt->currency,
            'value' => array_filter(
                ['@type' => 'QuantitativeValue', ...$betrag, 'unitText' => $gehalt->unit],
                static fn (mixed $v): bool => $v !== null,
            ),
        ];
    }

    private static function absoluteUrl(?string $url): ?string
    {
        if ($url === null || preg_match('/[\s\x00-\x1F\x7F]/', $url) === 1) {
            return null;
        }

        $teile = parse_url($url);
        if ($teile === false || ! in_array(strtolower((string) ($teile['scheme'] ?? '')), ['http', 'https'], true)
            || ($teile['host'] ?? '') === ''
            || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return $url;
    }
}
