<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Tests;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * AK3 – Kein Dauer-Warten bei Ausfall.
 *
 * VERTRAG (neu, noch nicht vorhanden):
 *  - Config-Schlüssel `consentlaw.fallback.retry_after` (int Sekunden, Standard 300).
 *  - Nach einer Störung (Timeout/Verbindungsfehler/5xx) oder einem Fehlerstatus
 *    ≠ 404 (AK2) setzt der ContentResolver einen Sperrvermerk je Inhalt (Key)
 *    für `retry_after` Sekunden – im Cache-Store, unabhängig von `cache.enabled`.
 *  - Solange der Sperrvermerk gilt: KEIN HTTP-Aufruf; es wird der Fallback
 *    geliefert (stale), ohne Fallback null bzw. leere Job-Liste.
 *  - Nach Ablauf wird die API wieder gefragt.
 *
 * Die Tests prüfen nur Verhalten über die Config (kein Konstruktor-Vertrag):
 * Der Provider reicht `fallback.retry_after` an den ContentResolver durch.
 * Zeit läuft über Carbon::setTestNow (AppTestCase::reise()).
 */
final class AusfallWartezeitTest extends AppTestCase
{
    // AK3
    public function test_standard_wartezeit_ist_300_sekunden_in_der_config(): void
    {
        $this->assertSame(300, $this->app['config']->get('consentlaw.fallback.retry_after'));
    }

    // AK3
    public function test_waehrend_der_wartezeit_kein_http_aufruf_auch_ohne_fallback(): void
    {
        $this->api(['*' => self::STOERUNG]);
        $this->assertNull($this->client()->impressum()->get());
        $this->assertSame(1, $this->anzahlAufrufe());

        $this->reise(299);
        $dokument = $this->client()->impressum()->get();

        $this->assertNull($dokument, 'Ohne Fallback wird direkt nichts geliefert.');
        $this->assertSame(1, $this->anzahlAufrufe(), 'Innerhalb der Wartezeit (299 s < 300 s) darf nicht erneut gewartet werden.');
    }

    // AK3
    public function test_waehrend_der_wartezeit_wird_der_fallback_ohne_http_aufruf_geliefert(): void
    {
        $this->api(['*' => $this->dokumentPayload('# Impressum alt')]);
        $this->client()->impressum()->get();
        $this->cacheAblaufenLassen();
        $this->api(['*' => self::STOERUNG]);
        $this->client()->impressum()->get();
        $this->assertSame(2, $this->anzahlAufrufe(), 'Vorbedingung: Störung wurde einmal erlebt.');

        $this->reise(120);
        $dokument = $this->client()->impressum()->get();

        $this->assertSame(2, $this->anzahlAufrufe(), 'Während der Wartezeit kein HTTP-Aufruf.');
        $this->assertNotNull($dokument);
        $this->assertSame('# Impressum alt', $dokument->content);
        $this->assertTrue($dokument->isStale);
    }

    // AK3
    public function test_nach_ablauf_der_wartezeit_wird_die_api_erneut_gefragt(): void
    {
        $this->api(['*' => self::STOERUNG]);
        $this->client()->impressum()->get();

        $this->reise(301);
        $this->client()->impressum()->get();

        $this->assertSame(2, $this->anzahlAufrufe(), 'Nach 301 s (> 300 s) wird die API erneut gefragt.');
    }

    // AK3
    public function test_nach_der_wartezeit_liefert_eine_erholte_api_wieder_frischen_inhalt(): void
    {
        $this->api(['*' => $this->dokumentPayload('# Impressum alt')]);
        $this->client()->impressum()->get();
        $this->cacheAblaufenLassen();
        $this->api(['*' => self::STOERUNG]);
        $this->client()->impressum()->get();

        $this->reise(301);
        $this->api(['*' => $this->dokumentPayload('# Impressum neu')]);
        $dokument = $this->client()->impressum()->get();

        $this->assertSame(3, $this->anzahlAufrufe());
        $this->assertSame('# Impressum neu', $dokument->content);
        $this->assertFalse($dokument->isStale);
    }

