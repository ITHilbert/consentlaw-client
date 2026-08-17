<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Tests;

use ITHilbert\ConsentLaw\Data\Document;
use PHPUnit\Framework\TestCase;

/**
 * Formaterkennung: Die API liefert je Endpunkt Markdown oder HTML und schickt
 * nicht immer ein `format`-Feld mit. Wird Markdown fälschlich als HTML behandelt,
 * landet "# Impressum" sichtbar auf der Kundenseite.
 */
final class DocumentFormatTest extends TestCase
{
    public function test_deklariertes_format_hat_vorrang(): void
    {
        $doc = Document::fromApi('impressum', ['content' => '# Titel', 'format' => 'html']);

        $this->assertSame('html', $doc->format);
        $this->assertFalse($doc->isMarkdown());
    }

    public function test_markdown_wird_ohne_format_angabe_erkannt(): void
    {
        $doc = Document::fromApi('impressum', ['content' => "# Impressum\n\n## Kontakt"]);

        $this->assertSame('markdown', $doc->format);
        $this->assertTrue($doc->isMarkdown());
    }

    public function test_inline_br_macht_markdown_nicht_zu_html(): void
    {
        // Realfall aus consentLaw: Markdown mit <br> in der Adresse.
        $content = "# Impressum\n\n## Angaben\n\nIT-Hilbert GmbH<br>Mühlenberg 15<br>24217 Schönberg";

        $doc = Document::fromApi('impressum', ['content' => $content]);

        $this->assertSame('markdown', $doc->format, '<br> ist ein Inline-Tag und kein HTML-Indiz.');
    }

    public function test_echtes_html_wird_erkannt(): void
    {
        $doc = Document::fromApi('agb', ['content' => '<h1>AGB</h1><p>Text</p>']);

        $this->assertSame('html', $doc->format);
        $this->assertFalse($doc->isMarkdown());
    }

    public function test_leerer_inhalt_gilt_als_leer(): void
    {
        $this->assertTrue(Document::fromApi('agb', ['content' => "  \n "])->isEmpty());
        $this->assertFalse(Document::fromApi('agb', ['content' => 'Text'])->isEmpty());
    }

    public function test_fehlende_felder_fuehren_nicht_zum_fehler(): void
    {
        $doc = Document::fromApi('impressum', []);

        $this->assertSame('', $doc->content);
        $this->assertTrue($doc->isEmpty());
        $this->assertNull($doc->generatedAt);
    }

    public function test_kaputtes_datum_wird_zu_null(): void
    {
        $doc = Document::fromApi('impressum', ['content' => 'x', 'generated_at' => 'kein-datum']);

        $this->assertNull($doc->generatedAt);
    }
}
