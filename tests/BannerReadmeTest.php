<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Feature banner-komponente, Aufgabe T3: README (AK7).
 */
final class BannerReadmeTest extends TestCase
{
    private function readme(): string
    {
        return (string) file_get_contents(__DIR__.'/../README.md');
    }

    // AK7
    public function test_readme_beschreibt_die_banner_komponente(): void
    {
        $this->assertStringContainsString('<x-consentlaw::banner', $this->readme());
    }

    // AK7
    public function test_readme_beschreibt_die_cookie_einstellungen_komponente(): void
    {
        $this->assertStringContainsString('<x-consentlaw::cookie-einstellungen', $this->readme());
    }

    // AK7
    public function test_readme_beschreibt_den_schalter(): void
    {
        $readme = $this->readme();

        $this->assertStringContainsString('CONSENTLAW_BANNER_ENABLED', $readme);
        $this->assertStringContainsString('consentlaw.banner.enabled', $readme);
    }

    // AK7
    public function test_readme_sagt_dass_banner_js_von_consentlaw_geladen_wird(): void
    {
        $readme = $this->readme();

        $this->assertStringContainsString('banner.js', $readme);
        $this->assertSame(1, preg_match('/banner\.js[^\n]*consentLaw|consentLaw[^\n]*banner\.js/i', $readme), 'banner.js und consentLaw stehen in einem Satz/einer Zeile.');
    }
}
