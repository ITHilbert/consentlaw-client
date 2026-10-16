<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Tests;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * AK1 – Kernablauf Cache → API → Fallback (bestehendes Verhalten, absichern).
 *
 * Läuft über die echte Verdrahtung (Provider, HttpTransport, ContentResolver,
 * Endpoints); nur der HTTP-Client ist ein Fake (siehe AppTestCase::api()).
 */
final class KernablaufTest extends AppTestCase
{
    // AK1a
    public function test_frischer_cache_loest_keinen_http_aufruf_aus(): void
    {
        $this->api(['*' => $this->dokumentPayload('# Impressum')]);
        $this->client()->impressum()->get();
        $this->assertSame(1, $this->anzahlAufrufe(), 'Vorbedingung: erster Abruf füllt den Cache.');

        $dokument = $this->client()->impressum()->get();

        $this->assertSame(1, $this->anzahlAufrufe(), 'Frischer Cache darf keinen HTTP-Aufruf auslösen.');
        $this->assertNotNull($dokument);
        $this->assertSame('# Impressum', $dokument->content);
        $this->assertFalse($dokument->isStale);
    }

    // AK1a
    public function test_frischer_cache_wird_auch_bei_ausgefallener_api_ohne_markierung_geliefert(): void
    {
        $this->api(['*' => $this->dokumentPayload('# Impressum')]);
        $this->client()->impressum()->get();

        $this->api(['*' => self::STOERUNG]);
        $dokument = $this->client()->impressum()->get();

        $this->assertSame(1, $this->anzahlAufrufe());
        $this->assertFalse($dokument->isStale, 'Ein frischer Cache-Treffer ist nicht veraltet.');
    }

    // AK1a
    public function test_frischer_cache_gilt_auch_fuer_jobs(): void
    {
        $this->api(['*' => $this->jobsPayload()]);
        $this->client()->jobs()->all();

        $jobs = $this->client()->jobs()->all();

        $this->assertSame(1, $this->anzahlAufrufe());
        $this->assertCount(1, $jobs);
    }

    // AK1b
    public function test_erfolgreiche_antwort_liefert_den_inhalt(): void
    {
        $this->api(['*' => $this->dokumentPayload('# Datenschutz')]);

        $dokument = $this->client()->datenschutz()->get();

        $this->assertNotNull($dokument);
        $this->assertSame('# Datenschutz', $dokument->content);
        $this->assertFalse($dokument->isStale);
        $this->assertStringContainsString('/api/datenschutz/'.self::TOKEN, $this->aufrufe[0]);
    }

    // AK1b
    public function test_erfolgreiche_antwort_wird_gecacht(): void
    {
        $this->api(['*' => $this->dokumentPayload('# Datenschutz')]);

        $this->client()->datenschutz()->get();
        $this->client()->datenschutz()->get();
        $this->client()->datenschutz()->get();

        $this->assertSame(1, $this->anzahlAufrufe());
    }

    // AK1b
    public function test_erfolgreiche_antwort_wird_als_fallback_datei_gespeichert(): void
    {
        $this->api(['*' => $this->dokumentPayload('# Datenschutz')]);

        $this->client()->datenschutz()->get();

        $dateien = $this->fallbackDateien();
        $this->assertCount(1, $dateien, 'Es muss genau eine Fallback-Datei geben.');

        $gespeichert = json_decode((string) file_get_contents($dateien[0]), true);
        $this->assertSame('# Datenschutz', $gespeichert['payload']['content'] ?? null);
    }

    // AK1b
    public function test_erfolgreiche_jobs_antwort_wird_geliefert_gecacht_und_gespeichert(): void
    {
        $this->api(['*' => $this->jobsPayload()]);

        $jobs = $this->client()->jobs()->all();
        $this->client()->jobs()->all();

        $this->assertCount(1, $jobs);
        $this->assertSame('PHP-Entwickler', $jobs->first()->title);
        $this->assertSame(1, $this->anzahlAufrufe());
        $this->assertCount(1, $this->fallbackDateien());
    }

