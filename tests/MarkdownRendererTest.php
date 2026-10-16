<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Tests;

use ITHilbert\ConsentLaw\Support\MarkdownRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * AK4 – Sichere Markdown-Ausgabe, zentrale Render-Stelle.
 *
 * VERTRAG (neu, noch nicht vorhanden):
 *   Klasse  ITHilbert\ConsentLaw\Support\MarkdownRenderer  (final)
 *   Methode public static function render(string $markdown): string
 *
 * Verhalten:
 *  - Rohes HTML (<script>, <iframe>, <img onerror=…>, Event-Handler) wird entfernt
 *    (strip, nicht escapen: weder Tag noch Handler stehen in der Ausgabe).
 *  - Unsichere Link-Ziele (javascript:, data:, vbscript: …) landen nicht in der Ausgabe.
 *  - <br>, <br/>, <br /> werden zu einem harten Zeilenumbruch (Ausgabe: <br />).
 *  - Normales Markdown inkl. GitHub-Tabellen bleibt korrekt.
 *
 * Die Aufrufer (Blade) sind in MarkdownAusgabeTest abgedeckt.
 */
final class MarkdownRendererTest extends TestCase
{
    private function render(string $markdown): string
    {
        $this->assertTrue(
            class_exists(MarkdownRenderer::class),
            'Vertrag AK4: Klasse '.MarkdownRenderer::class.' mit statischer Methode render(string): string fehlt.',
        );

        return MarkdownRenderer::render($markdown);
    }

    // AK4
    public function test_script_tag_wird_entfernt(): void
    {
        $html = $this->render("Vorher\n\n<script>alert('xss')</script>\n\nNachher");

        $this->assertStringNotContainsStringIgnoringCase('<script', $html);
        $this->assertStringNotContainsStringIgnoringCase('</script', $html);
        $this->assertStringContainsString('Vorher', $html);
        $this->assertStringContainsString('Nachher', $html);
    }

    // AK4
    public function test_script_tag_im_fliesstext_wird_entfernt(): void
    {
        $html = $this->render('Text <script>alert(1)</script> Ende');

        $this->assertStringNotContainsStringIgnoringCase('<script', $html);
        $this->assertStringNotContainsStringIgnoringCase('</script', $html);
    }

    // AK4
    public function test_iframe_wird_entfernt(): void
    {
        $html = $this->render("Vorher\n\n<iframe src=\"https://evil.example\"></iframe>\n\nNachher");

        $this->assertStringNotContainsStringIgnoringCase('<iframe', $html);
        $this->assertStringNotContainsString('evil.example', $html);
    }

    // AK4
    public function test_img_mit_event_handler_wird_entfernt(): void
    {
        $html = $this->render("Vorher\n\n<img src=x onerror=alert(1)>\n\nNachher");

        $this->assertStringNotContainsStringIgnoringCase('<img', $html);
        $this->assertStringNotContainsStringIgnoringCase('onerror', $html);
    }

    // AK4
    public function test_img_mit_event_handler_im_fliesstext_wird_entfernt(): void
    {
        $html = $this->render('Text <img src=x onerror=alert(1)> Ende');

        $this->assertStringNotContainsStringIgnoringCase('<img', $html);
        $this->assertStringNotContainsStringIgnoringCase('onerror', $html);
    }

    // AK4
    public function test_event_handler_an_erlaubtem_inline_tag_wird_entfernt(): void
    {
        $html = $this->render('Text <b onclick="alert(1)">fett</b> Ende');

        $this->assertStringNotContainsStringIgnoringCase('onclick', $html);
        $this->assertStringNotContainsString('<b', $html);
    }

    /** @return array<string, array{0: string}> */
    public static function unsichereLinks(): array
    {
        return [
            'javascript' => ['[Klick](javascript:alert(1))'],
            'javascript gemischte Schreibweise' => ['[Klick](JaVaScRiPt:alert(1))'],
            'data' => ['[Klick](data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==)'],
        ];
    }

