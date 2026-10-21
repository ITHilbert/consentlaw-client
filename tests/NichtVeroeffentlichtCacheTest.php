<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Tests;

use ITHilbert\ConsentLaw\Console\ConsentLawLoadCommand;
use ITHilbert\ConsentLaw\Data\Abrufstatus;

/**
 * AK8 – „Nicht veröffentlicht“ (404) wird gecacht, nicht wiederholt und
 * von forget()/refresh()/consentlaw:load verworfen.
 *
 * Ohne Cache würde jeder Seitenaufruf eines Kunden ohne z. B. AGB einen
 * HTTP-Aufruf (plus Retry) auslösen.
 */
final class NichtVeroeffentlichtCacheTest extends AppTestCase
{
    // AK8
    public function test_zweiter_abruf_innerhalb_der_ttl_loest_keinen_http_aufruf_aus(): void
    {
        $this->api(['*' => 404]);
        $this->assertNull($this->client()->agb()->get());
        $this->assertSame(1, $this->anzahlAufrufe(), 'Vorbedingung: erster Abruf fragt die API.');

        $this->reise(self::CACHE_TTL - 1);
        $dokument = $this->client()->agb()->get();

        $this->assertSame(1, $this->anzahlAufrufe(), '404 muss innerhalb der TTL gecacht sein.');
        $this->assertNull($dokument, 'Es wird weiterhin nichts geliefert.');
        $this->assertSame('', $this->client()->agb()->content());
        $this->assertFalse($this->client()->agb()->exists());
        $this->assertSame(1, $this->anzahlAufrufe());
    }

    // AK8
    public function test_der_gecachte_404_meldet_weiterhin_den_status_nicht_veroeffentlicht(): void
    {
        $this->api(['*' => 404]);
        $this->client()->agb()->get();

        $abruf = $this->client()->agb()->abrufen();

        $this->assertSame(1, $this->anzahlAufrufe());
        $this->assertSame(Abrufstatus::NichtVeroeffentlicht, $abruf->status);
        $this->assertNull($abruf->inhalt);
    }

    // AK8
    public function test_gecachter_404_gilt_auch_fuer_jobs(): void
    {
        $this->api(['*' => 404]);
        $this->assertTrue($this->client()->jobs()->all()->isEmpty());

        $this->reise(self::CACHE_TTL - 1);
        $jobs = $this->client()->jobs()->all();

        $this->assertSame(1, $this->anzahlAufrufe(), '404 muss auch für Jobs innerhalb der TTL gecacht sein.');
        $this->assertTrue($jobs->isEmpty());
        $this->assertSame(Abrufstatus::NichtVeroeffentlicht, $this->client()->jobs()->abrufen()->status);
        $this->assertSame(1, $this->anzahlAufrufe());
    }

    // AK8
    public function test_der_404_cache_gilt_nur_fuer_den_betroffenen_inhalt(): void
    {
        $this->api(['/api/agb/' => 404, '*' => $this->dokumentPayload('# Impressum')]);

        $this->assertNull($this->client()->agb()->get());
        $impressum = $this->client()->impressum()->get();

        $this->assertNotNull($impressum, 'Ein 404 bei AGB darf andere Typen nicht verdecken.');
        $this->assertSame(2, $this->anzahlAufrufe());
    }

    // AK8
    public function test_nach_ablauf_der_ttl_wird_wieder_gefragt_und_der_inhalt_geliefert(): void
    {
        $this->api(['*' => 404]);
        $this->client()->agb()->get();

        $this->cacheAblaufenLassen();
        $this->api(['*' => $this->dokumentPayload('# AGB')]);
        $dokument = $this->client()->agb()->get();

        $this->assertSame(2, $this->anzahlAufrufe(), 'Nach Ablauf der TTL wird die API erneut gefragt.');
        $this->assertNotNull($dokument);
        $this->assertSame('# AGB', $dokument->content);
    }

