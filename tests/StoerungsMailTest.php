<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Tests;

use ITHilbert\ConsentLaw\ConsentLawServiceProvider;
use ITHilbert\ConsentLaw\Console\ConsentLawReloadCommand;

/**
 * AK4–AK9 der Störungsmeldung – Mail, Sperre, Vorlage, Abschalter, Ausfallsicherheit.
 *
 * VERTRAG (neu, noch nicht vorhanden):
 *  - Config `consentlaw.alerts`: `mail` (env CONSENTLAW_ALERT_MAIL, kommagetrennt, Standard leer/null),
 *    `mailer` (env CONSENTLAW_ALERT_MAILER, Standard null = App-Standard),
 *    `mail_listener` (env CONSENTLAW_ALERT_LISTENER, Standard true).
 *  - Der Listener wird beim Booten des Providers registriert (nur wenn `mail_listener` true);
 *    die Werte der Config gelten also ab dem Boot der App.
 *  - Versand über das Laravel-Mail-System. Die Tests prüfen NUR am Transport (Array-Transport
 *    des Mailers) – ob Mailable oder Notification dahintersteckt, ist offen. Wer die Mail
 *    verschickt, darf sie nicht queuen (die Test-App hat keine Queue).
 *  - Die Mail wird aus der Blade-View `consentlaw::mail.stoerung` gerendert
 *    (`src/Resources/views/mail/stoerung.blade.php`, publishbar über das Tag `consentlaw-views`).
 *  - Inhalt (Betreff, HTML- oder Text-Teil): App-URL (`app.url`), Typ, Fehlertext,
 *    Zeitpunkt (`d.m.Y` oder `Y-m-d`); bei NotstandVeraltet das Alter in ganzen Tagen
 *    als „<n> Tage“ (z. B. „10 Tage“). Der Token (auch URL-kodiert) steht nie im Klartext.
 *  - Sperre: höchstens eine Mail je Fall (RechtstextNichtLieferbar, TokenVermutlichUngueltig,
 *    NotstandVeraltet) pro 24 h, über den Cache (Zeit via Carbon::setTestNow).
 *  - Schlägt der Versand fehl, wird die Exception (mit ihrer Meldung) geloggt; Seitenaufruf
 *    und `consentlaw:reload` verhalten sich wie ohne Mail. Andere Listener laufen weiter.
 *  - Konstruktor der Events (benannte Parameter): RechtstextNichtLieferbar(typ, locale, fehler),
 *    NotstandVeraltet(typ, locale, alterInSekunden, fehler), TokenVermutlichUngueltig().
 */
final class StoerungsMailTest extends AppTestCase
{
    private const EVENTS = 'ITHilbert\ConsentLaw\Events\\';

    private const ADRESSE = 'chef@kunde.example';

