<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Tests;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Rückmeldung nach „Neu laden“ – AK1 bis AK10 aus .project/features/neu-laden-rueckmeldung/spec.md.
 *
 * Der Ablauf ist der des Browsers: Seite abrufen, das Formular „Neu laden“ aus dem HTML
 * lesen und mit allen versteckten Feldern (`_token`, `zurueck`, `locale`) absenden – OHNE
 * Referer-Header (die Homepage sendet no-referrer) –, dem Redirect folgen und die Meldung
 * im gerenderten HTML der Zielseite prüfen. Die Cookies der Sitzung laufen mit.
 *
 * Vertrag aus der Spec, soweit er hier geprüft wird:
 *  - Meldung = Element mit `role="status"` und Klasse `consentlaw-refresh-meldung` plus
 *    `consentlaw-refresh-meldung--ok` bzw. `--fehler`, vor dem Inhalt der Komponente.
 *  - Texte exakt laut Spec; Datum `d.m.Y H:i` in der Zeitzone der App.
 *  - Die Meldung gilt genau einen Seitenaufruf lang und nur für die Komponente (Typ + Sprache),
 *    deren Button ausgelöst wurde.
 *
 * Die Seiten sind Test-Routen der Test-App (Gruppe `web` mit Session), die die Komponenten rendern.
 */
final class NeuLadenRueckmeldungTest extends AppTestCase
{
    private const GEHEIM = 'GEHEIM-TOKEN-4711';

    private const TEXT_ERFOLG = 'Neu geladen – Stand: %s';

    private const TEXT_STOERUNG_MIT_STAND = 'Neu laden fehlgeschlagen: consentLaw nicht erreichbar. Angezeigt wird der Stand vom %s.';

    private const TEXT_STOERUNG_OHNE_STAND = 'Neu laden fehlgeschlagen: consentLaw nicht erreichbar, kein gespeicherter Stand vorhanden.';

    private const TEXT_NICHT_VEROEFFENTLICHT = 'Neu laden fehlgeschlagen: Inhalt bei consentLaw nicht veröffentlicht oder Token ungültig.';

    private const TEXT_KEIN_TOKEN = 'Neu laden fehlgeschlagen: kein Token konfiguriert (CONSENTLAW_TOKEN).';

    private string $urspruenglicheZone;

