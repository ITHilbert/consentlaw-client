<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Tests;

use ITHilbert\ConsentLaw\Data\Document;
use ITHilbert\ConsentLaw\Support\MarkdownRenderer;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * AK1 (Feature zeilenumbruch-token) – Ein einfacher Zeilenumbruch im Markdown ist
 * ein Zeilenumbruch: an jeder Stelle steht ein `<br />`, in allen Ausgabestellen
 * (Dokument-Komponente, Fertig-Seite, Jobs-HTML, Jobs-JSON-LD).
 *
 * Leerzeilen trennen weiterhin Absätze, Listen/Überschriften/Tabellen/Code-Blöcke
 * bleiben unverändert, die Filterung (AK4/AK10 Feature robustheit) gilt weiter.
 */
final class ZeilenumbruchTest extends AppTestCase
{
    private const ADRESSE = "IT-Hilbert GmbH\nMühlenberg 15\n24217 Schönberg";

    private function anzahlBr(string $html): int
    {
        return substr_count($html, '<br />');
    }

    private function anzahlAbsaetze(string $html): int
    {
        return substr_count($html, '<p>');
    }

    /** @return array{0: string, 1: list<array<string, mixed>>} HTML ohne JSON-LD und die dekodierten JSON-LD-Blöcke */
    private function trenneJsonLd(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $treffer);

        $daten = array_map(static fn (string $json): array => json_decode(trim($json), true, 512, JSON_THROW_ON_ERROR), $treffer[1]);