    /** @param array<string, mixed> $args */
    private function neu(string $name, array $args = []): object
    {
        $klasse = self::EVENTS.$name;
        $this->assertTrue(class_exists($klasse), "Event {$klasse} fehlt.");

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

    private function tokenUngueltig(): object
    {
        return $this->neu('TokenVermutlichUngueltig');
    }

    private function melde(object $event): void
    {
        $this->app['events']->dispatch($event);
    }

    // ── Config (Grundlage AK4/AK5/AK8) ───────────────────────────────────────

    // AK5 / AK8
    public function test_standardwerte_der_alerts_config(): void
    {
        $alerts = $this->app['config']->get('consentlaw.alerts');

        $this->assertIsArray($alerts, 'Config-Block consentlaw.alerts fehlt.');
        $this->assertContains($alerts['mail'] ?? null, [null, ''], 'Ohne Adresse keine Mail.');
        $this->assertArrayHasKey('mailer', $alerts);
        $this->assertNull($alerts['mailer']);
        $this->assertTrue($alerts['mail_listener'] ?? null);
    }

    // AK4
    public function test_alerts_config_liest_die_umgebungsvariablen(): void
    {
        $this->setzeUmgebung('CONSENTLAW_ALERT_MAIL', 'a@kunde.example,b@kunde.example');
        $this->setzeUmgebung('CONSENTLAW_ALERT_MAILER', 'zweit');
        $this->setzeUmgebung('CONSENTLAW_ALERT_LISTENER', 'false');

        $this->appNeuBauen('testing');

        $this->assertSame('a@kunde.example,b@kunde.example', $this->app['config']->get('consentlaw.alerts.mail'));
        $this->assertSame('zweit', $this->app['config']->get('consentlaw.alerts.mailer'));
        $this->assertFalse($this->app['config']->get('consentlaw.alerts.mail_listener'));
    }

    // ── AK4 ──────────────────────────────────────────────────────────────────

    // AK4
    public function test_mail_geht_an_die_adresse_mit_url_typ_und_fehlertext(): void
    {
        $this->mitAlerts(['mail' => self::ADRESSE]);

        $this->melde($this->nichtLieferbar('impressum', 'cURL error 28: Operation timed out'));

        $mails = $this->gesendeteMails();
        $this->assertCount(1, $mails);
        $this->assertSame([self::ADRESSE], $this->empfaenger($mails[0]));
        $text = $this->mailText($mails[0]);
        $this->assertStringContainsString('https://kunde.example', $text);
        $this->assertStringContainsString('impressum', $text);
        $this->assertStringContainsString('cURL error 28: Operation timed out', $text);
        $this->assertMatchesRegularExpression('/16\.10\.2026|2026-10-16/', $text, 'Zeitpunkt fehlt.');
    }

    // AK4
    public function test_mail_geht_an_alle_kommagetrennten_adressen(): void
    {
        $this->mitAlerts(['mail' => 'a@kunde.example, b@kunde.example']);

        $this->melde($this->nichtLieferbar());

        $mails = $this->gesendeteMails();
        $empfaenger = array_merge(...array_map(fn ($mail): array => $this->empfaenger($mail), $mails));
        sort($empfaenger);
        $this->assertSame(['a@kunde.example', 'b@kunde.example'], array_values(array_unique($empfaenger)));
    }

    // AK4
    public function test_mail_bei_notstand_nennt_das_alter(): void
    {
        $this->mitAlerts(['mail' => self::ADRESSE]);

        $this->melde($this->notstand('datenschutz', 10 * 86400, 'HTTP 503'));

        $mails = $this->gesendeteMails();
        $this->assertCount(1, $mails);
        $text = $this->mailText($mails[0]);
        $this->assertMatchesRegularExpression('/\b10\s+Tage/u', $text);
        $this->assertStringContainsString('datenschutz', $text);
        $this->assertStringContainsString('HTTP 503', $text);
        $this->assertStringContainsString('https://kunde.example', $text);
    }

    // AK4
    public function test_mail_bei_token_vermutlich_ungueltig(): void
    {
        $this->mitAlerts(['mail' => self::ADRESSE]);
        $this->api(['*' => 404]);

        $this->fuehreAus(ConsentLawReloadCommand::class);

        $mails = $this->gesendeteMails();
        $this->assertCount(1, $mails, 'Genau eine Mail für den Fall „Token vermutlich ungültig“.');
        $this->assertSame([self::ADRESSE], $this->empfaenger($mails[0]));
        $this->assertStringContainsString('https://kunde.example', $this->mailText($mails[0]));
    }

    // AK4
    public function test_mail_beim_seitenaufruf_mit_stoerung_ohne_fallback(): void
    {
        $this->mitAlerts(['mail' => self::ADRESSE]);
        $this->api(['*' => self::STOERUNG]);

        $this->rendere('<x-consentlaw::impressum />');

        $mails = $this->gesendeteMails();
        $this->assertCount(1, $mails);
        $this->assertStringContainsString('impressum', $this->mailText($mails[0]));
    }

    // AK4
    public function test_mail_beim_seitenaufruf_mit_veraltetem_fallback_nennt_das_alter(): void
    {
        $this->mitAlerts(['mail' => self::ADRESSE]);
        $this->api(['*' => $this->dokumentPayload('# Impressum alt')]);
        $this->client()->impressum()->get();
        $this->reise(10 * 86400);

        $this->api(['*' => 503]);
        $this->client()->impressum()->get();

        $mails = $this->gesendeteMails();
        $this->assertCount(1, $mails);
        $this->assertMatchesRegularExpression('/\b10\s+Tage/u', $this->mailText($mails[0]));
    }

    // AK4
    public function test_token_steht_nie_im_klartext_in_der_mail(): void
    {
        $this->mitAlerts(['mail' => self::ADRESSE]);

        $this->melde($this->nichtLieferbar('impressum', 'cURL error 28 for https://www.consentlaw.eu/api/impressum/'.self::TOKEN.'?locale=de'));

        $mails = $this->gesendeteMails();
        $this->assertCount(1, $mails);
        $text = $this->mailText($mails[0]);
        $this->assertStringNotContainsString(self::TOKEN, $text);
        $this->assertStringNotContainsString(rawurlencode(self::TOKEN), $text);
    }

    // AK4
    public function test_token_aus_dem_echten_ablauf_steht_nicht_in_der_mail(): void
    {
        $this->mitAlerts(['mail' => self::ADRESSE]);
        $this->api(['*' => self::STOERUNG]); // Meldung des Fake-Fehlers nennt die URL samt Token.

        $this->rendere('<x-consentlaw::impressum />');

        $mails = $this->gesendeteMails();
        $this->assertCount(1, $mails);
        $this->assertStringNotContainsString(self::TOKEN, $this->mailText($mails[0]));
    }

    // AK4
    public function test_alerts_mailer_waehlt_den_mailer(): void
    {
        $this->mitAlerts(['mail' => self::ADRESSE, 'mailer' => 'zweit']);

        $this->melde($this->nichtLieferbar());

        $this->assertCount(1, $this->gesendeteMails('zweit'));
        $this->assertCount(0, $this->gesendeteMails('array'), 'Der App-Standard-Mailer wird dann nicht benutzt.');
    }

    // AK4
    public function test_ohne_alerts_mailer_wird_der_app_standard_benutzt(): void
    {
        $this->mitAlerts(['mail' => self::ADRESSE, 'mailer' => null]);

        $this->melde($this->nichtLieferbar());

        $this->assertCount(1, $this->gesendeteMails('array'));
        $this->assertCount(0, $this->gesendeteMails('zweit'));
    }

    // ── AK5 ──────────────────────────────────────────────────────────────────

    // AK5
    public function test_ohne_adresse_events_ja_mail_nein(): void
    {
        $this->mitAlerts(['mail' => null]);
        $events = $this->fangeEvents();
        $this->api(['*' => self::STOERUNG]);

        $this->rendere('<x-consentlaw::impressum />');
        $this->fuehreAus(ConsentLawReloadCommand::class);

        $this->assertGreaterThan(0, count($events), 'Die Events werden trotzdem ausgelöst.');
        $this->assertSame(0, $this->anzahlMails());
    }

    // AK5
    public function test_leere_adresse_versendet_nichts(): void
    {
        $this->mitAlerts(['mail' => '']);

        $this->melde($this->nichtLieferbar());
        $this->melde($this->tokenUngueltig());
        $this->melde($this->notstand());

        $this->assertSame(0, $this->anzahlMails());
    }

    // ── AK6 ──────────────────────────────────────────────────────────────────

    // AK6
    public function test_mehrfaches_ausloesen_desselben_falls_ergibt_eine_mail(): void
    {
        $this->mitAlerts(['mail' => self::ADRESSE]);

        $this->melde($this->nichtLieferbar('impressum'));
        $this->melde($this->nichtLieferbar('impressum'));
        $this->melde($this->nichtLieferbar('datenschutz'));
        $this->melde($this->nichtLieferbar('jobs'));

        $this->assertCount(1, $this->gesendeteMails(), 'Eine Mail je Fall, unabhängig vom Typ.');
    }

    // AK6
    public function test_mehrere_seitenaufrufe_und_typen_ergeben_eine_mail(): void
    {
        $this->mitAlerts(['mail' => self::ADRESSE]);
        $events = $this->fangeEvents();
        $this->api(['*' => self::STOERUNG]);

        $this->rendere('<x-consentlaw::impressum />');
        $this->rendere('<x-consentlaw::datenschutz />');
        $this->reise(301); // Sperrzeit nach der Störung vorbei: wirklich ein weiterer Abruf
        $this->rendere('<x-consentlaw::impressum />');

        $this->assertGreaterThanOrEqual(3, count($events), 'Vorbedingung: mehrere Events.');
        $this->assertCount(1, $this->gesendeteMails());
    }

    // AK6
    public function test_verschiedene_faelle_ergeben_je_eine_mail(): void
    {
        $this->mitAlerts(['mail' => self::ADRESSE]);

        $this->melde($this->nichtLieferbar());
        $this->melde($this->tokenUngueltig());
        $this->melde($this->notstand());
        $this->melde($this->notstand('agb'));
        $this->melde($this->tokenUngueltig());

        $this->assertCount(3, $this->gesendeteMails());
    }

    // AK6
    public function test_nach_24_stunden_geht_wieder_eine_mail_raus(): void
    {
        $this->mitAlerts(['mail' => self::ADRESSE]);

        $this->melde($this->nichtLieferbar());
        $this->reise(86400 - 1);
        $this->melde($this->nichtLieferbar());
        $this->assertCount(1, $this->gesendeteMails(), 'Nach 23:59:59 h gilt die Sperre noch.');

        $this->reise(2);
        $this->melde($this->nichtLieferbar());
        $this->assertCount(2, $this->gesendeteMails(), 'Nach Ablauf der 24 h geht wieder eine Mail raus.');
    }

    // AK6
    public function test_sperre_gilt_je_fall_nicht_global(): void
    {
        $this->mitAlerts(['mail' => self::ADRESSE]);

        $this->melde($this->nichtLieferbar());
        $this->reise(86400 + 1);
        $this->melde($this->tokenUngueltig());

        $this->assertCount(2, $this->gesendeteMails());
    }

    // ── AK7 ──────────────────────────────────────────────────────────────────

    // AK7
    public function test_die_mitgelieferte_vorlage_existiert(): void
    {
        $this->assertTrue($this->app['view']->exists('consentlaw::mail.stoerung'), 'View consentlaw::mail.stoerung fehlt.');
    }

    // AK7
    public function test_vorlage_ist_ueber_das_tag_consentlaw_views_publishbar(): void
    {
        $pfade = ConsentLawServiceProvider::pathsToPublish(ConsentLawServiceProvider::class, 'consentlaw-views');

        $this->assertNotSame([], $pfade, 'Tag consentlaw-views nicht registriert.');
        $enthalten = false;
        foreach (array_keys($pfade) as $quelle) {
            $enthalten = $enthalten || is_file(rtrim($quelle, '/').'/mail/stoerung.blade.php');
        }
        $this->assertTrue($enthalten, 'mail/stoerung.blade.php liegt nicht in einem veröffentlichten Views-Ordner.');
    }

    // AK7
    public function test_mail_inhalt_kommt_aus_der_view_und_eine_ueberschriebene_view_wird_benutzt(): void
    {
        $this->mitAlerts(['mail' => self::ADRESSE]);
        $ordner = $this->basis.'/ueberschrieben';
        mkdir($ordner.'/mail', 0777, true);
        file_put_contents($ordner.'/mail/stoerung.blade.php', 'EIGENE-VORLAGE-4711');
        $this->app['view']->prependNamespace('consentlaw', $ordner);

        $this->melde($this->nichtLieferbar());

        $mails = $this->gesendeteMails();
        $this->assertCount(1, $mails);
        $this->assertStringContainsString('EIGENE-VORLAGE-4711', $this->mailText($mails[0]));
    }

    // AK7
    public function test_vendor_pfad_der_app_ueberschreibt_die_vorlage(): void
    {
        $this->mitAlerts(['mail' => self::ADRESSE]);
        $ordner = $this->basis.'/views/vendor/consentlaw/mail';
        mkdir($ordner, 0777, true);
        file_put_contents($ordner.'/stoerung.blade.php', 'VENDOR-VORLAGE-0815');
        // Wie Laravels Provider-Hilfe: der App-Vendor-Pfad hat Vorrang vor dem Package-Pfad.
        $this->app['view']->prependNamespace('consentlaw', $this->basis.'/views/vendor/consentlaw');

        $this->melde($this->nichtLieferbar());

        $this->assertStringContainsString('VENDOR-VORLAGE-0815', $this->mailText($this->gesendeteMails()[0]));
    }

    // ── AK8 ──────────────────────────────────────────────────────────────────

    // AK8
    public function test_mail_listener_aus_events_ja_mail_nein(): void
    {
        $this->mitAlerts(['mail' => self::ADRESSE, 'mail_listener' => false]);
        $events = $this->fangeEvents();
        $this->api(['*' => self::STOERUNG]);

        $this->rendere('<x-consentlaw::impressum />');

        $this->assertCount(1, $events, 'Die Events werden weiter ausgelöst.');
        $this->assertSame(0, $this->anzahlMails());
    }

    // AK8
    public function test_mail_listener_aus_auch_fuer_direkt_ausgeloeste_events(): void
    {
        $this->mitAlerts(['mail' => self::ADRESSE, 'mail_listener' => false]);

        $this->melde($this->tokenUngueltig());
        $this->melde($this->notstand());

        $this->assertSame(0, $this->anzahlMails());
    }

    // AK8
    public function test_mail_listener_ueber_umgebungsvariable_abschaltbar(): void
    {
        $this->setzeUmgebung('CONSENTLAW_ALERT_MAIL', self::ADRESSE);
        $this->setzeUmgebung('CONSENTLAW_ALERT_LISTENER', 'false');
        $this->appNeuBauen('testing');

        $this->melde($this->nichtLieferbar());

        $this->assertSame(0, $this->anzahlMails());
    }

    // AK8
    public function test_mail_ueber_umgebungsvariablen_ohne_config_eingriff(): void
    {
        $this->setzeUmgebung('CONSENTLAW_ALERT_MAIL', self::ADRESSE);
        $this->appNeuBauen('testing');

        $this->melde($this->nichtLieferbar());

        $this->assertCount(1, $this->gesendeteMails());
    }

    // ── AK9 ──────────────────────────────────────────────────────────────────

    // AK9
    public function test_fehlschlagender_mailversand_laesst_den_seitenaufruf_heil(): void
    {
        $this->mitAlerts(['mail' => self::ADRESSE, 'mailer' => 'kaputt']);
        $events = $this->fangeEvents();
        $this->api(['*' => self::STOERUNG]);

        $html = $this->rendere('<x-consentlaw::impressum />');

        $this->assertIsString($html, 'Kein 500: die Komponente rendert trotz Mailfehler.');
        $this->assertCount(1, $events, 'Weitere Listener laufen weiter.');
    }

    // AK9
    public function test_fehlschlagender_mailversand_liefert_weiter_den_fallback_aus(): void
    {
        $this->mitAlerts(['mail' => self::ADRESSE, 'mailer' => 'kaputt']);
        $this->api(['*' => $this->dokumentPayload('# Impressum alt')]);
        $this->client()->impressum()->get();
        $this->reise(10 * 86400);

        $this->api(['*' => self::STOERUNG]);
        $dokument = $this->client()->impressum()->get();

        $this->assertNotNull($dokument);
        $this->assertSame('# Impressum alt', $dokument->content);
    }

    // AK9
    public function test_fehlschlagender_mailversand_wird_geloggt(): void
    {
        $this->mitAlerts(['mail' => self::ADRESSE, 'mailer' => 'kaputt']);

        $this->melde($this->nichtLieferbar());

        $this->assertStringContainsString('SMTP nicht erreichbar', $this->logText(), 'Der Mailfehler muss im Log stehen.');
    }

    // AK9
    public function test_reload_hat_mit_kaputtem_mailer_denselben_exit_code_token_fall(): void
    {
        $this->api(['*' => 404]);
        $this->mitAlerts(['mail' => null]);
        [$ohneMail, $ausgabeOhne] = $this->fuehreAus(ConsentLawReloadCommand::class);

        $this->mitAlerts(['mail' => self::ADRESSE, 'mailer' => 'kaputt']);
        [$mitKaputt, $ausgabeKaputt] = $this->fuehreAus(ConsentLawReloadCommand::class);

        $this->assertSame(1, $ohneMail);
        $this->assertSame($ohneMail, $mitKaputt);
        $this->assertSame($ausgabeOhne, $ausgabeKaputt, 'Die Ausgabe bleibt unverändert.');
        $this->assertStringContainsString('SMTP nicht erreichbar', $this->logText());
    }

    // AK9
    public function test_reload_hat_mit_kaputtem_mailer_denselben_exit_code_bei_stoerung(): void
    {
        $this->api(['*' => self::STOERUNG]);
        $this->mitAlerts(['mail' => null]);
        [$ohneMail] = $this->fuehreAus(ConsentLawReloadCommand::class);

        $this->mitAlerts(['mail' => self::ADRESSE, 'mailer' => 'kaputt']);
        [$mitKaputt] = $this->fuehreAus(ConsentLawReloadCommand::class);

        $this->assertSame(1, $ohneMail);
        $this->assertSame($ohneMail, $mitKaputt);
    }

    // AK9
    public function test_reload_mit_veraltetem_fallback_bleibt_trotz_mailfehler_success(): void
    {
        $this->mitAlerts(['mail' => self::ADRESSE, 'mailer' => 'kaputt']);
        $this->api(['/api/jobs/' => $this->jobsPayload(), '*' => $this->dokumentPayload('# Inhalt')]);
        $this->fuehreAus(ConsentLawReloadCommand::class);
        $this->reise(10 * 86400);

        $this->api(['*' => self::STOERUNG]);
        [$code, $ausgabe] = $this->fuehreAus(ConsentLawReloadCommand::class);

        $this->assertSame(0, $code, $ausgabe);
        $this->assertStringContainsString('SMTP nicht erreichbar', $this->logText());
    }
}