    // AK4
    #[DataProvider('unsichereLinks')]
    public function test_unsichere_link_ziele_landen_nicht_in_der_ausgabe(string $markdown): void
    {
        $html = $this->render($markdown);

        $this->assertStringNotContainsStringIgnoringCase('javascript:', $html);
        $this->assertStringNotContainsStringIgnoringCase('data:text', $html);
        $this->assertStringNotContainsString('alert(1)', $html);
    }

    // AK4
    public function test_javascript_link_ist_kein_klickbarer_link_mit_diesem_ziel(): void
    {
        $html = $this->render('[x](javascript:alert(1))');

        $this->assertDoesNotMatchRegularExpression('/href\s*=\s*["\']?\s*javascript/i', $html);
    }

    /** @return array<string, array{0: string}> */
    public static function umbrueche(): array
    {
        return [
            '<br>' => ['Zeile1<br>Zeile2'],
            '<br/>' => ['Zeile1<br/>Zeile2'],
            '<br />' => ['Zeile1<br />Zeile2'],
        ];
    }

    // AK4
    #[DataProvider('umbrueche')]
    public function test_br_varianten_werden_zu_einem_zeilenumbruch(string $markdown): void
    {
        $html = $this->render($markdown);

        $this->assertMatchesRegularExpression('#Zeile1\s*<br />\s*Zeile2#', $html);
        $this->assertStringNotContainsString('&lt;br', $html, '<br> darf nicht als Text erscheinen.');
    }

    // AK4
    public function test_adresse_mit_br_bleibt_mehrzeilig(): void
    {
        $html = $this->render("# Impressum\n\nIT-Hilbert GmbH<br>Mühlenberg 15<br>24217 Schönberg");

        $this->assertSame(2, preg_match_all('#<br />#', $html), 'Zwei Umbrüche für drei Adresszeilen erwartet.');
        $this->assertStringContainsString('<h1>Impressum</h1>', $html);
    }

    // AK4
    public function test_br_bleibt_erhalten_waehrend_gefaehrliches_html_entfernt_wird(): void
    {
        $html = $this->render("Zeile1<br>Zeile2\n\n<script>alert(1)</script>");

        $this->assertMatchesRegularExpression('#Zeile1\s*<br />\s*Zeile2#', $html);
        $this->assertStringNotContainsStringIgnoringCase('<script', $html);
    }

    // AK4 (normales Markdown bleibt korrekt)
    public function test_ueberschrift_fett_und_liste_werden_gerendert(): void
    {
        $html = $this->render("# Titel\n\nText mit **fett**.\n\n- Eins\n- Zwei");

        $this->assertStringContainsString('<h1>Titel</h1>', $html);
        $this->assertStringContainsString('<strong>fett</strong>', $html);
        $this->assertStringContainsString('<ul>', $html);
        $this->assertStringContainsString('<li>Eins</li>', $html);
        $this->assertStringContainsString('<li>Zwei</li>', $html);
    }

    // AK4 (normales Markdown bleibt korrekt)
    public function test_normale_links_bleiben_erhalten(): void
    {
        $html = $this->render('[Seite](https://example.org/seite) und [Mail](mailto:info@example.org) und [Anker](#abschnitt)');

        $this->assertStringContainsString('<a href="https://example.org/seite">Seite</a>', $html);
        $this->assertStringContainsString('<a href="mailto:info@example.org">Mail</a>', $html);
        $this->assertStringContainsString('<a href="#abschnitt">Anker</a>', $html);
    }

    // AK4 (normales Markdown bleibt korrekt – Rechtstexte enthalten Tabellen)
    public function test_tabellen_werden_weiterhin_gerendert(): void
    {
        $html = $this->render("| Zweck | Dauer |\n|---|---|\n| Login | Sitzung |");

        $this->assertStringContainsString('<table>', $html);
        $this->assertStringContainsString('<th>Zweck</th>', $html);
        $this->assertStringContainsString('<td>Login</td>', $html);
    }

    // AK4
    public function test_leerer_text_ergibt_leere_ausgabe(): void
    {
        $this->assertSame('', trim($this->render('')));
    }
}
