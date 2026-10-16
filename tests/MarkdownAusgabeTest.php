<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Tests;

use ITHilbert\ConsentLaw\Data\Document;

/**
 * AK4 – Die Aufrufer des Markdown-Renderings: Komponente `document`, Fertig-Seite
 * `pages/document`, Komponente `jobs` (HTML und JSON-LD).
 *
 * Die Views werden hier wirklich gerendert (echte Blade-Engine, siehe AppTestCase).
 * Zusätzlich sichern Datei-Inspektions-Tests ab, dass das Rendern an EINER Stelle
 * im Package geschieht (Spec: „an einer Stelle“): Kein Aufrufer ruft Str::markdown()
 * oder CommonMark selbst auf; alle Blade-Aufrufer verwenden
 * ITHilbert\ConsentLaw\Support\MarkdownRenderer::render() (Vertrag siehe
 * MarkdownRendererTest).
 */
final class MarkdownAusgabeTest extends AppTestCase
{
    private const GEFAEHRLICH = <<<'MD'
# Titel

Text mit <b onclick="alert('klick')">fett</b> und Adresse<br>Mühlenberg 15<br/>24217 Schönberg<br />Deutschland

<script>alert('xss')</script>

<iframe src="https://evil.example/frame"></iframe>

<img src=x onerror=alert(1)>

[Böser Link](javascript:alert(1))

[Guter Link](https://example.org/seite)
MD;

    private function pruefeSicher(string $html): void
    {
        $this->assertStringNotContainsStringIgnoringCase('<script', $html);
        $this->assertStringNotContainsStringIgnoringCase('<iframe', $html);
        $this->assertStringNotContainsStringIgnoringCase('<img', $html);
        $this->assertStringNotContainsStringIgnoringCase('onerror', $html);
        $this->assertStringNotContainsStringIgnoringCase('onclick', $html);
        $this->assertStringNotContainsStringIgnoringCase('javascript:', $html);
    }

    private function pruefeNormalesBleibtErhalten(string $html): void
    {
        $this->assertStringContainsString('<h1>Titel</h1>', $html);
        $this->assertStringContainsString('<a href="https://example.org/seite">Guter Link</a>', $html);
        $this->assertMatchesRegularExpression('#Adresse\s*<br />\s*Mühlenberg 15\s*<br />\s*24217 Schönberg\s*<br />\s*Deutschland#u', $html);
    }

    /** Entfernt die JSON-LD-Blöcke und liefert sie dekodiert zurück. @return array{0: string, 1: list<array<string, mixed>>} */
    private function trenneJsonLd(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $treffer);

        $daten = array_map(static fn (string $json): array => json_decode(trim($json), true, 512, JSON_THROW_ON_ERROR), $treffer[1]);

        return [preg_replace('#<script type="application/ld\+json">.*?</script>#s', '', $html) ?? $html, $daten];
    }

    // AK4 – Komponente document
    public function test_dokument_komponente_gibt_kein_gefaehrliches_html_aus(): void
    {
        $this->api(['*' => $this->dokumentPayload(self::GEFAEHRLICH)]);

        $html = $this->rendere('<x-consentlaw::impressum />');

        $this->pruefeSicher($html);
    }

    // AK4 – Komponente document
    public function test_dokument_komponente_behaelt_normales_markdown_und_zeilenumbrueche(): void
    {
        $this->api(['*' => $this->dokumentPayload(self::GEFAEHRLICH)]);

        $html = $this->rendere('<x-consentlaw::document type="datenschutz" />');

        $this->pruefeNormalesBleibtErhalten($html);
    }

    // AK4 – Fertig-Seite pages/document
    public function test_fertig_seite_gibt_kein_gefaehrliches_html_aus(): void
    {
        $dokument = Document::fromApi('impressum', $this->dokumentPayload(self::GEFAEHRLICH));

        $html = view('consentlaw::pages.document', ['document' => $dokument, 'layout' => null])->render();

        $this->pruefeSicher($html);
        $this->pruefeNormalesBleibtErhalten($html);
    }

    // AK4 – Komponente jobs (HTML-Beschreibung)
    public function test_jobs_komponente_gibt_kein_gefaehrliches_html_aus(): void
    {
        $this->api(['*' => $this->jobsPayload(['description' => self::GEFAEHRLICH])]);

        $html = $this->rendere('<x-consentlaw::jobs />');
        [$ohneJsonLd] = $this->trenneJsonLd($html);

        $this->pruefeSicher($ohneJsonLd);
        $this->pruefeNormalesBleibtErhalten($ohneJsonLd);
    }

