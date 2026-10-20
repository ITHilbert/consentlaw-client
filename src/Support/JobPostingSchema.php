<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Support;

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
            'description' => $job->isMarkdown()
                ? MarkdownRenderer::render($job->description)
                : $job->description,
            'employmentType' => $job->employmentTypeSchema,
            'datePosted' => $job->datePosted?->toIso8601String(),
            'validThrough' => $job->validThrough?->toIso8601String(),
            'jobLocation' => $job->location ? [
                '@type' => 'Place',
                'address' => ['@type' => 'PostalAddress', 'addressLocality' => $job->location],
            ] : null,
        ]);

        if ($job->hiringOrganization !== null) {
            $schema['hiringOrganization'] = array_filter([
                '@type' => 'Organization',
                'name' => $job->hiringOrganization->name,
                'sameAs' => self::absoluteUrl($job->hiringOrganization->url),
                'logo' => self::absoluteUrl($job->hiringOrganization->logo),
            ]);
        }

        if ($job->remote) {
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

    private static function absoluteUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        return preg_match('#^https?://[^\s/]+#i', $url) === 1 ? $url : null;
    }
}