    // AK8
    public function test_nach_ablauf_der_ttl_wird_bei_jobs_wieder_gefragt(): void
    {
        $this->api(['*' => 404]);
        $this->client()->jobs()->all();

        $this->cacheAblaufenLassen();
        $this->api(['*' => $this->jobsPayload()]);
        $jobs = $this->client()->jobs()->all();

        $this->assertSame(2, $this->anzahlAufrufe());
        $this->assertCount(1, $jobs);
    }

    // AK8
    public function test_nach_ablauf_der_ttl_wird_ein_erneuter_404_wieder_gecacht(): void
    {
        $this->api(['*' => 404]);
        $this->client()->agb()->get();
        $this->cacheAblaufenLassen();
        $this->client()->agb()->get();
        $this->assertSame(2, $this->anzahlAufrufe(), 'Vorbedingung: nach der TTL wurde erneut gefragt.');

        $this->client()->agb()->get();

        $this->assertSame(2, $this->anzahlAufrufe(), 'Der neue 404 muss wieder gecacht werden.');
    }

    // AK8
    public function test_ein_404_wird_nicht_wiederholt_auch_bei_konfigurierten_retries(): void
    {
        $this->konfig('http.retries', 1);
        $this->api(['*' => 404]);

        $this->assertNull($this->client()->agb()->get());

        $this->assertSame(1, $this->anzahlAufrufe(), 'Ein Abruf mit 404 = genau ein HTTP-Aufruf, trotz http.retries=1.');
    }

    // AK8
    public function test_ein_404_wird_bei_jobs_nicht_wiederholt_auch_bei_konfigurierten_retries(): void
    {
        $this->konfig('http.retries', 1);
        $this->api(['*' => 404]);

        $this->assertTrue($this->client()->jobs()->all()->isEmpty());

        $this->assertSame(1, $this->anzahlAufrufe());
    }

    // AK8 (Abgrenzung): Retries bleiben für echte Störungen erhalten.
    public function test_retries_gelten_weiterhin_fuer_stoerungen(): void
    {
        $this->konfig('http.retries', 1);
        $this->api(['*' => 503]);

        $this->assertNull($this->client()->agb()->get());

        $this->assertSame(2, $this->anzahlAufrufe(), 'Bei einer Störung (503) wird weiter einmal wiederholt.');
    }

    // AK8
    public function test_bei_deaktiviertem_cache_wird_404_nicht_gecacht(): void
    {
        $this->konfig('cache.enabled', false);
        $this->api(['*' => 404]);

        $this->client()->agb()->get();
        $this->client()->agb()->get();

        $this->assertSame(2, $this->anzahlAufrufe(), 'Mit cache.enabled=false fragt jeder Abruf die API.');
    }

    // AK8
    public function test_bei_deaktiviertem_cache_wird_404_bei_jobs_nicht_gecacht(): void
    {
        $this->konfig('cache.enabled', false);
        $this->api(['*' => 404]);

        $this->client()->jobs()->all();
        $this->client()->jobs()->all();

        $this->assertSame(2, $this->anzahlAufrufe());
    }

    // AK8
    public function test_bei_ttl_null_wird_404_nicht_gecacht(): void
    {
        $this->konfig('cache.ttl', 0);
        $this->api(['*' => 404]);

        $this->client()->agb()->get();
        $this->client()->agb()->get();

        $this->assertSame(2, $this->anzahlAufrufe(), 'Mit TTL 0 fragt jeder Abruf die API.');
    }

    // AK8
    public function test_bei_ttl_null_wird_404_bei_jobs_nicht_gecacht(): void
    {
        $this->konfig('cache.ttl', 0);
        $this->api(['*' => 404]);

        $this->client()->jobs()->all();
        $this->client()->jobs()->all();

        $this->assertSame(2, $this->anzahlAufrufe());
    }