    // AK4 – Komponente jobs (JSON-LD description)
    public function test_json_ld_der_jobs_enthaelt_kein_gefaehrliches_html(): void
    {
        $this->api(['*' => $this->jobsPayload(['description' => self::GEFAEHRLICH])]);

        $html = $this->rendere('<x-consentlaw::jobs />');
        [, $jsonLd] = $this->trenneJsonLd($html);

        $this->assertCount(1, $jsonLd, 'Pro Job genau ein JSON-LD-Block.');
        // Die JSON-Kodierung maskiert "<" – deshalb wird der DEKODIERTE Text geprüft.
        $beschreibung = (string) $jsonLd[0]['description'];

        $this->pruefeSicher($beschreibung);
        $this->pruefeNormalesBleibtErhalten($beschreibung);
    }

    // Randfall: format html wird nicht durch den Markdown-Renderer geschickt (bestehendes Verhalten).
    public function test_html_format_wird_nicht_als_markdown_gerendert(): void
    {
        $this->api(['*' => $this->dokumentPayload("<p>Absatz</p>\n\n**kein fett**\n\n# kein Titel", 'html')]);

        $html = $this->rendere('<x-consentlaw::impressum />');

        $this->assertStringContainsString('<p>Absatz</p>', $html);
        $this->assertStringContainsString('**kein fett**', $html);
        $this->assertStringNotContainsString('<strong>', $html);
        $this->assertStringNotContainsString('<h1>', $html);
    }

    // AK4 – Markdown bleibt Markdown (Regression der Umstellung)
    public function test_markdown_dokument_wird_weiterhin_zu_html_gerendert(): void
    {
        $this->api(['*' => $this->dokumentPayload("# Impressum\n\nText mit **fett**.")]);

        $html = $this->rendere('<x-consentlaw::impressum />');

        $this->assertStringContainsString('<h1>Impressum</h1>', $html);
        $this->assertStringContainsString('<strong>fett</strong>', $html);
    }

    // AK4 – „an einer Stelle“: kein Aufrufer rendert selbst
    public function test_nur_der_zentrale_renderer_ruft_commonmark_oder_str_markdown_auf(): void
    {
        $dateien = $this->quelldateien();
        $this->assertNotEmpty($dateien);

        $verstoesse = [];

        foreach ($dateien as $datei) {
            if (str_ends_with($datei, 'Support/MarkdownRenderer.php')) {
                continue;
            }

            $inhalt = (string) file_get_contents($datei);

            if (preg_match('/Str::markdown|->markdown\(|CommonMark|MarkdownConverter/', $inhalt) === 1) {
                $verstoesse[] = substr($datei, strlen($this->quellordner()) + 1);
            }
        }

        $this->assertSame([], $verstoesse, 'Diese Dateien rendern Markdown selbst statt über MarkdownRenderer::render().');
    }

    // AK4 – „an einer Stelle“: alle Blade-Aufrufer nutzen die zentrale Stelle
    public function test_alle_blade_aufrufer_nutzen_den_zentralen_renderer(): void
    {
        $erwartet = [
            'Resources/views/components/document.blade.php' => 1,
            'Resources/views/pages/document.blade.php' => 1,
            'Resources/views/components/jobs.blade.php' => 2, // HTML-Beschreibung + JSON-LD
        ];

        foreach ($erwartet as $datei => $mindestens) {
            $inhalt = (string) file_get_contents($this->quellordner().'/'.$datei);

            $this->assertGreaterThanOrEqual(
                $mindestens,
                preg_match_all('/MarkdownRenderer::render\(/', $inhalt),
                "{$datei} muss MarkdownRenderer::render() {$mindestens}× aufrufen.",
            );
        }
    }

    private function quellordner(): string
    {
        return (string) realpath(__DIR__.'/../src');
    }

    /** @return list<string> */
    private function quelldateien(): array
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->quellordner(), \FilesystemIterator::SKIP_DOTS));
        $dateien = [];

        foreach ($iterator as $datei) {
            if ($datei->isFile() && str_ends_with($datei->getFilename(), '.php')) {
                $dateien[] = $datei->getPathname();
            }
        }

        sort($dateien);

        return $dateien;
    }
}