    // AK3
    public function test_die_wartezeit_ist_ueber_die_config_einstellbar(): void
    {
        $this->konfig('fallback.retry_after', 60);
        $this->api(['*' => self::STOERUNG]);
        $this->client()->impressum()->get();

        $this->reise(59);
        $this->client()->impressum()->get();
        $this->assertSame(1, $this->anzahlAufrufe(), 'Nach 59 s gilt die Sperre (retry_after = 60).');

        $this->reise(2);
        $this->client()->impressum()->get();
        $this->assertSame(2, $this->anzahlAufrufe(), 'Nach 61 s ist die Sperre abgelaufen.');
    }

    /**
     * Fehlerstatus ≠ 404 zählt wie eine Störung (AK2) und sperrt ebenso.
     *
     * @return array<string, array{0: int}>
     */
    public static function sperrendeStatus(): array
    {
        return [
            '429' => [429],
            '401' => [401],
            '403' => [403],
            '500' => [500],
            '503' => [503],
        ];
    }

    // AK3 (AK2)
    #[DataProvider('sperrendeStatus')]
    public function test_auch_ein_fehlerstatus_loest_die_wartezeit_aus(int $status): void
    {
        $this->api(['*' => $status]);
        $this->client()->impressum()->get();

        $this->reise(60);
        $this->client()->impressum()->get();

        $this->assertSame(1, $this->anzahlAufrufe(), "Nach Status {$status} darf innerhalb der Wartezeit kein erneuter Aufruf erfolgen.");
    }

    // AK3
    public function test_wartezeit_gilt_auch_bei_deaktiviertem_inhalts_cache(): void
    {
        $this->konfig('cache.enabled', false);
        $this->api(['*' => $this->dokumentPayload('# Impressum alt')]);
        $this->client()->impressum()->get();
        $this->assertSame(1, $this->anzahlAufrufe());

        $this->api(['*' => self::STOERUNG]);
        $erster = $this->client()->impressum()->get();
        $this->assertSame(2, $this->anzahlAufrufe(), 'Ohne Cache fragt jeder Abruf die API – hier die Störung.');
        $this->assertTrue($erster->isStale);

        $zweiter = $this->client()->impressum()->get();

        $this->assertSame(2, $this->anzahlAufrufe(), 'Die Wartezeit hängt nicht am Inhalts-Cache.');
        $this->assertSame('# Impressum alt', $zweiter->content);
        $this->assertTrue($zweiter->isStale);
    }

    // AK3 (Plan: Sperrvermerk je Key)
    public function test_wartezeit_gilt_nur_fuer_den_betroffenen_inhalt(): void
    {
        $this->api(['*' => self::STOERUNG]);
        $this->client()->impressum()->get();
        $this->assertSame(1, $this->anzahlAufrufe());

        $this->client()->datenschutz()->get();

        $this->assertSame(2, $this->anzahlAufrufe(), 'Ein anderer Inhalt wird weiterhin abgefragt.');
    }

    // AK3
    public function test_wartezeit_gilt_auch_fuer_jobs(): void
    {
        $this->api(['*' => $this->jobsPayload()]);
        $this->client()->jobs()->all();
        $this->cacheAblaufenLassen();
        $this->api(['*' => self::STOERUNG]);
        $this->client()->jobs()->all();
        $this->assertSame(2, $this->anzahlAufrufe());

        $this->reise(10);
        $jobs = $this->client()->jobs()->all();

        $this->assertSame(2, $this->anzahlAufrufe());
        $this->assertCount(1, $jobs, 'Der Fallback-Stand wird weiter geliefert.');
    }

    // AK3 (Abgrenzung): Ein erfolgreicher Abruf löst keine Wartezeit aus.
    public function test_nach_erfolgreichem_abruf_gibt_es_keine_wartezeit(): void
    {
        $this->api(['*' => $this->dokumentPayload('# Impressum')]);
        $this->client()->impressum()->get();
        $this->cacheAblaufenLassen();

        $this->client()->impressum()->get();

        $this->assertSame(2, $this->anzahlAufrufe(), 'Nach Cache-Ablauf wird bei gesunder API normal neu geholt.');
    }
}
