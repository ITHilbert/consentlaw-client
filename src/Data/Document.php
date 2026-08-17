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
            format: self::resolveFormat($payload, $content),
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
     * Datenschutz, AGB). Deren Inhalte sind je nach Generator Markdown ODER HTML.
     * Ohne Angabe wird daher am Inhalt erkannt, statt ein Format zu unterstellen –
     * sonst landet Markdown-Quelltext ("# Impressum") sichtbar auf der Seite.
     */
    private static function resolveFormat(array $payload, string $content): string
    {
        $declared = $payload['format'] ?? $payload['content_format'] ?? null;

        if (is_string($declared) && $declared !== '') {
            return $declared;
        }

        return self::looksLikeHtml($content) ? 'html' : 'markdown';
    }

    /**
     * Enthält der Inhalt echte Block-Level-HTML-Tags?
     *
     * Inline-Tags wie <br>, <strong> oder <a> zählen NICHT: Sie sind in
     * Markdown-Dokumenten üblich (z.B. Adresszeilen mit <br>) und würden den
     * Inhalt sonst fälschlich als HTML einstufen. Zusätzlich schlägt ein
     * Markdown-Heading am Zeilenanfang die HTML-Erkennung, weil "# " in HTML
     * keine Bedeutung hat.
     */
    private static function looksLikeHtml(string $content): bool
    {
        if (preg_match('/^\s{0,3}#{1,6}\s/m', $content) === 1) {
            return false;
        }

        return preg_match('/<(p|div|h[1-6]|ul|ol|li|table|section|article|main|header|footer)\b[^>]*>/i', $content) === 1;
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
