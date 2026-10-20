<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Tests;

use ITHilbert\Alerts\Domain\Meldung;
use ITHilbert\ConsentLaw\Console\ConsentLawReloadCommand;
use RuntimeException;

/**
 * AK1–AK7 der Anbindung an das Basis-Package alerts (ersetzt die eigene Störungsmail).
 *
 * VERTRAG (neu):
 *  - Ein Listener des Packages hängt IMMER an den drei Störungs-Events (kein Abschalter mehr)
 *    und meldet sie über den Port ITHilbert\Alerts\Contracts\AlertReporter (per DI).
 *  - Meldung (ITHilbert\Alerts\Domain\Meldung):
 *      schluessel: 'consentlaw.' + Stoerungsfall-Wert, also
 *                  consentlaw.nicht-lieferbar | consentlaw.token-vermutlich-ungueltig | consentlaw.notstand-veraltet
 *      titel:      Stoerungsfall::bezeichnung()
 *      fehlertext: Fehlertext des Events, Token maskiert (TokenMaskierung); beim Token-Fall null
 *      details:    'Website' => app.url, 'Typ' => typ, 'Sprache' => locale (nur wenn gesetzt),
 *                  'Alter' => '<n> Tage' (nur Notstand), 'Was ist passiert' => beschreibung(),
 *                  'Was ist zu tun' => massnahmen() als ein Text. Token-Fall: ohne Typ/Sprache.
 *  - Keine eigene Sperre/Drosselung mehr: jedes Event → eine Meldung (Drosselung macht alerts
 *    über den festen Schlüssel je Fall).
 *  - Wirft der Reporter (darf laut Vertrag nicht passieren, aber Rechtstexte gehen vor), wird das
 *    als Warnung geloggt (Token maskiert); Seitenaufruf und consentlaw:reload verhalten sich unverändert.
 *  - Entfällt: Config-Block consentlaw.alerts, View consentlaw::mail.stoerung,
 *    Klassen Benachrichtigung\MeldeStoerungPerMail und Benachrichtigung\StoerungsMail.
 */
final class StoerungsAlertsTest extends AppTestCase
{
    private const EVENTS = 'ITHilbert\ConsentLaw\Events\\';

    /** @param array<string, mixed> $args */
    private function neu(string $name, array $args = []): object
    {
        $klasse = self::EVENTS.$name;

        return new $klasse(...$args);
    }

    private function nichtLieferbar(string $typ = 'impressum', string $fehler = 'cURL error 28: Timeout'): object
    {
        return $this->neu('RechtstextNichtLieferbar', ['typ' => $typ, 'locale' => 'de', 'fehler' => $fehler]);
    }

    private function notstand(string $typ = 'impressum', int $alter = 10 * 86400, string $fehler = 'HTTP 503'): object
    {
        return $this->neu('NotstandVeraltet', ['typ' => $typ, 'locale' => 'de', 'alterInSekunden' => $alter, 'fehler' => $fehler]);
    }

    private function melde(object $event): void
    {
        $this->app['events']->dispatch($event);
    }

    /** @return list<Meldung> */
    private function meldungen(): array
    {
        return $this->alerts->meldungen;
    }

    private function einzige(): Meldung
    {
        $this->assertCount(1, $this->meldungen(), 'Erwartet: genau eine Meldung an alerts.');

        return $this->meldungen()[0];
    }

