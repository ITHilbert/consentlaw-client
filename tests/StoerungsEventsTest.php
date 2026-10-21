<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Tests;

use ITHilbert\ConsentLaw\Console\ConsentLawLoadCommand;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * AK1–AK3 der Störungsmeldung – die drei Events.
 *
 * VERTRAG (neu, noch nicht vorhanden), Namespace `ITHilbert\ConsentLaw\Events\`:
 *  - `RechtstextNichtLieferbar`  : öffentliche Properties `typ` (string, z. B. 'impressum', 'jobs'),
 *                                  `locale` (?string), `fehler` (string, nicht leer).
 *  - `TokenVermutlichUngueltig`  : keine Pflicht-Properties.
 *  - `NotstandVeraltet`          : `typ`, `locale` (?string), `alterInSekunden` (int), `fehler` (string).
 *
 * Ausgelöst werden sie beim Seitenaufruf/Button (ContentResolver) und bei `consentlaw:load`.
 * Die Tests hängen eigene Listener an den Dispatcher (AppTestCase::fangeEvents) und lösen
 * nichts selbst aus. Zeit läuft über Carbon::setTestNow (AppTestCase::reise()).
 */
final class StoerungsEventsTest extends AppTestCase
{
    private const NICHT_LIEFERBAR = 'ITHilbert\ConsentLaw\Events\RechtstextNichtLieferbar';

    private const TOKEN_UNGUELTIG = 'ITHilbert\ConsentLaw\Events\TokenVermutlichUngueltig';

    private const NOTSTAND = 'ITHilbert\ConsentLaw\Events\NotstandVeraltet';

    private const ACHT_TAGE = 8 * 86400;

    /** @return array<string, array{0: mixed}> */
    public static function stoerungen(): array
    {
        return [
            'Timeout' => [self::STOERUNG],
            '500' => [500],
            '503' => [503],
            '429 Rate-Limit' => [429],
        ];
    }

    /**
     * @param  \ArrayObject<int, object>  $events
     * @return list<object>
     */
    private function davon(\ArrayObject $events, string $klasse): array
    {
        return array_values(array_filter($events->getArrayCopy(), static fn (object $e): bool => $e instanceof $klasse));
    }

    // ── AK1 ──────────────────────────────────────────────────────────────────

    // AK1
    #[DataProvider('stoerungen')]
    public function test_stoerung_ohne_fallback_loest_beim_seitenaufruf_das_event_aus(mixed $stoerung): void
    {
        $events = $this->fangeEvents();
        $this->api(['*' => $stoerung]);

        $this->rendere('<x-consentlaw::impressum />');

        $treffer = $this->davon($events, self::NICHT_LIEFERBAR);
        $this->assertCount(1, $treffer, 'Erwartet: genau ein Event RechtstextNichtLieferbar.');
        $this->assertSame('impressum', $treffer[0]->typ);
        $this->assertIsString($treffer[0]->fehler);
        $this->assertNotSame('', $treffer[0]->fehler);
    }

    // AK1
    public function test_event_nennt_die_locale(): void
    {
        $events = $this->fangeEvents();
        $this->api(['*' => self::STOERUNG]);

        $this->client()->locale('en')->impressum()->get();

        $treffer = $this->davon($events, self::NICHT_LIEFERBAR);
        $this->assertCount(1, $treffer);
        $this->assertSame('en', $treffer[0]->locale);
    }

    // AK1
    public function test_stoerung_bei_abgeschaltetem_fallback_loest_das_event_aus(): void
    {
        $this->konfig('fallback.enabled', false);
        $events = $this->fangeEvents();
        $this->api(['*' => self::STOERUNG]);

        $this->client()->datenschutz()->get();

        $treffer = $this->davon($events, self::NICHT_LIEFERBAR);
        $this->assertCount(1, $treffer, 'Auch ohne aktiven Fallback ist nichts lieferbar.');
        $this->assertSame('datenschutz', $treffer[0]->typ);
    }

    // AK1
    public function test_stoerung_bei_jobs_ohne_fallback_loest_das_event_aus(): void
    {
        $events = $this->fangeEvents();
        $this->api(['*' => self::STOERUNG]);

        $this->client()->jobs()->abrufen();

        $treffer = $this->davon($events, self::NICHT_LIEFERBAR);
        $this->assertCount(1, $treffer);
        $this->assertSame('jobs', $treffer[0]->typ);
    }

    // AK1
    public function test_stoerung_bei_reload_loest_das_event_je_typ_aus(): void
    {
        $events = $this->fangeEvents();
        $this->api(['*' => self::STOERUNG]);

        [$code] = $this->fuehreAus(ConsentLawLoadCommand::class);

        $typen = array_map(static fn (object $e): string => $e->typ, $this->davon($events, self::NICHT_LIEFERBAR));
        sort($typen);
        $this->assertSame(1, $code);
        $this->assertSame(['agb', 'avv', 'cookie-richtlinie', 'datenschutz', 'impressum', 'jobs'], $typen);
    }

    // AK1
    public function test_kein_event_bei_404(): void
    {
        $events = $this->fangeEvents();
        $this->api(['*' => 404]);

        $this->rendere('<x-consentlaw::impressum />');
        $this->fuehreAus(ConsentLawLoadCommand::class);

        $this->assertCount(0, $this->davon($events, self::NICHT_LIEFERBAR), '404 = „nicht veröffentlicht“, keine Störung.');
        $this->assertCount(0, $this->davon($events, self::NOTSTAND));
    }

    // AK1
    public function test_kein_event_bei_erfolgreichem_abruf(): void
    {
        $events = $this->fangeEvents();
        $this->api(['/api/jobs/' => $this->jobsPayload(), '*' => $this->dokumentPayload('# Inhalt')]);

        $this->rendere('<x-consentlaw::impressum />');
        $this->fuehreAus(ConsentLawLoadCommand::class);

        $this->assertCount(0, $events);
    }

    // AK1
    public function test_kein_event_wenn_ein_fallback_geliefert_wird_der_nicht_veraltet_ist(): void
    {
        $this->api(['*' => $this->dokumentPayload('# Impressum alt')]);
        $this->client()->impressum()->get();
        $this->cacheAblaufenLassen();

        $events = $this->fangeEvents();
        $this->api(['*' => self::STOERUNG]);
        $dokument = $this->client()->impressum()->get();

        $this->assertTrue($dokument->isStale);
        $this->assertCount(0, $events, 'Der Fallback trägt noch – weder „nicht lieferbar“ noch „veraltet“.');
    }

    // AK1 / Randfall Sperrzeit
    public function test_waehrend_der_sperrzeit_gibt_es_kein_neues_event(): void
    {
        $events = $this->fangeEvents();
        $this->api(['*' => self::STOERUNG]);

        $this->client()->impressum()->get();
        $this->reise(120);
        $this->client()->impressum()->get();

        $this->assertCount(1, $this->davon($events, self::NICHT_LIEFERBAR), 'Innerhalb von retry_after wird nicht erneut abgerufen.');
    }

    // ── AK2 ──────────────────────────────────────────────────────────────────

    // AK2
    public function test_reload_mit_allen_typen_404_loest_token_vermutlich_ungueltig_aus(): void
    {
        $events = $this->fangeEvents();
        $this->api(['*' => 404]);

        $this->fuehreAus(ConsentLawLoadCommand::class);

        $this->assertCount(1, $this->davon($events, self::TOKEN_UNGUELTIG));
        $this->assertCount(0, $this->davon($events, self::NICHT_LIEFERBAR), '404 ist keine Störung.');
    }

    // AK2
    public function test_reload_exit_code_und_ausgabe_bleiben_bei_komplettem_404_unveraendert(): void
    {
        $this->fangeEvents();
        $this->api(['*' => 404]);

        [$code, $ausgabe] = $this->fuehreAus(ConsentLawLoadCommand::class);

        $this->assertSame(1, $code, $ausgabe);
        $this->assertStringContainsString('Alle Inhalte sind „nicht veröffentlicht“ – bitte den Token (CONSENTLAW_TOKEN) prüfen, er ist vermutlich ungültig.', $ausgabe);
        foreach (['impressum', 'datenschutz', 'agb', 'jobs'] as $typ) {
            $this->assertMatchesRegularExpression("/{$typ}.*nicht veröffentlicht/u", $ausgabe);
        }
    }

    // AK2
    public function test_kein_token_event_wenn_nur_einzelne_typen_404_sind(): void
    {
        $events = $this->fangeEvents();
        $this->api(['/api/agb/' => 404, '/api/jobs/' => $this->jobsPayload(), '*' => $this->dokumentPayload('# Inhalt')]);

        $this->fuehreAus(ConsentLawLoadCommand::class);

        $this->assertCount(0, $this->davon($events, self::TOKEN_UNGUELTIG));
    }

    // AK2
    public function test_kein_token_event_wenn_alle_dokumente_404_aber_jobs_veroeffentlicht_sind(): void
    {
        $events = $this->fangeEvents();
        $this->api(['/api/jobs/' => $this->jobsPayload(), '*' => 404]);

        $this->fuehreAus(ConsentLawLoadCommand::class);

        $this->assertCount(0, $this->davon($events, self::TOKEN_UNGUELTIG));
    }

    // AK2
    public function test_kein_token_event_bei_reiner_stoerung(): void
    {
        $events = $this->fangeEvents();
        $this->api(['*' => self::STOERUNG]);

        $this->fuehreAus(ConsentLawLoadCommand::class);

        $this->assertCount(0, $this->davon($events, self::TOKEN_UNGUELTIG));
    }

    // AK2
    public function test_seitenaufruf_mit_404_loest_kein_token_event_aus(): void
    {
        $events = $this->fangeEvents();
        $this->api(['*' => 404]);

        $this->rendere('<x-consentlaw::impressum />');

        $this->assertCount(0, $this->davon($events, self::TOKEN_UNGUELTIG), 'Das Event gehört nur zu `consentlaw:load`.');
    }

    // ── AK3 ──────────────────────────────────────────────────────────────────

    // AK3
    #[DataProvider('stoerungen')]
    public function test_veralteter_fallback_loest_notstand_veraltet_aus_und_inhalt_wird_geliefert(mixed $stoerung): void
    {
        $this->api(['*' => $this->dokumentPayload('# Impressum alt')]);
        $this->client()->impressum()->get();
        $this->reise(self::ACHT_TAGE);

        $events = $this->fangeEvents();
        $this->api(['*' => $stoerung]);
        $dokument = $this->client()->impressum()->get();

        $treffer = $this->davon($events, self::NOTSTAND);
        $this->assertCount(1, $treffer);
        $this->assertSame('impressum', $treffer[0]->typ);
        $this->assertSame(self::ACHT_TAGE, $treffer[0]->alterInSekunden);
        $this->assertNotSame('', $treffer[0]->fehler);
        $this->assertNotNull($dokument, 'Der Inhalt wird weiter ausgeliefert.');
        $this->assertSame('# Impressum alt', $dokument->content);
        $this->assertTrue($dokument->isStale);
        $this->assertCount(0, $this->davon($events, self::NICHT_LIEFERBAR), 'Es gibt ja einen Fallback.');
    }

    // AK3
    public function test_notstand_veraltet_auch_ueber_die_komponente(): void
    {
        $this->api(['*' => $this->dokumentPayload('# Impressum alt')]);
        $this->rendere('<x-consentlaw::impressum />');
        $this->reise(self::ACHT_TAGE);

        $events = $this->fangeEvents();
        $this->api(['*' => self::STOERUNG]);
        $html = $this->rendere('<x-consentlaw::impressum />');

        $this->assertCount(1, $this->davon($events, self::NOTSTAND));
        $this->assertStringContainsString('Impressum alt', $html);
    }

    // AK3
    public function test_fallback_juenger_als_warn_after_loest_kein_event_aus(): void
    {
        $this->api(['*' => $this->dokumentPayload('# Impressum alt')]);
        $this->client()->impressum()->get();
        $this->reise(6 * 86400);

        $events = $this->fangeEvents();
        $this->api(['*' => self::STOERUNG]);
        $dokument = $this->client()->impressum()->get();

        $this->assertTrue($dokument->isStale);
        $this->assertCount(0, $events);
    }

    // AK3
    public function test_warn_after_aus_der_config_wird_beachtet(): void
    {
        $this->konfig('fallback.warn_after', 3600);
        $this->api(['*' => $this->dokumentPayload('# Impressum alt')]);
        $this->client()->impressum()->get();
        $this->reise(2 * 3600);

        $events = $this->fangeEvents();
        $this->api(['*' => self::STOERUNG]);
        $this->client()->impressum()->get();

        $treffer = $this->davon($events, self::NOTSTAND);
        $this->assertCount(1, $treffer);
        $this->assertSame(2 * 3600, $treffer[0]->alterInSekunden);
    }

    // AK3
    public function test_notstand_veraltet_bei_reload_trotz_exit_code_success(): void
    {
        $this->api(['/api/jobs/' => $this->jobsPayload(), '*' => $this->dokumentPayload('# Inhalt')]);
        $this->fuehreAus(ConsentLawLoadCommand::class);
        $this->reise(self::ACHT_TAGE);

        $events = $this->fangeEvents();
        $this->api(['*' => self::STOERUNG]);
        [$code, $ausgabe] = $this->fuehreAus(ConsentLawLoadCommand::class);

        $this->assertSame(0, $code, "Der Fallback liefert – Exit-Code unverändert.\n{$ausgabe}");
        $this->assertCount(6, $this->davon($events, self::NOTSTAND), 'Je Typ (5 Dokumente + Jobs) ein Event.');
    }
}
