<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Tests;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Feature banner-komponente, Aufgabe T1: `<x-consentlaw::banner />` (AK1–AK4, Randfall 1).
 */
final class BannerKomponenteTest extends AppTestCase
{
    private const BANNER = '<x-consentlaw::banner />';

    /** @return list<string> alle <script …>-Starttags der Ausgabe */
    private function scriptTags(string $html): array
    {
        preg_match_all('/<script\b[^>]*>/i', $html, $treffer);

        return $treffer[0];
    }

    private function aktiv(string $token = 'abc123', string $basis = 'https://www.consentlaw.eu'): void
    {
        $this->konfig('token', $token);
        $this->konfig('base_url', $basis);
        $this->konfig('banner.enabled', true);
    }

    /** @return array<string, array{mixed}> */
    public static function tokenLeer(): array
    {
        return ['nicht gesetzt (null)' => [null], 'leer gesetzt' => ['']];
    }

    // AK1
    public function test_gibt_genau_ein_script_mit_src_und_defer_aus(): void
    {
        $this->aktiv('abc123', 'https://www.consentlaw.eu');

        $html = $this->rendere(self::BANNER);
        $tags = $this->scriptTags($html);

        $this->assertCount(1, $tags, 'Genau ein <script>.');
        $this->assertSame(1, preg_match('/\bsrc="https:\/\/www\.consentlaw\.eu\/banner\.js\?token=abc123"/', $tags[0]));
        $this->assertSame(1, preg_match('/\sdefer(\s|=|>|\/)/', $tags[0]), 'Attribut defer.');
        $this->assertSame(1, substr_count($html, '</script>'));
    }

    // AK1
    public function test_token_ist_url_kodiert_und_html_maskiert(): void
    {
        $token = 'a&b"<c>+/=\'x y';
        $this->aktiv($token);

        $html = $this->rendere(self::BANNER);
        $tags = $this->scriptTags($html);

        $this->assertCount(1, $tags);
        $this->assertStringContainsString('token='.rawurlencode($token).'"', $tags[0]);
        $this->assertStringNotContainsString($token, $html, 'Token nie roh in der Ausgabe.');
        $this->assertSame(1, substr_count($tags[0], '<'), 'Kein eingeschleustes Tag-Zeichen im Starttag.');
    }

    // AK1
    public function test_base_url_kommt_aus_der_konfiguration(): void
    {
        $this->aktiv('abc123', 'https://banner.example');

        $this->assertStringContainsString('src="https://banner.example/banner.js?token=abc123"', $this->rendere(self::BANNER));
    }

    // AK1, Randfall 1
    #[DataProvider('abschliessendeSchraegstriche')]
    public function test_base_url_mit_abschliessendem_schraegstrich_gibt_keinen_doppelten(string $basis): void
    {
        $this->aktiv('abc123', $basis);

        $html = $this->rendere(self::BANNER);

        $this->assertStringContainsString('src="https://www.consentlaw.eu/banner.js?token=abc123"', $html);
        $this->assertStringNotContainsString('//banner.js', $html);
    }

    /** @return array<string, array{string}> */
    public static function abschliessendeSchraegstriche(): array
    {
        return ['ein Slash' => ['https://www.consentlaw.eu/'], 'zwei Slashes' => ['https://www.consentlaw.eu//']];
    }

    // AK2
    #[DataProvider('tokenLeer')]
    public function test_ohne_token_ist_die_ausgabe_leer(mixed $token): void
    {
        // Gegenprobe: mit Token und eingeschaltet gibt es ein Script.
        $this->aktiv();
        $this->assertCount(1, $this->scriptTags($this->rendere(self::BANNER)));

        $this->konfig('token', $token);

        $this->assertSame('', trim($this->rendere(self::BANNER)));
    }

    // AK3
    public function test_ausgeschaltet_ist_die_ausgabe_leer_auch_mit_token(): void
    {
        // Gegenprobe: eingeschaltet gibt es ein Script.
        $this->aktiv();
        $this->assertCount(1, $this->scriptTags($this->rendere(self::BANNER)));

        $this->konfig('banner.enabled', false);

        $this->assertSame('', trim($this->rendere(self::BANNER)));
    }

