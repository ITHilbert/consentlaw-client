<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Data;

use Carbon\CarbonImmutable;
use ITHilbert\ConsentLaw\Support\MarkdownRenderer;

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
            format: Inhaltsformat::ausApi($payload['format'] ?? $payload['content_format'] ?? null)->value,
            generatedAt: self::parseDate($payload['generated_at'] ?? null),
            locale: isset($payload['locale']) ? (string) $payload['locale'] : null,
            isStale: $isStale,
            fetchedAt: $fetchedAt,
        );
    }

    public function isEmpty(): bool
    {
        return trim($this->content) === '';
    }

    /** Inhalt als ausgabefertiges HTML (Markdown gerendert, HTML unverändert). */
    public function contentHtml(): string
    {
        return $this->isMarkdown()
            ? MarkdownRenderer::render($this->content)
            : $this->content;
    }

    public function isMarkdown(): bool
    {
        return Inhaltsformat::ausApi($this->format) === Inhaltsformat::Markdown;
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
