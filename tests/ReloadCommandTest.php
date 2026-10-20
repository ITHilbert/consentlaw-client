<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Tests;

use ITHilbert\ConsentLaw\Console\ConsentLawReloadCommand;

/**
 * AK7 – `consentlaw:reload` scheitert nur bei echten Problemen.
 *
 * VERTRAG (Ausgabe, neu):
 *  - Typ nicht veröffentlicht (404): die Ausgabezeile nennt den Typ und den Text
 *    „nicht veröffentlicht“ (Hinweis); Exit-Code bleibt SUCCESS (0).
 *  - Typ wegen Störung weder von der API noch aus dem Fallback lieferbar: die
 *    Ausgabezeile nennt den Typ und das Wort „Störung“ (und NICHT „nicht
 *    veröffentlicht“); Exit-Code FAILURE (1).
 *  - Störung, aber Fallback vorhanden: SUCCESS (Inhalt wurde geliefert).
 *  - Kein Token: FAILURE.
 *
 * Der Command läuft über die echte Provider-Verdrahtung mit Fake-HTTP; Exit-Code
 * und Ausgabe kommen aus Command::run() mit BufferedOutput (AppTestCase::fuehreAus()).
 */
final class ReloadCommandTest extends AppTestCase
{
    /** Alle Typen veröffentlicht; $abweichungen überschreiben einzelne Typen. */
    private function alleVeroeffentlicht(array $abweichungen = []): array
    {
        return [...$abweichungen, '/api/jobs/' => $this->jobsPayload(), '*' => $this->dokumentPayload('# Inhalt')];
    }

    // AK7 (bestehend, absichern)
    public function test_alles_veroeffentlicht_endet_mit_success(): void
    {
        $this->api($this->alleVeroeffentlicht());

        [$code, $ausgabe] = $this->fuehreAus(ConsentLawReloadCommand::class);

        $this->assertSame(0, $code, $ausgabe);
        $this->assertCount(6, $this->fallbackDateien(), 'Fallback-Dateien für 5 Dokumente und die Jobs erwartet.');
    }

    // AK7
    public function test_nicht_veroeffentlichter_typ_endet_mit_success(): void
    {
        $this->api($this->alleVeroeffentlicht(['/api/agb/' => 404]));

        [$code, $ausgabe] = $this->fuehreAus(ConsentLawReloadCommand::class);

        $this->assertSame(0, $code, "404 = nicht veröffentlicht darf den Befehl nicht scheitern lassen.\n{$ausgabe}");
    }

    // AK7
    public function test_nicht_veroeffentlichter_typ_wird_als_hinweis_gemeldet(): void
    {
        $this->api($this->alleVeroeffentlicht(['/api/agb/' => 404]));

        [, $ausgabe] = $this->fuehreAus(ConsentLawReloadCommand::class);

        $this->assertMatchesRegularExpression('/agb.*nicht veröffentlicht/u', $ausgabe, "Hinweis zu agb fehlt.\n{$ausgabe}");
        $this->assertDoesNotMatchRegularExpression('/impressum.*nicht veröffentlicht/u', $ausgabe, 'Veröffentlichte Typen dürfen nicht als „nicht veröffentlicht“ gemeldet werden.');
    }

    // AK7
    public function test_stoerung_ohne_fallback_endet_mit_failure(): void
    {
        $this->api($this->alleVeroeffentlicht(['/api/avv/' => self::STOERUNG]));

        [$code, $ausgabe] = $this->fuehreAus(ConsentLawReloadCommand::class);

        $this->assertSame(1, $code, $ausgabe);
    }

    // AK7
    public function test_stoerung_wird_von_nicht_veroeffentlicht_unterschieden(): void
    {
        $this->api($this->alleVeroeffentlicht(['/api/avv/' => self::STOERUNG]));

        [, $ausgabe] = $this->fuehreAus(ConsentLawReloadCommand::class);

        $this->assertMatchesRegularExpression('/avv.*Störung/u', $ausgabe, "Die Störung bei avv muss als Störung gemeldet werden.\n{$ausgabe}");
        $this->assertDoesNotMatchRegularExpression('/avv.*nicht veröffentlicht/u', $ausgabe, 'Eine Störung ist nicht „nicht veröffentlicht“.');
    }

    // AK7
    public function test_fehlerstatus_429_ohne_fallback_gilt_als_stoerung(): void
    {
        $this->api($this->alleVeroeffentlicht(['/api/avv/' => 429]));

        [$code, $ausgabe] = $this->fuehreAus(ConsentLawReloadCommand::class);

        $this->assertSame(1, $code, $ausgabe);
        $this->assertMatchesRegularExpression('/avv.*Störung/u', $ausgabe);
    }

    // AK7
    public function test_stoerung_und_nicht_veroeffentlicht_zusammen_enden_mit_failure_und_nennen_beides(): void
    {
        $this->api($this->alleVeroeffentlicht(['/api/agb/' => 404, '/api/avv/' => self::STOERUNG]));

        [$code, $ausgabe] = $this->fuehreAus(ConsentLawReloadCommand::class);

        $this->assertSame(1, $code, $ausgabe);
        $this->assertMatchesRegularExpression('/agb.*nicht veröffentlicht/u', $ausgabe);
        $this->assertMatchesRegularExpression('/avv.*Störung/u', $ausgabe);
    }

    // AK7
    public function test_stoerung_mit_vorhandenem_fallback_endet_mit_success(): void
    {
        $this->api($this->alleVeroeffentlicht());
        [$erster] = $this->fuehreAus(ConsentLawReloadCommand::class);
        $this->assertSame(0, $erster, 'Vorbedingung: erster Lauf legt die Fallback-Dateien an.');

        $this->api($this->alleVeroeffentlicht(['/api/avv/' => self::STOERUNG]));
        [$code, $ausgabe] = $this->fuehreAus(ConsentLawReloadCommand::class);

        $this->assertSame(0, $code, "Mit Fallback wurde der Inhalt geliefert – kein Fehler.\n{$ausgabe}");
        $this->assertStringContainsString('Fallback', $ausgabe);
    }

    // AK7
    public function test_ohne_token_endet_mit_failure(): void
    {
        $this->konfig('token', null);

        [$code] = $this->fuehreAus(ConsentLawReloadCommand::class);

        $this->assertSame(1, $code);
        $this->assertSame(0, $this->anzahlAufrufe(), 'Ohne Token darf keine Anfrage rausgehen.');
    }
}