    /**
     * Störung = Timeout/Verbindungsfehler/5xx.
     *
     * @return array<string, array{0: mixed}>
     */
    public static function stoerungen(): array
    {
        return [
            'Timeout/Verbindungsfehler' => [self::STOERUNG],
            'HTTP 500' => [500],
            'HTTP 503' => [503],
        ];
    }

    // AK1c
    #[DataProvider('stoerungen')]
    public function test_bei_stoerung_wird_der_fallback_geliefert_und_als_veraltet_markiert(mixed $stoerung): void
    {
        $this->api(['*' => $this->dokumentPayload('# Impressum alt')]);
        $this->client()->impressum()->get();
        $this->cacheAblaufenLassen();

        $this->api(['*' => $stoerung]);
        $dokument = $this->client()->impressum()->get();

        $this->assertSame(2, $this->anzahlAufrufe(), 'Nach Ablauf des Caches wird die API gefragt.');
        $this->assertNotNull($dokument, 'Fallback muss den Inhalt liefern.');
        $this->assertSame('# Impressum alt', $dokument->content);
        $this->assertTrue($dokument->isStale);
    }

    // AK1c
    #[DataProvider('stoerungen')]
    public function test_bei_stoerung_werden_jobs_aus_dem_fallback_geliefert(mixed $stoerung): void
    {
        $this->api(['*' => $this->jobsPayload()]);
        $this->client()->jobs()->all();
        $this->cacheAblaufenLassen();

        $this->api(['*' => $stoerung]);
        $jobs = $this->client()->jobs()->all();

        $this->assertCount(1, $jobs);
        $this->assertSame('PHP-Entwickler', $jobs->first()->title);
    }

    // AK1c
    public function test_ohne_fallback_und_bei_stoerung_wird_nichts_geliefert_ohne_exception(): void
    {
        $this->api(['*' => self::STOERUNG]);

        $this->assertNull($this->client()->impressum()->get());
        $this->assertSame('', $this->client()->impressum()->content());
        $this->assertTrue($this->client()->jobs()->all()->isEmpty());
    }

    // AK1d
    public function test_bei_404_wird_nichts_geliefert_auch_wenn_ein_fallback_existiert(): void
    {
        $this->api(['*' => $this->dokumentPayload('# AGB')]);
        $this->client()->agb()->get();
        $this->assertCount(1, $this->fallbackDateien(), 'Vorbedingung: Fallback-Datei existiert.');
        $this->cacheAblaufenLassen();

        $this->api(['*' => 404]);

        $this->assertNull($this->client()->agb()->get());
        $this->assertSame('', $this->client()->agb()->content());
        $this->assertFalse($this->client()->agb()->exists());
    }

    // AK1d
    public function test_bei_404_ist_die_jobliste_leer_auch_wenn_ein_fallback_existiert(): void
    {
        $this->api(['*' => $this->jobsPayload()]);
        $this->client()->jobs()->all();
        $this->assertCount(1, $this->fallbackDateien(), 'Vorbedingung: Fallback-Datei existiert.');
        $this->cacheAblaufenLassen();

        $this->api(['*' => 404]);

        $this->assertTrue($this->client()->jobs()->all()->isEmpty());
    }

    // Randfall zu AK1c: beschädigte Fallback-Datei zählt wie „kein Fallback“.
    public function test_beschaedigte_fallback_datei_wird_wie_kein_fallback_behandelt(): void
    {
        $this->api(['*' => $this->dokumentPayload('# Impressum')]);
        $this->client()->impressum()->get();
        $dateien = $this->fallbackDateien();
        $this->assertCount(1, $dateien);
        file_put_contents($dateien[0], '{das ist kein json');
        $this->cacheAblaufenLassen();

        $this->api(['*' => self::STOERUNG]);

        $this->assertNull($this->client()->impressum()->get());
    }
}
