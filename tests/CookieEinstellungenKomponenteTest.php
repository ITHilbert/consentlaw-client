<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Tests;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Feature banner-komponente, Aufgabe T2: `<x-consentlaw::cookie-einstellungen />` (AK5, AK6, AK8, Randfall 3).
 *
 * AK8: Das Element ist ein <button type="button"> ohne href (kein <a href="#">, kein javascript:).
 * AK5 gilt für die Schaltfläche: Text, Slot, Attribute, Aufruf von window.CookieScript.open()
 * nur, wenn window.CookieScript existiert.
 */
final class CookieEinstellungenKomponenteTest extends AppTestCase
{
    private const LINK = '<x-consentlaw::cookie-einstellungen />';

    private function aktiv(): void
    {
        $this->konfig('token', 'abc123');
        $this->konfig('banner.enabled', true);
    }

    /** Die Schaltfläche samt Inhalt. */
    private function element(string $html): string
    {
        $this->assertSame(1, preg_match('/<button\b[^>]*>.*?<\/button>/s', $html, $m), 'Ein <button> in der Ausgabe.');

        return $m[0];
    }

    /** Starttag der Schaltfläche. */
    private function starttag(string $html): string
    {
        preg_match('/<button\b[^>]*>/', $html, $m);

        return $m[0] ?? '';
    }

    /** @return array<string, array{mixed}> */
    public static function tokenLeer(): array
    {
        return ['nicht gesetzt (null)' => [null], 'leer gesetzt' => ['']];
    }

    // AK5
    public function test_rendert_link_mit_text_cookie_einstellungen(): void
    {
        $this->aktiv();

        $element = $this->element($this->rendere(self::LINK));

        $this->assertSame('Cookie-Einstellungen', trim(strip_tags($element)));
    }

    // AK5
    public function test_klick_ruft_window_cookiescript_open_auf(): void
    {
        $this->aktiv();

        $html = $this->rendere(self::LINK);

        $this->assertStringContainsString('window.CookieScript.open()', $html);
    }

    // AK5, Randfall 3
    public function test_aufruf_ist_gegen_fehlendes_window_cookiescript_abgesichert(): void
    {
        $this->aktiv();

        $html = $this->rendere(self::LINK);

        $this->assertSame(1, preg_match(
            '/(window\.CookieScript\s*&&\s*window\.CookieScript\.open\(\)|if\s*\(\s*window\.CookieScript\s*\)\s*\{?\s*window\.CookieScript\.open\(\)|window\.CookieScript\s*\?\.\s*open\(\)|window\.CookieScript\s*!==?\s*undefined\s*(&&|\))\s*\{?\s*window\.CookieScript\.open\(\)|typeof\s+window\.CookieScript\s*!==?\s*[\'"]undefined[\'"]\s*(&&|\))\s*\{?\s*window\.CookieScript\.open\(\))/',
            $html,
        ), 'open() nur aufrufen, wenn window.CookieScript existiert (kein Aufruf ohne Prüfung).');
        $this->assertStringNotContainsString('window.CookieScript.open()', preg_replace('/window\.CookieScript\s*&&\s*window\.CookieScript\.open\(\)|if\s*\(\s*window\.CookieScript[^)]*\)\s*\{?\s*window\.CookieScript\.open\(\)|typeof\s+window\.CookieScript[^;]*?window\.CookieScript\.open\(\)|window\.CookieScript\s*!==?\s*undefined[^;]*?window\.CookieScript\.open\(\)/', '', $html), 'Kein zweiter, ungeschützter Aufruf.');
    }

    // AK8
    public function test_element_ist_button_type_button(): void
    {
        $this->aktiv();

        $start = $this->starttag($this->rendere(self::LINK));

        $this->assertNotSame('', $start, 'Ein <button> in der Ausgabe.');
        $this->assertSame(1, preg_match('/\stype="button"/', $start), 'type="button" verhindert das Absenden umgebender Formulare.');
    }

    // AK8
    public function test_kein_link_und_kein_href_oder_javascript_ziel(): void
    {
        $this->aktiv();

        $html = $this->rendere(self::LINK);

        $this->assertSame(1, preg_match('/<button\b/', $html), 'Gegenprobe: Die Schaltfläche wird gerendert.');
        $this->assertSame(0, preg_match('/<a\b/i', $html), 'Kein <a>-Element.');
        $this->assertSame(0, preg_match('/\shref\s*=/i', $html), 'Kein href-Attribut.');
        $this->assertStringNotContainsStringIgnoringCase('javascript:', $html);
    }

    // AK8
    public function test_mit_slot_und_attributen_bleibt_es_eine_schaltflaeche_ohne_href(): void
    {
        $this->aktiv();

        $html = $this->rendere('<x-consentlaw::cookie-einstellungen class="x" data-test="c">Eigener Text</x-consentlaw::cookie-einstellungen>');
        $start = $this->starttag($html);

        $this->assertSame(1, preg_match('/\stype="button"/', $start));
        $this->assertSame(0, preg_match('/<a\b/i', $html));
        $this->assertSame(0, preg_match('/\shref\s*=/i', $html));
        $this->assertStringNotContainsStringIgnoringCase('javascript:', $html);
    }

    // AK5
    public function test_slot_ersetzt_den_text(): void
    {
        $this->aktiv();

        $element = $this->element($this->rendere('<x-consentlaw::cookie-einstellungen>Cookies verwalten</x-consentlaw::cookie-einstellungen>'));

        $this->assertSame('Cookies verwalten', trim(strip_tags($element)));
        $this->assertStringNotContainsString('Cookie-Einstellungen', $element);
    }

    // AK5
    public function test_attribute_landen_am_link(): void
    {
        $this->aktiv();

        $html = $this->rendere('<x-consentlaw::cookie-einstellungen class="footer-link text-muted" data-test="cookie-link" />');
        $start = $this->starttag($html);

        $this->assertStringContainsString('footer-link text-muted', $start);
        $this->assertSame(1, preg_match('/\sclass="[^"]*footer-link text-muted[^"]*"/', $start));
        $this->assertStringContainsString('data-test="cookie-link"', $start);
    }

    // AK6
    #[DataProvider('tokenLeer')]
    public function test_ohne_token_ist_die_ausgabe_leer(mixed $token): void
    {
        // Gegenprobe: aktiv gibt es den Link.
        $this->aktiv();
        $this->assertStringContainsString('Cookie-Einstellungen', $this->rendere(self::LINK));

        $this->konfig('token', $token);

        $this->assertSame('', trim($this->rendere(self::LINK)));
    }

    // AK6
    public function test_ausgeschaltet_ist_die_ausgabe_leer_auch_mit_token(): void
    {
        // Gegenprobe: eingeschaltet gibt es den Link.
        $this->aktiv();
        $this->assertStringContainsString('Cookie-Einstellungen', $this->rendere(self::LINK));

        $this->konfig('banner.enabled', false);

        $this->assertSame('', trim($this->rendere(self::LINK)));
    }

    // AK6
    public function test_ausgeschaltet_ist_auch_mit_slot_leer(): void
    {
        $this->aktiv();
        $this->assertStringContainsString('Eigener Text', $this->rendere('<x-consentlaw::cookie-einstellungen>Eigener Text</x-consentlaw::cookie-einstellungen>'));

        $this->konfig('banner.enabled', false);

        $this->assertSame('', trim($this->rendere('<x-consentlaw::cookie-einstellungen>Eigener Text</x-consentlaw::cookie-einstellungen>')));
    }
}
