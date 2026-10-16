<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Tests;

use ITHilbert\ConsentLaw\Data\Document;

/**
 * AK10 – Eine Antwort ohne `format` gilt als `markdown` (und läuft damit durch
 * den Markdown-Filter nach AK4), auch wenn der Inhalt wie HTML aussieht.
 * Ein ausdrückliches `format: html` bleibt html (AK5).
 */
final class FormatOhneAngabeTest extends AppTestCase
{
    private const HTML_ARTIG = '<div><script>alert(1)</script><p>x</p></div>';

    /** @return array<string, mixed> Payload ohne `format`-Feld. */
    private function payloadOhneFormat(string $content): array
    {
        $payload = $this->dokumentPayload($content);
        unset($payload['format']);

        return $payload;
    }

    // AK10
    public function test_html_artiger_inhalt_ohne_format_wird_zu_markdown(): void
    {
        $dokument = Document::fromApi('agb', $this->payloadOhneFormat(self::HTML_ARTIG));

        $this->assertSame('markdown', $dokument->format);
        $this->assertTrue($dokument->isMarkdown());
    }

    // AK10
    public function test_html_artiger_inhalt_ohne_format_aus_der_api_antwort_ist_markdown(): void
    {
        $this->api(['*' => $this->payloadOhneFormat(self::HTML_ARTIG)]);

        $dokument = $this->client()->agb()->get();

        $this->assertNotNull($dokument);
        $this->assertSame('markdown', $dokument->format);
    }

    // AK10
    public function test_html_artiger_inhalt_ohne_format_wird_in_der_komponente_gefiltert(): void
    {
        $this->api(['*' => $this->payloadOhneFormat(self::HTML_ARTIG)]);

        $html = $this->rendere('<x-consentlaw::agb />');

        $this->assertStringNotContainsStringIgnoringCase('<script', $html);
        $this->assertStringNotContainsString('alert(1)', $html, 'Der Skript-Block darf nicht ausgeliefert werden.');
    }

    // AK10
    public function test_html_artiger_inhalt_ohne_format_wird_in_allen_dokument_komponenten_gefiltert(): void
    {
        foreach (['impressum', 'datenschutz', 'agb', 'cookie-richtlinie', 'avv'] as $komponente) {
            $this->api(['*' => $this->payloadOhneFormat(self::HTML_ARTIG)]);

            $html = $this->rendere("<x-consentlaw::{$komponente} />");

            $this->assertStringNotContainsStringIgnoringCase('<script', $html, $komponente);
        }
    }

    // AK10
    public function test_html_artiger_inhalt_mit_event_handler_ohne_format_wird_gefiltert(): void
    {
        $this->api(['*' => $this->payloadOhneFormat('<section><img src=x onerror=alert(1)><p>Text</p></section>')]);

        $html = $this->rendere('<x-consentlaw::impressum />');

        $this->assertStringNotContainsStringIgnoringCase('onerror', $html);
        $this->assertStringNotContainsStringIgnoringCase('<img', $html);
    }

    // AK10
    public function test_markdown_mit_heading_ohne_format_bleibt_markdown(): void
    {
        $dokument = Document::fromApi('impressum', ['content' => "# Impressum\n\n## Kontakt"]);

        $this->assertSame('markdown', $dokument->format);
    }

    // AK10 / AK5: explizites html bleibt html – auch bei Skript-artigem Inhalt wird es nicht neu bewertet.
    public function test_explizites_format_html_bleibt_html(): void
    {
        $dokument = Document::fromApi('agb', ['content' => '<p>Text</p>', 'format' => 'html']);

        $this->assertSame('html', $dokument->format);
        $this->assertFalse($dokument->isMarkdown());
    }

    // AK10 / AK5
    public function test_explizites_format_html_aus_der_api_wird_nicht_durch_den_markdown_renderer_geschickt(): void
    {
        $this->api(['*' => $this->dokumentPayload('<p>Absatz</p>', 'html')]);

        $dokument = $this->client()->agb()->get();
        $html = $this->rendere('<x-consentlaw::agb />');

        $this->assertSame('html', $dokument->format);
        $this->assertStringContainsString('<p>Absatz</p>', $html);
    }
}