    // AK3
    public function test_umgebungsvariable_false_schaltet_den_banner_in_der_konfiguration_aus(): void
    {
        $this->setzeUmgebung('CONSENTLAW_BANNER_ENABLED', 'false');
        $this->appNeuBauen('testing');

        $this->assertFalse($this->app['config']->get('consentlaw.banner.enabled'));
        $this->assertSame('', trim($this->rendere(self::BANNER)), 'Token ist gesetzt (AppTestCase), Ausgabe trotzdem leer.');
    }

    // AK3
    public function test_umgebungsvariable_true_laesst_den_banner_eingeschaltet(): void
    {
        $this->setzeUmgebung('CONSENTLAW_BANNER_ENABLED', 'true');
        $this->appNeuBauen('testing');

        $this->assertTrue($this->app['config']->get('consentlaw.banner.enabled'));
        $this->assertCount(1, $this->scriptTags($this->rendere(self::BANNER)));
    }

    // AK3
    public function test_ohne_umgebungsvariable_ist_der_banner_eingeschaltet(): void
    {
        $this->assertNull(getenv('CONSENTLAW_BANNER_ENABLED') ?: null, 'Aufbau: Variable ist nicht gesetzt.');
        $this->assertTrue($this->app['config']->get('consentlaw.banner.enabled'));
        $this->assertCount(1, $this->scriptTags($this->rendere(self::BANNER)), 'Token ist gesetzt (AppTestCase).');
    }

    // AK4
    public function test_kein_api_aufruf_cache_oder_fallback_zugriff(): void
    {
        // Cache-Store, der jeden Zugriff zählt (Lesen, Schreiben, Prüfen). Muss vor dem ersten Client-Zugriff stehen.
        $zaehler = new \ArrayObject(['zugriffe' => 0]);
        $store = new class($zaehler) extends ArrayStore
        {
            public function __construct(private \ArrayObject $zaehler)
            {
                parent::__construct(false);
            }

            public function get($key)
            {
                $this->zaehler['zugriffe']++;

                return parent::get($key);
            }

            public function many(array $keys)
            {
                $this->zaehler['zugriffe']++;

                return parent::many($keys);
            }

            public function put($key, $value, $seconds)
            {
                $this->zaehler['zugriffe']++;

                return parent::put($key, $value, $seconds);
            }
        };
        $this->app['cache']->extend('spy', fn () => new Repository($store));
        $this->app['config']->set('cache.stores.spy', ['driver' => 'spy']);
        $this->konfig('cache.store', 'spy');

        $this->api(['*' => $this->dokumentPayload('Impressum-Text')]);

        // Gegenprobe: ein Dokument löst HTTP, Cache und Fallback-Datei aus – die Messung greift.
        $this->rendere('<x-consentlaw::impressum />');
        $this->assertGreaterThan(0, $this->anzahlAufrufe(), 'Gegenprobe: HTTP wird erfasst.');
        $this->assertGreaterThan(0, $zaehler['zugriffe'], 'Gegenprobe: Cache-Zugriffe werden erfasst.');
        $this->assertNotEmpty($this->fallbackDateien(), 'Gegenprobe: Fallback-Datei wird erfasst.');

        // Neu messen.
        foreach ($this->fallbackDateien() as $datei) {
            unlink($datei);
        }
        $this->aufrufe = [];
        $zaehler['zugriffe'] = 0;

        // AK1: aktiv
        $this->aktiv();
        $this->assertCount(1, $this->scriptTags($this->rendere(self::BANNER)));
        // AK2: kein Token
        $this->konfig('token', '');
        $this->assertSame('', trim($this->rendere(self::BANNER)));
        // AK3: ausgeschaltet
        $this->aktiv();
        $this->konfig('banner.enabled', false);
        $this->assertSame('', trim($this->rendere(self::BANNER)));

        $this->assertSame(0, $this->anzahlAufrufe(), 'Kein HTTP-Request.');
        $this->assertSame(0, $zaehler['zugriffe'], 'Kein Cache-Zugriff.');
        $this->assertSame([], $this->fallbackDateien(), 'Kein Fallback-Zugriff.');
    }
}