    // AK8 (Wechselwirkung mit AK1d): Fallback-Datei bleibt, 404 wird trotzdem gecacht und liefert nichts.
    public function test_gecachter_404_liefert_auch_mit_fallback_datei_nichts(): void
    {
        $this->api(['*' => $this->dokumentPayload('# AGB')]);
        $this->client()->agb()->get();
        $this->cacheAblaufenLassen();

        $this->api(['*' => 404]);
        $this->assertNull($this->client()->agb()->get());
        $this->assertNull($this->client()->agb()->get());

        $this->assertSame(2, $this->anzahlAufrufe(), 'Erster Abruf + ein 404-Abruf, der zweite 404 kommt aus dem Cache.');
    }

    // AK8 (Wechselwirkung mit forget())
    public function test_forget_verwirft_den_gecachten_404(): void
    {
        $this->api(['*' => 404]);
        $this->client()->agb()->get();

        $this->api(['*' => $this->dokumentPayload('# AGB neu')]);
        $this->client()->agb()->forget();
        $dokument = $this->client()->agb()->get();

        $this->assertSame(2, $this->anzahlAufrufe(), 'Nach forget() wird die API wieder gefragt.');
        $this->assertNotNull($dokument);
        $this->assertSame('# AGB neu', $dokument->content);
    }

    // AK8 (Wechselwirkung mit forget())
    public function test_forget_verwirft_den_gecachten_404_bei_jobs(): void
    {
        $this->api(['*' => 404]);
        $this->client()->jobs()->all();

        $this->api(['*' => $this->jobsPayload()]);
        $this->client()->jobs()->forget();
        $jobs = $this->client()->jobs()->all();

        $this->assertSame(2, $this->anzahlAufrufe());
        $this->assertCount(1, $jobs);
    }

    // AK8 (Wechselwirkung mit refresh())
    public function test_refresh_umgeht_den_gecachten_404(): void
    {
        $this->api(['*' => 404]);
        $this->client()->agb()->get();

        $this->api(['*' => $this->dokumentPayload('# AGB neu')]);
        $dokument = $this->client()->agb()->refresh();

        $this->assertNotNull($dokument);
        $this->assertSame('# AGB neu', $dokument->content);
        $this->assertSame(2, $this->anzahlAufrufe());
    }

    // AK8 (Wechselwirkung mit refresh())
    public function test_refresh_umgeht_den_gecachten_404_bei_jobs(): void
    {
        $this->api(['*' => 404]);
        $this->client()->jobs()->all();

        $this->api(['*' => $this->jobsPayload()]);
        $jobs = $this->client()->jobs()->refresh();

        $this->assertCount(1, $jobs);
        $this->assertSame(2, $this->anzahlAufrufe());
    }

    // AK8 (Wechselwirkung mit AK7): reload ruft forget() vor dem Abruf – ein gecachter 404 darf es nicht aushebeln.
    public function test_reload_ignoriert_einen_gecachten_404_und_liefert_den_inzwischen_veroeffentlichten_inhalt(): void
    {
        $this->api(['*' => 404]);
        $this->client()->agb()->get();
        $this->client()->jobs()->all();

        $this->api(['/api/jobs/' => $this->jobsPayload(), '*' => $this->dokumentPayload('# Inhalt')]);
        [$code, $ausgabe] = $this->fuehreAus(ConsentLawLoadCommand::class);

        $this->assertSame(0, $code, $ausgabe);
        $this->assertDoesNotMatchRegularExpression('/agb.*nicht veröffentlicht/u', $ausgabe, "reload darf den gecachten 404 nicht übernehmen.\n{$ausgabe}");
        $this->assertDoesNotMatchRegularExpression('/jobs.*nicht veröffentlicht/u', $ausgabe, $ausgabe);
        $this->assertMatchesRegularExpression('/agb: \d+ Zeichen/u', $ausgabe);
        $this->assertMatchesRegularExpression('/jobs: 1 Stellenangebote/u', $ausgabe);
        $this->assertCount(6, $this->fallbackDateien());
    }
}