    /** @var array<string, string> Cookies der Test-Sitzung (leer = Homepage ohne Session) */
    private array $cookies = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->urspruenglicheZone = date_default_timezone_get();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->urspruenglicheZone);

        parent::tearDown();
    }

    // ── Aufbau ───────────────────────────────────────────────────────────────

    /**
     * Schalter an, Test-Seiten registriert, Sitzung (Cookie) angelegt.
     * Der Token ist erkennbar, damit ein Durchsickern auffällt (AK5, Randfall).
     */
    private function bereit(bool $mitSession = true, string $umgebung = 'local'): void
    {
        $this->appNeuBauen($umgebung, ['refresh_button' => true]);
        $this->konfig('token', self::GEHEIM);

        $router = $this->app['router'];
        $router->middleware('web')->get('/impressum', static fn (): string => Blade::render('<x-consentlaw::impressum />'));
        $router->middleware('web')->get('/jobs', static fn (): string => Blade::render('<x-consentlaw::jobs />'));
        $router->middleware('web')->get('/gemischt', static fn (): string => Blade::render(
            '<x-consentlaw::jobs /><x-consentlaw::impressum /><x-consentlaw::impressum locale="en" /><x-consentlaw::datenschutz />'
        ));
        $this->cookies = [];

        if ($mitSession) {
            $store = $this->app['session']->driver();
            $store->start();
            $store->token();
            $store->save();
            $this->cookies = [$store->getName() => $store->getId()];
        }
    }

    /** @param  array<string, mixed>|int|string  $antwort  siehe AppTestCase::api() */
    private function apiAlle(mixed $antwort): void
    {
        $this->api(['*' => $antwort]);
    }

    /** Antwort je Typ: Dokument-Payload bzw. Jobs-Payload mit erkennbarem Text. */
    private function payload(string $typ, string $text): array
    {
        return $typ === 'jobs' ? $this->jobsPayload(['title' => $text]) : $this->dokumentPayload($text);
    }

    private function seiteFuer(string $typ): string
    {
        return $typ === 'jobs' ? '/jobs' : '/impressum';
    }

    // ── HTTP wie im Browser ──────────────────────────────────────────────────

    /** @param  array<string, mixed>  $daten */
    private function anfrage(string $url, string $methode, array $daten = []): HttpRequest
    {
        return HttpRequest::create($url, $methode, $daten, $this->cookies, [], ['REMOTE_ADDR' => '203.0.113.5']);
    }

    /** @return array{0: int, 1: ?SymfonyResponse} */
    private function sende(HttpRequest $anfrage): array
    {
        $this->app->instance('request', $anfrage);

        try {
            $antwort = $this->app['router']->dispatch($anfrage);
        } catch (TokenMismatchException) {
            return [419, null];
        } catch (HttpExceptionInterface $e) {
            return [$e->getStatusCode(), null];
        }

        return [$antwort->getStatusCode(), $antwort];
    }

    /** Ruft eine Seite auf (GET, mit Sitzung) und liefert das HTML. */
    private function seite(string $pfad): string
    {
        [$status, $antwort] = $this->sende($this->anfrage('http://localhost'.$pfad, 'GET'));

        $this->assertSame(200, $status, "Seite {$pfad} muss 200 liefern.");

        return (string) $antwort->getContent();
    }

    private function dom(string $html): DOMDocument
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><body>'.$html.'</body>');
        libxml_clear_errors();

        return $dom;
    }

    private const KLASSE = "contains(concat(' ', normalize-space(@class), ' '), ' %s ')";

    /**
     * Sendet das Neu-laden-Formular aus dem HTML ab: alle Felder des Formulars,
     * Ziel = action. Kein Referer, kein Origin.
     *
     * @return array{0: int, 1: ?SymfonyResponse}
     */
    private function absenden(string $html, string $typ = 'impressum', ?string $locale = null): array
    {
        $dom = $this->dom($html);
        $xpath = new DOMXPath($dom);
        $formulare = $xpath->query('//form['.sprintf(self::KLASSE, 'consentlaw-refresh').']');

        $passend = null;
        foreach ($formulare as $form) {
            /** @var DOMElement $form */
            if (! str_ends_with($form->getAttribute('action'), '/consentlaw/neu-laden/'.$typ)) {
                continue;
            }

            $localeFeld = $xpath->query('.//input[@name="locale"]', $form)->item(0);
            if (($localeFeld?->getAttribute('value')) !== $locale) {
                continue;
            }

            $passend = $form;

            break;
        }

        $this->assertNotNull($passend, "Kein Neu-laden-Formular für '{$typ}' (Sprache ".($locale ?? 'Standard').') im HTML.');

        $felder = [];
        foreach ($xpath->query('.//input[@type="hidden"]', $passend) as $feld) {
            /** @var DOMElement $feld */
            $felder[$feld->getAttribute('name')] = $feld->getAttribute('value');
        }

        return $this->sende($this->anfrage($passend->getAttribute('action'), 'POST', $felder));
    }

    /** Folgt dem Redirect wie der Browser und liefert das HTML der Zielseite. */
    private function folge(int $status, ?SymfonyResponse $antwort): string
    {
        $this->assertSame(302, $status, 'Neu laden muss zurückleiten (302).');

        $ziel = parse_url((string) $antwort->headers->get('Location'));
        $this->assertSame('localhost', $ziel['host'] ?? null, 'Rückleitung nur auf den eigenen Host.');

        return $this->seite(($ziel['path'] ?? '/').(isset($ziel['query']) ? '?'.$ziel['query'] : ''));
    }

    /** Seite aufrufen, „Neu laden“ drücken, Redirect folgen. @return string HTML der Zielseite */
    private function neuLadenUndZurueck(string $seite, string $typ = 'impressum', ?string $locale = null): string
    {
        $vorher = $this->seite($seite);
        [$status, $antwort] = $this->absenden($vorher, $typ, $locale);

        return $this->folge($status, $antwort);
    }

    // ── Auswertung des HTML ──────────────────────────────────────────────────

    /** @return list<array{text: string, klassen: string, role: string}> */
    private function meldungen(string $html): array
    {
        $treffer = (new DOMXPath($this->dom($html)))->query('//*['.sprintf(self::KLASSE, 'consentlaw-refresh-meldung').']');

        $liste = [];
        foreach ($treffer as $element) {
            /** @var DOMElement $element */
            $liste[] = [
                'text' => trim((string) preg_replace('/\s+/u', ' ', $element->textContent)),
                'klassen' => $element->getAttribute('class'),
                'role' => $element->getAttribute('role'),
            ];
        }

        return $liste;
    }

    /** Position (Byte-Offset) der ersten Meldung im HTML oder null. */
    private function meldungsPosition(string $html): ?int
    {
        return preg_match('/<[a-z]+\b[^>]*class="[^"]*consentlaw-refresh-meldung(?:\s|")/i', $html, $m, PREG_OFFSET_CAPTURE) === 1
            ? $m[0][1]
            : null;
    }

    private function assertKeineMeldung(string $html): void
    {
        $this->assertSame([], $this->meldungen($html), 'Es darf keine Meldung erscheinen.');
        $this->assertStringNotContainsString('Neu geladen –', $html);
        $this->assertStringNotContainsString('Neu laden fehlgeschlagen', $html);
    }

    /** Genau eine Meldung mit Text, Rolle und Art (AK8: ok/fehler unterscheidbar). */
    private function assertMeldung(string $html, string $text, string $art): void
    {
        $meldungen = $this->meldungen($html);
        $this->assertCount(1, $meldungen, 'Genau eine Meldung erwartet.');

        $this->assertSame($text, $meldungen[0]['text']);
        $this->assertSame('status', $meldungen[0]['role'], 'Die Meldung braucht role="status".');

        $klassen = preg_split('/\s+/', trim($meldungen[0]['klassen']));
        $andere = $art === 'ok' ? 'fehler' : 'ok';
        $this->assertContains('consentlaw-refresh-meldung', $klassen);
        $this->assertContains('consentlaw-refresh-meldung--'.$art, $klassen);
        $this->assertNotContains('consentlaw-refresh-meldung--'.$andere, $klassen);

        $this->assertStringNotContainsString(self::GEHEIM, $html, 'Der Token darf nirgends im HTML stehen.');
        $this->assertStringNotContainsString('cURL', $html, 'Keine Exception-Texte in der Ausgabe.');
        $this->assertStringNotContainsString('/api/', $html, 'Keine API-URLs in der Ausgabe.');
    }

    private function assertMeldungVorInhalt(string $html, string $inhalt): void
    {
        $inhaltPos = strpos($html, $inhalt);
        $this->assertNotFalse($inhaltPos, "Vorbedingung: Inhalt '{$inhalt}' wird gerendert.");

        $meldungPos = $this->meldungsPosition($html);
        $this->assertNotNull($meldungPos, 'Meldung fehlt.');
        $this->assertLessThan($inhaltPos, $meldungPos, 'Die Meldung steht oberhalb des Inhalts.');
    }

    // ── AK1: Erfolg ──────────────────────────────────────────────────────────

    /** @return array<string, array{string, string}> */
    public static function erfolgsFaelle(): array
    {
        return [
            'Dokument' => ['impressum', 'Neu-Impressum'],
            'Jobs' => ['jobs', 'Neu-Stelle'],
        ];
    }

    // AK1
    #[DataProvider('erfolgsFaelle')]
    public function test_nach_erfolgreichem_neu_laden_meldet_die_seite_den_stand(string $typ, string $neu): void
    {
        $this->bereit();
        $this->apiAlle($this->payload($typ, 'Alt-Inhalt'));
        $seite = $this->seiteFuer($typ);
        $vorher = $this->seite($seite);
        $this->assertKeineMeldung($vorher);

        $this->reise(600);
        $this->apiAlle($this->payload($typ, $neu));
        [$status, $antwort] = $this->absenden($vorher, $typ);
        $html = $this->folge($status, $antwort);

        $this->assertSame('http://localhost'.$seite, (string) $antwort->headers->get('Location'));
        $this->assertMeldung($html, sprintf(self::TEXT_ERFOLG, '16.10.2026 12:10'), 'ok');
        $this->assertStringContainsString($neu, $html, 'Die Seite zeigt den neu geladenen Inhalt.');
        $this->assertMeldungVorInhalt($html, $neu);
    }

    // AK1
    public function test_der_stand_der_meldung_nutzt_die_zeitzone_der_app(): void
    {
        $this->bereit();
        $this->apiAlle($this->dokumentPayload('Alt-Inhalt'));
        $vorher = $this->seite('/impressum');

        // Wie der Kernel: Zeitzone aus der App-Config setzt die Standard-Zeitzone.
        $this->app['config']->set('app.timezone', 'Europe/Berlin');
        date_default_timezone_set('Europe/Berlin');
        Carbon::setTestNow(CarbonImmutable::parse('2026-10-16 10:00:00', 'UTC')); // 12:00 in Berlin (MESZ)

        $this->apiAlle($this->dokumentPayload('Neu-Impressum'));
        [$status, $antwort] = $this->absenden($vorher);
        $html = $this->folge($status, $antwort);

        $this->assertMeldung($html, sprintf(self::TEXT_ERFOLG, '16.10.2026 12:00'), 'ok');
    }

    // ── AK2: Störung mit Fallback ────────────────────────────────────────────

    /** @return array<string, array{string, mixed}> */
    public static function stoerungsFaelle(): array
    {
        return [
            'Dokument, Timeout' => ['impressum', self::STOERUNG],
            'Dokument, 5xx' => ['impressum', 503],
            'Jobs, Timeout' => ['jobs', self::STOERUNG],
            'Jobs, 5xx' => ['jobs', 503],
        ];
    }

    // AK2
    #[DataProvider('stoerungsFaelle')]
    public function test_bei_stoerung_mit_gespeichertem_stand_nennt_die_meldung_diesen_stand(string $typ, mixed $stoerung): void
    {
        $this->bereit();
        $this->apiAlle($this->payload($typ, 'Alt-Inhalt'));
        $seite = $this->seiteFuer($typ);
        $vorher = $this->seite($seite); // legt Cache und Fallback-Stand an (16.10.2026 12:00)

        $this->cacheAblaufenLassen();
        $this->apiAlle($stoerung);
        [$status, $antwort] = $this->absenden($vorher, $typ);
        $html = $this->folge($status, $antwort);

        $this->assertMeldung($html, sprintf(self::TEXT_STOERUNG_MIT_STAND, '16.10.2026 12:00'), 'fehler');
        $this->assertStringContainsString('Alt-Inhalt', $html, 'Der gespeicherte Stand wird weiter angezeigt.');
        $this->assertMeldungVorInhalt($html, 'Alt-Inhalt');
    }

    // ── AK3: Störung ohne Fallback ───────────────────────────────────────────

    // AK3
    #[DataProvider('stoerungsFaelle')]
    public function test_bei_stoerung_ohne_gespeicherten_stand_nennt_die_meldung_das(string $typ, mixed $stoerung): void
    {
        $this->bereit();
        $this->apiAlle($stoerung);

        $html = $this->neuLadenUndZurueck($this->seiteFuer($typ), $typ);

        $this->assertMeldung($html, self::TEXT_STOERUNG_OHNE_STAND, 'fehler');
        $this->assertStringNotContainsString('Alt-Inhalt', $html);
    }

    // ── AK4: Nicht veröffentlicht ────────────────────────────────────────────

    /** @return array<string, array{string}> */
    public static function typen(): array
    {
        return ['Dokument' => ['impressum'], 'Jobs' => ['jobs']];
    }

    // AK4
    #[DataProvider('typen')]
    public function test_bei_404_meldet_die_seite_nicht_veroeffentlicht_oder_token_ungueltig(string $typ): void
    {
        $this->bereit();
        $this->apiAlle(404);

        $html = $this->neuLadenUndZurueck($this->seiteFuer($typ), $typ);

        $this->assertMeldung($html, self::TEXT_NICHT_VEROEFFENTLICHT, 'fehler');
    }

    // AK4
    public function test_404_hat_vorrang_vor_einem_vorhandenen_gespeicherten_stand(): void
    {
        $this->bereit();
        $this->apiAlle($this->dokumentPayload('Alt-Inhalt'));
        $vorher = $this->seite('/impressum');

        $this->apiAlle(404);
        [$status, $antwort] = $this->absenden($vorher);
        $html = $this->folge($status, $antwort);

        $this->assertMeldung($html, self::TEXT_NICHT_VEROEFFENTLICHT, 'fehler');
    }

    // ── AK5: Kein Token ──────────────────────────────────────────────────────

    // AK5
    public function test_ohne_token_meldet_die_seite_den_fehlenden_token_und_nennt_keinen_wert(): void
    {
        $this->bereit();
        $this->konfig('token', '');
        $this->apiAlle($this->dokumentPayload('Neu-Impressum'));
        $vorher = $this->seite('/impressum');

        [$status, $antwort] = $this->absenden($vorher);
        $html = $this->folge($status, $antwort);

        $this->assertMeldung($html, self::TEXT_KEIN_TOKEN, 'fehler');
        $this->assertSame(0, $this->anzahlAufrufe(), 'Ohne Token gibt es keinen API-Aufruf.');
    }

    // AK5
    #[DataProvider('tokenFreieMeldungen')]
    public function test_der_token_wert_steht_in_keiner_meldung(mixed $api): void
    {
        $this->bereit();
        $this->apiAlle($api);

        $html = $this->neuLadenUndZurueck('/impressum');

        $this->assertCount(1, $this->meldungen($html), 'Vorbedingung: Es gibt eine Meldung.');
        $this->assertStringNotContainsString(self::GEHEIM, $html);
        $this->assertStringNotContainsString('cURL', $html);
        $this->assertStringNotContainsString('timed out', $html);
        $this->assertStringNotContainsString('/api/', $html);
    }

    /** @return array<string, array{mixed}> */
    public static function tokenFreieMeldungen(): array
    {
        return ['Timeout' => [self::STOERUNG], '5xx' => [503], '404' => [404]];
    }

    // ── AK6: Einmalig ────────────────────────────────────────────────────────

    // AK6
    public function test_die_meldung_erscheint_nur_einmal(): void
    {
        $this->bereit();
        $this->apiAlle($this->dokumentPayload('Neu-Impressum'));

        $html = $this->neuLadenUndZurueck('/impressum');
        $this->assertCount(1, $this->meldungen($html), 'Vorbedingung: Die Meldung erscheint nach Neu laden.');

        $this->assertKeineMeldung($this->seite('/impressum'));
        $this->assertKeineMeldung($this->seite('/impressum'));
    }

    // AK6
    public function test_auch_eine_fehlermeldung_erscheint_nur_einmal(): void
    {
        $this->bereit();
        $this->apiAlle(404);

        $html = $this->neuLadenUndZurueck('/impressum');
        $this->assertCount(1, $this->meldungen($html));

        $this->assertKeineMeldung($this->seite('/impressum'));
    }

    // ── AK7: Nur am betroffenen Inhalt ───────────────────────────────────────

    private function gemischteSeiteVorbereiten(): void
    {
        $this->bereit();
        $this->api(['*' => function (Request $anfrage): array {
            $url = $anfrage->url();

            if (str_contains($url, '/api/jobs/')) {
                return $this->jobsPayload(['title' => 'JOB-TEXT']);
            }

            if (str_contains($url, '/api/datenschutz/')) {
                return $this->dokumentPayload('DS-TEXT');
            }

            return $this->dokumentPayload(str_contains($url, 'locale=en') ? 'IMP-EN' : 'IMP-DE');
        }]);
    }

    // AK7
    public function test_die_meldung_erscheint_nur_beim_impressum_in_standardsprache(): void
    {
        $this->gemischteSeiteVorbereiten();

        $html = $this->neuLadenUndZurueck('/gemischt', 'impressum');

        $this->assertCount(1, $this->meldungen($html), 'Genau eine Meldung auf der Seite.');
        $meldung = $this->meldungsPosition($html);
        $this->assertGreaterThan(strrpos($html, 'JOB-TEXT'), $meldung, 'Nicht bei den Jobs.');
        $this->assertLessThan(strpos($html, 'IMP-DE'), $meldung, 'Beim deutschen Impressum.');
    }

    // AK7
    public function test_die_meldung_erscheint_nur_bei_der_ausgeloesten_sprachvariante(): void
    {
        $this->gemischteSeiteVorbereiten();

        $html = $this->neuLadenUndZurueck('/gemischt', 'impressum', 'en');

        $this->assertCount(1, $this->meldungen($html), 'Genau eine Meldung auf der Seite.');
        $meldung = $this->meldungsPosition($html);
        $this->assertGreaterThan(strrpos($html, 'IMP-DE'), $meldung, 'Nicht beim deutschen Impressum.');
        $this->assertLessThan(strpos($html, 'IMP-EN'), $meldung, 'Beim englischen Impressum.');
    }

    // AK7
    public function test_die_meldung_erscheint_nur_bei_den_jobs(): void
    {
        $this->gemischteSeiteVorbereiten();

        $html = $this->neuLadenUndZurueck('/gemischt', 'jobs');

        $this->assertCount(1, $this->meldungen($html), 'Genau eine Meldung auf der Seite.');
        $this->assertLessThan(strpos($html, 'JOB-TEXT'), $this->meldungsPosition($html), 'Bei den Jobs.');
    }

    // ── AK8: Darstellung ─────────────────────────────────────────────────────

    /** Regelkörper des ersten <style>-Blocks für den Selektor (oder null). */
    private function regel(string $html, string $selektor): ?string
    {
        if (preg_match_all('#<style\b[^>]*>(.*?)</style>#si', $html, $blocks) === 0) {
            return null;
        }

        foreach ($blocks[1] as $css) {
            if (preg_match('/'.preg_quote($selektor, '/').'(?![\w-])[^{}]*\{([^}]*)\}/', $css, $m) === 1) {
                return trim($m[1]);
            }
        }

        return null;
    }

    // AK8
    public function test_das_partial_bringt_styles_fuer_meldung_ok_und_fehler_mit(): void
    {
        $this->bereit();
        $this->apiAlle($this->dokumentPayload('Alt-Inhalt'));

        $html = $this->seite('/impressum');

        $basis = $this->regel($html, '.consentlaw-refresh-meldung');
        $ok = $this->regel($html, '.consentlaw-refresh-meldung--ok');
        $fehler = $this->regel($html, '.consentlaw-refresh-meldung--fehler');

        $this->assertNotNull($basis, 'Regel .consentlaw-refresh-meldung im <style>-Block fehlt.');
        $this->assertNotNull($ok, 'Regel .consentlaw-refresh-meldung--ok im <style>-Block fehlt.');
        $this->assertNotNull($fehler, 'Regel .consentlaw-refresh-meldung--fehler im <style>-Block fehlt.');
        $this->assertNotSame('', $ok);
        $this->assertNotSame('', $fehler);
        $this->assertNotSame($ok, $fehler, 'Erfolg und Fehler müssen optisch unterscheidbar sein.');
    }

    // AK8
    public function test_der_meldungstext_wird_im_partial_escaped_ausgegeben(): void
    {
        $quelle = (string) file_get_contents(__DIR__.'/../src/Resources/views/components/partials/neu-laden.blade.php');

        $this->assertStringContainsString('consentlaw-refresh-meldung', $quelle, 'Vorbedingung: Das Partial gibt die Meldung aus.');
        $this->assertStringNotContainsString('{!!', $quelle, 'Der Meldungstext darf nicht unescaped (`{!! !!}`) ausgegeben werden.');
    }

    // ── AK9: Ohne Session ────────────────────────────────────────────────────

    // AK9
    public function test_ohne_session_funktioniert_neu_laden_wie_bisher_ohne_meldung(): void
    {
        $this->bereit(mitSession: false);
        // Homepage ohne Session: weder StartSession noch CSRF in der Gruppe.
        $this->app['router']->middlewareGroup('web', []);
        $this->apiAlle($this->dokumentPayload('Alt-Inhalt'));
        $vorher = $this->seite('/impressum');

        $this->apiAlle($this->dokumentPayload('Neu-Impressum'));
        [$status, $antwort] = $this->absenden($vorher);

        $this->assertSame(302, $status, 'Rückleitung wie bisher, kein Fehler.');
        $this->assertSame('http://localhost/impressum', (string) $antwort->headers->get('Location'));
        $this->assertSame(2, $this->anzahlAufrufe(), 'Der Abruf findet statt.');

        $html = $this->seite('/impressum');
        $this->assertKeineMeldung($html);
        $this->assertStringContainsString('Neu-Impressum', $html);
        $this->assertSame([], $this->app['session']->driver()->all(), 'Ohne Session wird nichts in einen Session-Speicher geschrieben.');
    }

    // ── AK10: Schalter aus ───────────────────────────────────────────────────

    // AK10
    public function test_bei_ausgeschaltetem_schalter_erscheint_weder_button_noch_meldung(): void
    {
        $this->bereit();
        $this->apiAlle($this->dokumentPayload('Neu-Impressum'));
        $vorher = $this->seite('/impressum');

        // Vorbedingung: Mit Schalter an erscheint die Meldung (sonst wäre der Test trivial).
        $this->assertCount(1, $this->meldungen($this->neuLadenUndZurueck('/impressum')));

        [$status, $antwort] = $this->absenden($vorher);
        $this->assertSame(302, $status);

        // Die Meldung liegt jetzt in der Session; danach wird der Schalter ausgeschaltet.
        $this->konfig('refresh_button', false);
        $html = $this->seite((string) parse_url((string) $antwort->headers->get('Location'), PHP_URL_PATH));

        $this->assertStringContainsString('Neu-Impressum', $html, 'Vorbedingung: Das Dokument wird gerendert.');
        $this->assertStringNotContainsString('consentlaw-refresh', $html, 'Weder Button noch Meldung noch Styles.');
        $this->assertStringNotContainsString('Neu laden', $html);
        $this->assertStringNotContainsString('Neu geladen', $html);
        $this->assertSame([], $this->meldungen($html));
    }

    // ── AK11: Seite ohne Token ───────────────────────────────────────────────

    /** @return array<string, array{string}> */
    public static function umgebungen(): array
    {
        return ['local' => ['local'], 'production' => ['production']];
    }

    // AK11
    #[DataProvider('umgebungen')]
    public function test_komponenten_rendern_ohne_token_mit_200_und_button_ohne_api_aufruf(string $umgebung): void
    {
        $this->bereit(umgebung: $umgebung);
        $this->konfig('token', '');
        $this->apiAlle($this->dokumentPayload('Inhalt'));

        foreach (['/impressum', '/jobs', '/gemischt'] as $pfad) {
            $html = $this->seite($pfad); // seite() prüft 200
            $this->assertStringContainsString('consentlaw-refresh', $html, "Button auf {$pfad} sichtbar.");
            $this->assertStringNotContainsString('Inhalt', $html);
            $this->assertKeineMeldung($html);
        }

        $this->assertSame(0, $this->anzahlAufrufe(), 'Ohne Token gibt es keinen API-Aufruf.');
    }

    // AK11
    public function test_document_komponente_und_slot_fall_ohne_token(): void
    {
        $this->bereit();
        $this->konfig('token', '');
        $this->app['router']->middleware('web')->get('/doc', static fn (): string => Blade::render(
            '<x-consentlaw::document type="agb">Slot-Inhalt</x-consentlaw::document><x-consentlaw::jobs>Jobs-Slot</x-consentlaw::jobs>'
        ));

        $html = $this->seite('/doc');

        $this->assertStringContainsString('Slot-Inhalt', $html, 'Wie „kein Inhalt verfügbar“: der Slot erscheint.');
        $this->assertStringContainsString('Jobs-Slot', $html);
        $this->assertStringContainsString('consentlaw-refresh', $html);
        $this->assertSame(0, $this->anzahlAufrufe());
    }

    // AK11
    public function test_ohne_token_steht_eine_warnung_im_log(): void
    {
        $this->bereit();
        $this->konfig('token', '');

        $this->seite('/impressum');

        $this->assertMatchesRegularExpression('/^WARNING .*CONSENTLAW_TOKEN fehlt/m', $this->logText());
    }

    // AK11
    public function test_ohne_token_funktioniert_der_ablauf_auf_der_komponenten_seite_ende_zu_ende(): void
    {
        $this->bereit();
        $this->konfig('token', '');

        $html = $this->neuLadenUndZurueck('/jobs', 'jobs');

        $this->assertMeldung($html, self::TEXT_KEIN_TOKEN, 'fehler');
        $this->assertSame(0, $this->anzahlAufrufe());
    }

    // AK11 (Nicht-Ziel: die PHP-API wirft weiter)
    public function test_die_php_api_wirft_ohne_token_weiter_missing_token_exception(): void
    {
        $this->bereit();
        $this->konfig('token', '');

        $this->expectException(\ITHilbert\ConsentLaw\Exceptions\MissingTokenException::class);

        $this->client()->document('impressum');
    }

    // ── Randfälle ────────────────────────────────────────────────────────────

    // Randfall: Rate-Limit → 429 wie bisher, keine Meldung
    public function test_bei_rate_limit_gibt_es_429_und_keine_meldung(): void
    {
        $this->bereit();
        $this->apiAlle($this->dokumentPayload('Neu-Impressum'));
        $vorher = $this->seite('/impressum');

        for ($i = 1; $i <= 6; $i++) {
            [$status] = $this->absenden($vorher);
            $this->assertSame(302, $status, "Anfrage {$i} von 6 ist erlaubt.");
            $this->assertCount(1, $this->meldungen($this->seite('/impressum')), 'Zeigt und verbraucht die Meldung.');
        }

        [$status] = $this->absenden($vorher);
        $this->assertSame(429, $status);

        $this->assertKeineMeldung($this->seite('/impressum'));
    }
}
