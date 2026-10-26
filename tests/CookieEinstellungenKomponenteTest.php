<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Tests;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Feature banner-komponente, Aufgabe T2: `<x-consentlaw::cookie-einstellungen />` (AK5, AK6, Randfall 3).
 *
 * Das Markup des Links (<a href="#"> oder <button>) lässt die Spec frei. Geprüft wird deshalb
 * nur das Verhalten: Aufruf von window.CookieScript.open(), Absicherung gegen fehlendes
 * window.CookieScript und keine Navigation.
 */
final class CookieEinstellungenKomponenteTest extends AppTestCase
{
    private const LINK = '<x-consentlaw::cookie-einstellungen />';

    private function aktiv(): void
    {
        $this->konfig('token', 'abc123');
        $this->konfig('banner.enabled', true);
    }

    /** Das Element (a oder button) samt Inhalt. */
    private function element(string $html): string
    {
        $this->assertSame(1, preg_match('/<(a|button)\b[^>]*>.*?<\/\1>/s', $html, $m), 'Ein Link (<a>) oder <button> in der Ausgabe.');

        return $m[0];
    }

    /** Starttag des Elements. */
    private function starttag(string $html): string
    {
        preg_match('/<(?:a|button)\b[^>]*>/', $html, $m);

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

    // AK5
    public function test_klick_verhindert_navigation_und_neuladen(): void
    {
        $this->aktiv();

        $html = $this->rendere(self::LINK);
        $start = $this->starttag($html);

        $istButton = str_starts_with($start, '<button') && preg_match('/\stype="button"/', $start) === 1;
        $verhindert = preg_match('/preventDefault\s*\(\s*\)|return\s+false/', $html) === 1;

        $this->assertTrue($istButton || $verhindert, 'Entweder <button type="button"> oder preventDefault()/return false im Klick-Handler.');
        // Ein Link darf nicht auf eine echte Adresse zeigen.
        if (str_starts_with($start, '<a')) {
            $this->assertSame(1, preg_match('/\shref="(#|javascript:[^"]*)"/', $start), 'Link ohne echtes Ziel (href="#").');
            $this->assertTrue($verhindert, 'Bei einem Link verhindert der Handler das Springen zum Seitenanfang.');
        }
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
