<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Tests;

use ITHilbert\ConsentLaw\Console\ConsentLawWarmCommand;

/**
 * AK9 – Ein ungültiger Token fällt bei `consentlaw:warm` auf: Antworten ALLE
 * Typen (5 Dokumente + Jobs) mit 404, endet der Befehl mit FAILURE und einem
 * Hinweis, den Token zu prüfen. Einzelne 404 bleiben ein Hinweis (AK7).
 */
final class WarmTokenHinweisTest extends AppTestCase
{
    // AK9
    public function test_wenn_alle_typen_404_liefern_endet_der_befehl_mit_failure(): void
    {
        $this->api(['*' => 404]);

        [$code, $ausgabe] = $this->fuehreAus(ConsentLawWarmCommand::class);

        $this->assertSame(1, $code, $ausgabe);
    }

    // AK9
    public function test_wenn_alle_typen_404_liefern_nennt_die_ausgabe_den_token(): void
    {
        $this->api(['*' => 404]);

        [, $ausgabe] = $this->fuehreAus(ConsentLawWarmCommand::class);

        $this->assertStringContainsString('Token', $ausgabe, "Hinweis auf den Token fehlt.\n{$ausgabe}");
    }

    // AK9
    public function test_die_einzelnen_typen_werden_bei_komplettem_404_weiterhin_genannt(): void
    {
        $this->api(['*' => 404]);

        [, $ausgabe] = $this->fuehreAus(ConsentLawWarmCommand::class);

        foreach (['impressum', 'datenschutz', 'agb', 'jobs'] as $typ) {
            $this->assertMatchesRegularExpression("/{$typ}.*nicht veröffentlicht/u", $ausgabe, "{$typ} fehlt.\n{$ausgabe}");
        }
    }

    // AK9 / AK7
    public function test_ein_einzelner_404_endet_mit_success_ohne_token_hinweis(): void
    {
        $this->api(['/api/agb/' => 404, '/api/jobs/' => $this->jobsPayload(), '*' => $this->dokumentPayload('# Inhalt')]);

        [$code, $ausgabe] = $this->fuehreAus(ConsentLawWarmCommand::class);

        $this->assertSame(0, $code, $ausgabe);
        $this->assertStringNotContainsString('Token', $ausgabe);
    }

    // AK9 / AK7
    public function test_alle_dokumente_404_aber_jobs_veroeffentlicht_endet_mit_success(): void
    {
        $this->api(['/api/jobs/' => $this->jobsPayload(), '*' => 404]);

        [$code, $ausgabe] = $this->fuehreAus(ConsentLawWarmCommand::class);

        $this->assertSame(0, $code, "Jobs sind veröffentlicht – nicht ALLE Typen sind 404.\n{$ausgabe}");
        $this->assertStringNotContainsString('Token', $ausgabe);
    }

    // AK9 / AK7
    public function test_alle_dokumente_veroeffentlicht_aber_jobs_404_endet_mit_success(): void
    {
        $this->api(['/api/jobs/' => 404, '*' => $this->dokumentPayload('# Inhalt')]);

        [$code, $ausgabe] = $this->fuehreAus(ConsentLawWarmCommand::class);

        $this->assertSame(0, $code, $ausgabe);
        $this->assertStringNotContainsString('Token', $ausgabe);
    }

    // AK9 / AK7
    public function test_alle_dokumente_bis_auf_eines_und_jobs_404_endet_mit_success(): void
    {
        $this->api(['/api/jobs/' => 404, '/api/impressum/' => $this->dokumentPayload('# Impressum'), '*' => 404]);

        [$code, $ausgabe] = $this->fuehreAus(ConsentLawWarmCommand::class);

        $this->assertSame(0, $code, $ausgabe);
    }

    // AK9 (Abgrenzung): Störungen sind kein Token-Problem.
    public function test_stoerungen_bei_allen_typen_nennen_nicht_den_token(): void
    {
        $this->api(['*' => self::STOERUNG]);

        [$code, $ausgabe] = $this->fuehreAus(ConsentLawWarmCommand::class);

        $this->assertSame(1, $code, $ausgabe);
        $this->assertStringNotContainsString('Token', $ausgabe, 'Eine Störung ist kein Hinweis auf einen ungültigen Token.');
    }
}
