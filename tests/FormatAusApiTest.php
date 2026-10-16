<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Tests;

use ITHilbert\ConsentLaw\Data\Document;

/**
 * AK5 – Das `format` aus der API gilt, ohne Erkennung am Inhalt
 * (bestehendes Verhalten, absichern).
 */
final class FormatAusApiTest extends AppTestCase
{
    // AK5
    public function test_format_markdown_gilt_auch_wenn_der_inhalt_wie_html_aussieht(): void
    {
        $dokument = Document::fromApi('agb', ['content' => '<h1>AGB</h1><p>Text</p>', 'format' => 'markdown']);

        $this->assertSame('markdown', $dokument->format);
        $this->assertTrue($dokument->isMarkdown());
    }

    // AK5
    public function test_format_html_gilt_auch_wenn_der_inhalt_wie_markdown_aussieht(): void
    {
        $dokument = Document::fromApi('agb', ['content' => "# AGB\n\n## Geltung", 'format' => 'html']);

        $this->assertSame('html', $dokument->format);
        $this->assertFalse($dokument->isMarkdown());
    }

    // AK5
    public function test_format_aus_der_api_antwort_erreicht_das_dokument(): void
    {
        $this->api(['*' => $this->dokumentPayload('<h1>AGB</h1><p>Text</p>', 'markdown')]);
        $markdown = $this->client()->agb()->get();

        $this->assertTrue($markdown->isMarkdown());

        $this->api(['/api/avv/' => $this->dokumentPayload("# AVV\n\n## Gegenstand", 'html')]);
        $html = $this->client()->avv()->get();

        $this->assertFalse($html->isMarkdown());
        $this->assertSame('html', $html->format);
    }
}
