<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Tests;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * AK2 – Nur 404 heißt „nicht veröffentlicht“. Jeder andere Fehlerstatus
 * (429 durch das Rate-Limit, 401, 403 …) verhält sich wie eine Störung.
 */
final class FehlerstatusTest extends AppTestCase
{
    /** @return array<string, array{0: int}> */
    public static function andereFehlerstatus(): array
    {
        return [
            '429 Rate-Limit' => [429],
            '401 nicht autorisiert' => [401],
            '403 verboten' => [403],
        ];
    }

    // AK2
    #[DataProvider('andereFehlerstatus')]
    public function test_anderer_fehlerstatus_liefert_den_fallback_als_veraltet(int $status): void
    {
        $this->api(['*' => $this->dokumentPayload('# Cookie-Richtlinie alt')]);
        $this->client()->cookieRichtlinie()->get();
        $this->cacheAblaufenLassen();

        $this->api(['*' => $status]);
        $dokument = $this->client()->cookieRichtlinie()->get();

        $this->assertNotNull($dokument, "Status {$status} ist keine Aussage „nicht veröffentlicht“ – der Fallback muss greifen.");
        $this->assertSame('# Cookie-Richtlinie alt', $dokument->content);
        $this->assertTrue($dokument->isStale);
    }

    // AK2
    #[DataProvider('andereFehlerstatus')]
    public function test_anderer_fehlerstatus_liefert_jobs_aus_dem_fallback(int $status): void
    {
        $this->api(['*' => $this->jobsPayload()]);
        $this->client()->jobs()->all();
        $this->cacheAblaufenLassen();

        $this->api(['*' => $status]);
        $jobs = $this->client()->jobs()->all();

        $this->assertCount(1, $jobs);
        $this->assertSame('PHP-Entwickler', $jobs->first()->title);
    }

    // AK2
    #[DataProvider('andereFehlerstatus')]
    public function test_anderer_fehlerstatus_ohne_fallback_liefert_nichts_ohne_exception(int $status): void
    {
        $this->api(['*' => $status]);

        $this->assertNull($this->client()->impressum()->get());
        $this->assertTrue($this->client()->jobs()->all()->isEmpty());
    }

    // AK2 (Abgrenzung): 404 bleibt „nicht veröffentlicht“, auch mit Fallback.
    public function test_404_liefert_weiterhin_nichts_trotz_fallback(): void
    {
        $this->api(['*' => $this->dokumentPayload('# Impressum')]);
        $this->client()->impressum()->get();
        $this->cacheAblaufenLassen();

        $this->api(['*' => 404]);

        $this->assertNull($this->client()->impressum()->get());
    }
}
