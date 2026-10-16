<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Data;

use Carbon\CarbonImmutable;

/**
 * Ein Rechtsdokument (Impressum, Datenschutz, AGB, Cookie-Richtlinie, AVV).
 */
final readonly class Document
{
    public function __construct(
        public string $type,
        public string $content,
        public string $format,
        public ?CarbonImmutable $generatedAt,
        public ?string $locale,
        /** True, wenn der Inhalt aus dem Fallback stammt (API war nicht erreichbar). */
        public bool $isStale = false,
        /** Zeitpunkt des Abrufs, aus dem dieser Inhalt stammt. */
        public ?CarbonImmutable $fetchedAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromApi(string $type, array $payload, bool $isStale = false, ?CarbonImmutable $fetchedAt = null): self
    {
        $content = (string) ($payload['content'] ?? '');

        return new self(
            type: $type,
            content: $content,
            format: self::resolveFormat($payload),
            generatedAt: self::parseDate($payload['generated_at'] ?? null),
            locale: isset($payload['locale']) ? (string) $payload['locale'] : null,
            isStale: $isStale,
            fetchedAt: $fetchedAt,
        );
    }

    /**
     * Bestimmt das Format des Inhalts.
     *
     * Nicht alle consentLaw-Endpunkte liefern ein `format`-Feld (z.B. Impressum,
     * Datenschutz, AGB). Ohne Angabe gilt `markdown` – es gibt keine Erkennung am
     * Inhalt: Markdown läuft durch den Filter des MarkdownRenderers, ein
     * unterstelltes „html“ dagegen ungefiltert auf die Seite.
     *
     * @param  array<string, mixed>  $payload
     */
    private static function resolveFormat(array $payload): string
    {
        $declared = $payload['format'] ?? $payload['content_format'] ?? null;

        if (is_string($declared) && $declared !== '') {
            return $declared;
        }

        return 'markdown';
    }

    public function isEmpty(): bool
    {
        return trim($this->content) === '';
    }

    public function isMarkdown(): bool
    {
        return $this->format === 'markdown' || $this->format === 'md';
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