    private function alsText(Meldung $meldung): string
    {
        return $meldung->titel."\n".$meldung->fehlertext."\n".json_encode($meldung->details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    // ── AK6: Rückbau ─────────────────────────────────────────────────────────

    public function test_eigene_mail_infrastruktur_entfaellt(): void
    {
        $this->assertNull($this->app['config']->get('consentlaw.alerts'), 'Config-Block consentlaw.alerts entfällt.');
        $this->assertFalse(view()->exists('consentlaw::mail.stoerung'), 'Mail-Vorlage entfällt.');
        $this->assertFalse(class_exists('ITHilbert\ConsentLaw\Benachrichtigung\MeldeStoerungPerMail'));
        $this->assertFalse(class_exists('ITHilbert\ConsentLaw\Benachrichtigung\StoerungsMail'));
    }

    public function test_alter_abschalter_ist_wirkungslos(): void
    {
        $this->setzeUmgebung('CONSENTLAW_ALERT_LISTENER', 'false');
        $this->appNeuBauen('testing');

        $this->melde($this->nichtLieferbar());

        $this->einzige();
    }

    // ── AK1: Inhalt der Meldung ──────────────────────────────────────────────

    public function test_nicht_lieferbar_wird_gemeldet(): void
    {
        $this->melde($this->nichtLieferbar('impressum', 'cURL error 28: Operation timed out'));

        $meldung = $this->einzige();
        $this->assertSame('consentlaw.nicht-lieferbar', $meldung->schluessel);
        $this->assertSame('Rechtstext nicht lieferbar', $meldung->titel);
        $this->assertSame('cURL error 28: Operation timed out', $meldung->fehlertext);
        $this->assertSame('https://kunde.example', $meldung->details['Website'] ?? null);
        $this->assertSame('impressum', $meldung->details['Typ'] ?? null);
        $this->assertSame('de', $meldung->details['Sprache'] ?? null);
        $this->assertStringContainsString('kein gespeicherter Stand', (string) ($meldung->details['Was ist passiert'] ?? ''));
        $this->assertStringContainsString('consentlaw:reload', (string) ($meldung->details['Was ist zu tun'] ?? ''));
        $this->assertArrayNotHasKey('Alter', $meldung->details);
    }

    public function test_ohne_locale_keine_sprache(): void
    {
        $this->melde($this->neu('RechtstextNichtLieferbar', ['typ' => 'agb', 'locale' => null, 'fehler' => 'HTTP 500']));

        $this->assertArrayNotHasKey('Sprache', $this->einzige()->details);
    }

    public function test_notstand_veraltet_wird_mit_alter_gemeldet(): void
    {
        $this->melde($this->notstand('datenschutz', 10 * 86400, 'HTTP 503'));

        $meldung = $this->einzige();
        $this->assertSame('consentlaw.notstand-veraltet', $meldung->schluessel);
        $this->assertSame('Veralteter Notstand', $meldung->titel);
        $this->assertSame('HTTP 503', $meldung->fehlertext);
        $this->assertSame('datenschutz', $meldung->details['Typ'] ?? null);
        $this->assertSame('10 Tage', $meldung->details['Alter'] ?? null);
    }

    public function test_token_vermutlich_ungueltig_wird_ohne_typ_gemeldet(): void
    {
        $this->api(['*' => 404]);

        $this->fuehreAus(ConsentLawReloadCommand::class);

        $meldung = $this->einzige();
        $this->assertSame('consentlaw.token-vermutlich-ungueltig', $meldung->schluessel);
        $this->assertSame('Token vermutlich ungültig', $meldung->titel);
        $this->assertNull($meldung->fehlertext);
        $this->assertSame('https://kunde.example', $meldung->details['Website'] ?? null);
        $this->assertArrayNotHasKey('Typ', $meldung->details);
        $this->assertStringContainsString('CONSENTLAW_TOKEN', (string) ($meldung->details['Was ist zu tun'] ?? ''));
    }

    // ── AK2: echter Ablauf ───────────────────────────────────────────────────

    public function test_seitenaufruf_mit_stoerung_ohne_fallback_meldet(): void
    {
        $this->api(['*' => self::STOERUNG]);

        $this->rendere('<x-consentlaw::impressum />');

        $meldung = $this->einzige();
        $this->assertSame('consentlaw.nicht-lieferbar', $meldung->schluessel);
        $this->assertSame('impressum', $meldung->details['Typ'] ?? null);
    }

    public function test_veralteter_fallback_im_echten_ablauf_meldet_das_alter(): void
    {
        $this->api(['*' => $this->dokumentPayload('# Impressum alt')]);
        $this->client()->impressum()->get();
        $this->reise(10 * 86400);

        $this->api(['*' => 503]);
        $this->client()->impressum()->get();

        $meldung = $this->einzige();
        $this->assertSame('consentlaw.notstand-veraltet', $meldung->schluessel);
        $this->assertSame('10 Tage', $meldung->details['Alter'] ?? null);
    }

    // ── AK3: Token-Schutz ────────────────────────────────────────────────────

    public function test_token_steht_nie_in_der_meldung(): void
    {
        $this->melde($this->nichtLieferbar('impressum', 'cURL error 28 for https://www.consentlaw.eu/api/impressum/'.self::TOKEN.'?locale=de'));

        $text = $this->alsText($this->einzige());
        $this->assertStringNotContainsString(self::TOKEN, $text);
        $this->assertStringNotContainsString(rawurlencode(self::TOKEN), $text);
    }

    public function test_token_aus_dem_echten_ablauf_steht_nicht_in_der_meldung(): void
    {
        $this->api(['*' => self::STOERUNG]); // Meldung des Fake-Fehlers nennt die URL samt Token.

        $this->rendere('<x-consentlaw::impressum />');

        $this->assertStringNotContainsString(self::TOKEN, $this->alsText($this->einzige()));
    }

    // ── AK4: Drosselung liegt bei alerts ─────────────────────────────────────

    public function test_jedes_event_wird_gemeldet_die_drosselung_macht_alerts(): void
    {
        $this->melde($this->nichtLieferbar('impressum'));
        $this->melde($this->nichtLieferbar('agb'));
        $this->melde($this->nichtLieferbar('impressum'));

        $this->assertCount(3, $this->meldungen());
        $this->assertSame(
            ['consentlaw.nicht-lieferbar'],
            array_values(array_unique(array_map(static fn (Meldung $m): string => $m->schluessel, $this->meldungen()))),
            'Ein fester Schlüssel je Fall – unabhängig vom Typ.',
        );
    }

    public function test_verschiedene_faelle_haben_verschiedene_schluessel(): void
    {
        $this->melde($this->nichtLieferbar());
        $this->melde($this->notstand());
        $this->melde($this->neu('TokenVermutlichUngueltig'));

        $schluessel = array_map(static fn (Meldung $m): string => $m->schluessel, $this->meldungen());
        $this->assertCount(3, array_unique($schluessel));
    }

    // ── AK5: Seite bleibt heil ───────────────────────────────────────────────

    private function reporterKaputt(): void
    {
        $this->alerts->wirft = new RuntimeException('Reporter kaputt für '.self::TOKEN);
    }

    public function test_kaputter_reporter_laesst_den_seitenaufruf_heil(): void
    {
        $this->reporterKaputt();
        $events = $this->fangeEvents();
        $this->api(['*' => self::STOERUNG]);

        $html = $this->rendere('<x-consentlaw::impressum />');

        $this->assertIsString($html, 'Kein 500: die Komponente rendert trotz Reporter-Fehler.');
        $this->assertCount(1, $events, 'Weitere Listener laufen weiter.');
    }

    public function test_kaputter_reporter_liefert_weiter_den_fallback_aus(): void
    {
        $this->reporterKaputt();
        $this->api(['*' => $this->dokumentPayload('# Impressum alt')]);
        $this->client()->impressum()->get();
        $this->reise(10 * 86400);

        $this->api(['*' => self::STOERUNG]);
        $dokument = $this->client()->impressum()->get();

        $this->assertNotNull($dokument);
        $this->assertSame('# Impressum alt', $dokument->content);
    }

    public function test_kaputter_reporter_wird_als_warnung_ohne_token_geloggt(): void
    {
        $this->reporterKaputt();

        $this->melde($this->nichtLieferbar());

        $this->assertMatchesRegularExpression('/^WARNING .*Reporter kaputt/m', $this->logText());
        $this->assertStringNotContainsString(self::TOKEN, $this->logText());
    }

    public function test_reload_mit_kaputtem_reporter_hat_denselben_exit_code_token_fall(): void
    {
        $this->api(['*' => 404]);
        [$heil, $ausgabeHeil] = $this->fuehreAus(ConsentLawReloadCommand::class);

        $this->reporterKaputt();
        [$kaputt, $ausgabeKaputt] = $this->fuehreAus(ConsentLawReloadCommand::class);

        $this->assertSame(1, $heil);
        $this->assertSame($heil, $kaputt);
        $this->assertSame($ausgabeHeil, $ausgabeKaputt, 'Die Ausgabe bleibt unverändert.');
    }

    public function test_reload_mit_kaputtem_reporter_hat_denselben_exit_code_bei_stoerung(): void
    {
        $this->api(['*' => self::STOERUNG]);
        [$heil] = $this->fuehreAus(ConsentLawReloadCommand::class);

        $this->reporterKaputt();
        [$kaputt] = $this->fuehreAus(ConsentLawReloadCommand::class);

        $this->assertSame(1, $heil);
        $this->assertSame($heil, $kaputt);
    }

    public function test_reload_mit_veraltetem_fallback_bleibt_trotz_reporter_fehler_success(): void
    {
        $this->reporterKaputt();
        $this->api(['/api/jobs/' => $this->jobsPayload(), '*' => $this->dokumentPayload('# Inhalt')]);
        $this->fuehreAus(ConsentLawReloadCommand::class);
        $this->reise(10 * 86400);

        $this->api(['*' => self::STOERUNG]);
        [$code, $ausgabe] = $this->fuehreAus(ConsentLawReloadCommand::class);

        $this->assertSame(0, $code, $ausgabe);
    }
}