        return [preg_replace('#<script type="application/ld\+json">.*?</script>#s', '', $html) ?? $html, $daten];
    }

    // ── Renderer ─────────────────────────────────────────────────────────────

    // AK1
    public function test_einfache_zeilenumbrueche_werden_zu_br_in_einem_absatz(): void
    {
        $html = MarkdownRenderer::render(self::ADRESSE);

        $this->assertSame(2, $this->anzahlBr($html), $html);
        $this->assertSame(1, $this->anzahlAbsaetze($html), $html);
        $this->assertMatchesRegularExpression(
            '#^<p>IT-Hilbert GmbH\s*<br />\s*Mühlenberg 15\s*<br />\s*24217 Schönberg</p>$#u',
            $html,
        );
    }

    // AK1
    public function test_windows_zeilenumbrueche_werden_zu_br_in_einem_absatz(): void
    {
        $html = MarkdownRenderer::render(str_replace("\n", "\r\n", self::ADRESSE));

        $this->assertSame(2, $this->anzahlBr($html), $html);
        $this->assertSame(1, $this->anzahlAbsaetze($html), $html);
        $this->assertStringNotContainsString("\r", $html);
        $this->assertMatchesRegularExpression(
            '#^<p>IT-Hilbert GmbH\s*<br />\s*Mühlenberg 15\s*<br />\s*24217 Schönberg</p>$#u',
            $html,
        );
    }

    // AK1
    public function test_leerzeile_trennt_absaetze_und_erzeugt_kein_br(): void
    {
        $html = MarkdownRenderer::render("Absatz eins\n\nAbsatz zwei");

        $this->assertSame(2, $this->anzahlAbsaetze($html), $html);
        $this->assertSame(0, $this->anzahlBr($html), $html);
    }

    // AK1
    public function test_leerzeile_mit_windows_umbruechen_trennt_absaetze(): void
    {
        $html = MarkdownRenderer::render("Absatz eins\r\nZeile zwei\r\n\r\nAbsatz zwei");

        $this->assertSame(2, $this->anzahlAbsaetze($html), $html);
        $this->assertSame(1, $this->anzahlBr($html), $html);
    }

    // AK1 – Randfall: harter Umbruch (zwei Leerzeichen) ergibt genau ein <br />
    public function test_zwei_leerzeichen_am_zeilenende_ergeben_genau_ein_br(): void
    {
        $html = MarkdownRenderer::render("Zeile1  \nZeile2");

        $this->assertSame(1, $this->anzahlBr($html), $html);
        $this->assertSame(1, $this->anzahlAbsaetze($html), $html);
    }

    // AK1 – Randfall: harter Umbruch mit Windows-Zeilenende
    public function test_zwei_leerzeichen_mit_windows_zeilenende_ergeben_genau_ein_br(): void
    {
        $html = MarkdownRenderer::render("Zeile1  \r\nZeile2");

        $this->assertSame(1, $this->anzahlBr($html), $html);
    }

    // AK1 – Randfall: Backslash am Zeilenende (CommonMark-Hardbreak) ebenfalls genau ein <br />
    public function test_backslash_am_zeilenende_ergibt_genau_ein_br(): void
    {
        $html = MarkdownRenderer::render("Zeile1\\\nZeile2");

        $this->assertSame(1, $this->anzahlBr($html), $html);
    }

    // AK1 – Randfall: <br> im Text (bestehendes Verhalten)
    #[DataProvider('brSchreibweisen')]
    public function test_br_tag_im_text_ergibt_weiterhin_genau_ein_br(string $br): void
    {
        $html = MarkdownRenderer::render("Zeile1{$br}Zeile2");

        $this->assertSame(1, $this->anzahlBr($html), $html);
        $this->assertStringNotContainsString('<br>', $html);
        $this->assertStringNotContainsString('<br/>', $html);
    }

    /** @return array<string, array{string}> */
    public static function brSchreibweisen(): array
    {
        return ['<br>' => ['<br>'], '<br/>' => ['<br/>'], '<br />' => ['<br />']];
    }

    // AK1 – Randfall: <br> direkt vor einem Zeilenende ergibt genau ein <br />
    #[DataProvider('brMitZeilenende')]
    public function test_br_tag_vor_zeilenende_ergibt_genau_ein_br(string $br, string $umbruch): void
    {
        $html = MarkdownRenderer::render("Zeile1{$br}{$umbruch}Zeile2");

        $this->assertSame(1, $this->anzahlBr($html), $html);
        $this->assertSame(1, $this->anzahlAbsaetze($html), $html);
        $this->assertMatchesRegularExpression('#^<p>Zeile1\s*<br />\s*Zeile2</p>$#u', $html);
    }

    /** @return array<string, array{string, string}> */
    public static function brMitZeilenende(): array
    {
        $faelle = [];

        foreach (['<br>', '<br/>', '<br />'] as $br) {
            foreach (['LF' => "\n", 'CRLF' => "\r\n"] as $name => $umbruch) {
                $faelle["{$br} + {$name}"] = [$br, $umbruch];
            }
        }

        return $faelle;
    }

    // AK1 – Randfall: mehrere Zeilen, jede mit <br> am Ende, ergeben je Zeilenende ein <br />
    public function test_br_tags_am_zeilenende_in_adressblock_ergeben_je_zeile_ein_br(): void
    {
        $html = MarkdownRenderer::render("IT-Hilbert GmbH<br>\r\nMühlenberg 15<br />\r\n24217 Schönberg");

        $this->assertSame(2, $this->anzahlBr($html), $html);
    }

    // AK1 – Unverändert: Liste
    public function test_liste_bleibt_unveraendert(): void
    {
        $html = MarkdownRenderer::render("- eins\n- zwei\n- drei");

        $this->assertSame(3, substr_count($html, '<li>'), $html);
        $this->assertStringContainsString('<ul>', $html);
        $this->assertSame(0, $this->anzahlBr($html), $html);
    }

    // AK1 – Unverändert: Überschrift direkt vor Text
    public function test_ueberschrift_bleibt_unveraendert(): void
    {
        $html = MarkdownRenderer::render("# Titel\nText danach");

        $this->assertStringContainsString('<h1>Titel</h1>', $html);
        $this->assertStringContainsString('<p>Text danach</p>', $html);
        $this->assertSame(0, $this->anzahlBr($html), $html);
    }

    // AK1 – Unverändert: Tabelle
    public function test_tabelle_bleibt_unveraendert(): void
    {
        $html = MarkdownRenderer::render("| Name | Wert |\n|---|---|\n| a | 1 |\n| b | 2 |");

        $this->assertStringContainsString('<table>', $html);
        $this->assertSame(3, substr_count($html, '<tr>'), $html);
        $this->assertSame(0, $this->anzahlBr($html), $html);
    }

    // AK1 – Unverändert: Code-Block (Zeilenumbrüche bleiben Text, kein <br />)
    public function test_code_block_bleibt_ohne_br(): void
    {
        $html = MarkdownRenderer::render("```\nzeile1\nzeile2\n```");

        $this->assertStringContainsString('<pre><code>zeile1'."\n".'zeile2', $html);
        $this->assertSame(0, $this->anzahlBr($html), $html);
    }

    // AK1 – Unverändert: eingerückter Code-Block
    public function test_eingerueckter_code_block_bleibt_ohne_br(): void
    {
        $html = MarkdownRenderer::render("Text\n\n    zeile1\n    zeile2");

        $this->assertStringContainsString('<pre><code>zeile1'."\n".'zeile2', $html);
        $this->assertSame(0, $this->anzahlBr($html), $html);
    }

    // AK1 – Filterung bleibt (AK4/AK10 robustheit)
    public function test_filterung_bleibt_bei_zeilenumbruechen_erhalten(): void
    {
        $html = MarkdownRenderer::render("Zeile1\n<script>alert(1)</script>\nZeile2\n[Link](javascript:alert(1))\n<img src=x onerror=alert(2)>");

        $this->assertStringNotContainsStringIgnoringCase('<script', $html);
        $this->assertStringNotContainsStringIgnoringCase('javascript:', $html);
        $this->assertStringNotContainsStringIgnoringCase('<img', $html);
        $this->assertStringNotContainsStringIgnoringCase('onerror', $html);
        $this->assertStringContainsString('Zeile1', $html);
        $this->assertStringContainsString('Zeile2', $html);
    }

    // ── Ausgabestellen ───────────────────────────────────────────────────────

    // AK1 – Komponente document (Impressum)
    public function test_impressum_komponente_rendert_zeilenumbrueche_als_br(): void
    {
        $this->api(['*' => $this->dokumentPayload(str_replace("\n", "\r\n", self::ADRESSE))]);

        $html = $this->rendere('<x-consentlaw::impressum />');

        $this->assertSame(2, $this->anzahlBr($html), $html);
        $this->assertMatchesRegularExpression('#IT-Hilbert GmbH\s*<br />\s*Mühlenberg 15\s*<br />\s*24217 Schönberg#u', $html);
    }

    // AK1 – Komponente document (Datenschutz, LF)
    public function test_dokument_komponente_rendert_lf_zeilenumbrueche_als_br(): void
    {
        $this->api(['*' => $this->dokumentPayload(self::ADRESSE)]);

        $html = $this->rendere('<x-consentlaw::document type="datenschutz" />');

        $this->assertSame(2, $this->anzahlBr($html), $html);
    }

    // AK1 – Randfall in der Komponente: <br> vor Zeilenende
    public function test_komponente_rendert_br_vor_zeilenende_als_genau_ein_br(): void
    {
        $this->api(['*' => $this->dokumentPayload("IT-Hilbert GmbH<br>\r\nMühlenberg 15<br />\n24217 Schönberg")]);

        $html = $this->rendere('<x-consentlaw::impressum />');

        $this->assertSame(2, $this->anzahlBr($html), $html);
    }

    // AK1 – Fertig-Seite pages/document
    public function test_fertig_seite_rendert_zeilenumbrueche_als_br(): void
    {
        $dokument = Document::fromApi('impressum', $this->dokumentPayload(self::ADRESSE));

        $html = view('consentlaw::pages.document', ['document' => $dokument, 'layout' => null])->render();

        $this->assertSame(2, $this->anzahlBr($html), $html);
    }

    // AK1 – Komponente jobs (HTML)
    public function test_jobs_komponente_rendert_zeilenumbrueche_der_beschreibung_als_br(): void
    {
        $this->api(['*' => $this->jobsPayload(['description' => "Erste Zeile\nZweite Zeile"])]);

        $html = $this->rendere('<x-consentlaw::jobs />');
        [$ohneJsonLd] = $this->trenneJsonLd($html);

        $this->assertSame(1, $this->anzahlBr($ohneJsonLd), $ohneJsonLd);
        $this->assertMatchesRegularExpression('#Erste Zeile\s*<br />\s*Zweite Zeile#u', $ohneJsonLd);
    }

    // AK1 – Komponente jobs (JSON-LD, dekodiert)
    public function test_json_ld_der_jobs_enthaelt_br_fuer_zeilenumbrueche(): void
    {
        $this->api(['*' => $this->jobsPayload(['description' => "Erste Zeile\r\nZweite Zeile"])]);

        $html = $this->rendere('<x-consentlaw::jobs />');
        [, $jsonLd] = $this->trenneJsonLd($html);

        $this->assertCount(1, $jsonLd);
        $beschreibung = (string) $jsonLd[0]['description'];

        $this->assertSame(1, $this->anzahlBr($beschreibung), $beschreibung);
        $this->assertMatchesRegularExpression('#Erste Zeile\s*<br />\s*Zweite Zeile#u', $beschreibung);
    }
}
