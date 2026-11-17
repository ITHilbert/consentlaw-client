<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Tests;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Feature banner-komponente, AK9 (Bugfix #1417): Die Komponente `<x-consentlaw::cookie-einstellungen />`
 * liefert eine Stilregel mit Spezifität null mit, die die Schaltfläche wie einen Text-Link darstellt.
 */
final class CookieEinstellungenStilTest extends AppTestCase
{
    private const LINK = '<x-consentlaw::cookie-einstellungen />';

    private function aktiv(): void
    {
        $this->konfig('token', 'abc123');
        $this->konfig('banner.enabled', true);
    }

    /** Inhalt aller <style>-Blöcke der Ausgabe. */
    private function stile(string $html): string
    {
        preg_match_all('/<style\b[^>]*>(.*?)<\/style>/s', $html, $m);

        return implode("\n", $m[1]);
    }

    /** @return array<string, array{string}> */
    public static function deklarationen(): array
    {
        return [
            'background' => ['background\s*:\s*none'],
            'border' => ['border\s*:\s*0'],
            'padding' => ['padding\s*:\s*0'],
            'font' => ['font\s*:\s*inherit'],
            'color' => ['color\s*:\s*inherit'],
            'cursor' => ['cursor\s*:\s*pointer'],
        ];
    }

    // AK9
    #[DataProvider('deklarationen')]
    public function test_stilregel_enthaelt_die_text_link_deklaration(string $muster): void
    {
        $this->aktiv();

        $stil = $this->stile($this->rendere(self::LINK));

        $this->assertSame(1, preg_match('/'.$muster.'\s*(;|\})/', $stil), 'Stilregel mit Deklaration '.$muster);
    }

    // AK9
    public function test_selektor_der_regel_steht_komplett_in_where(): void
    {
        $this->aktiv();

        $stil = $this->stile($this->rendere(self::LINK));

        $this->assertSame(1, preg_match('/^\s*:where\([^{}]+\)\s*\{[^{}]*\}\s*$/s', $stil), 'Genau eine Regel, deren Selektor ganz in :where(...) steht (Spezifität null).');
    }

    // AK9
    public function test_mehrere_komponenten_liefern_die_regel_nur_einmal(): void
    {
        $this->aktiv();
        $einzeln = $this->rendere(self::LINK);
        $this->assertSame(1, preg_match_all('/<style\b/', $einzeln), 'Gegenprobe: Eine Komponente liefert ein <style>.');

        $html = $this->rendere(self::LINK.'<p>x</p>'.self::LINK.'<x-consentlaw::cookie-einstellungen>Andere</x-consentlaw::cookie-einstellungen>');

        $this->assertSame(3, preg_match_all('/<button\b/', $html), 'Drei Schaltflächen.');
        $this->assertSame(1, preg_match_all('/<style\b/', $html), 'Genau ein <style>.');
    }

    // AK9
    public function test_ausgeschaltet_ist_die_ausgabe_auch_ohne_stilregel_leer(): void
    {
        $this->aktiv();
        $this->assertNotSame('', $this->stile($this->rendere(self::LINK)), 'Gegenprobe: aktiv gibt eine Stilregel.');

        $this->konfig('banner.enabled', false);
        $html = $this->rendere(self::LINK);

        $this->assertSame('', trim($html));
        $this->assertStringNotContainsString('<style', $html);
    }
}
