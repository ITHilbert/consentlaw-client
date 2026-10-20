<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Tests;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Button „Neu laden" – AK4 bis AK6 aus .project/features/neu-laden-button/spec.md
 * (unverändert), AK1, AK2, AK3, AK7, AK8, AK9 und AK10 aus
 * .project/features/neu-laden-app-test/spec.md.
 *
 * VERTRAG:
 *
 *  Config
 *   - `consentlaw.refresh_button` (bool), env `APP_TEST`, Standard false. Ein eigener
 *     ENV-Schalter des Packages (`CONSENTLAW_REFRESH_BUTTON`) existiert nicht mehr (AK8).
 *   - Freigabe = Schalter an, in jeder Umgebung (auch `production`, AK2). Die
 *     Freigabe-Logik liegt an EINER Stelle (Aktion und Partial/Komponenten nutzen sie).
 *
 *  Route (wird beim Booten des Providers IMMER registriert, unabhängig vom Schalter, AK10)
 *   - Der Limiter `consentlaw-neu-laden` wird ebenfalls immer definiert. Grund: Eine
 *     gecachte Route (`route:cache`) trägt `throttle:consentlaw-neu-laden`; fehlte der
 *     Limiter bei Schalter aus, gäbe es eine MissingRateLimiterException (500).
 *   - Die Freigabe wird erst in der AKTION und im Partial geprüft: Ohne Schalter kein
 *     Button, und ein POST antwortet mit 404 ohne API-Aufruf (AK1, AK7, AK10) – nie 405/500.
 *   - `POST consentlaw/neu-laden/{typ}`, Name `consentlaw.neu-laden`.
 *   - Middleware: Gruppe `web` (Session + CSRF) und ein Limiter, 6 Anfragen/Minute je IP
 *     (Test-App kennt Alias `throttle` und benannte Limiter über die RateLimiter-Facade).
 *   - `typ` ∈ impressum, datenschutz, agb, cookie-richtlinie, avv, jobs; alles andere
 *     → 404 (oder 422), ohne API-Aufruf.
 *   - Aktion: ruft `refresh()` des passenden Endpoints (Cache, Sperrzeit und Fallback-Datei
 *     werden umgangen/aktualisiert) und antwortet mit Redirect 302 zurück (`back()`).
 *   - Ist die Freigabe beim Aufruf nicht (mehr) gegeben → 404 ohne API-Aufruf (AK7).
 *   - Rückleitung nur auf die eigene Seite: Ein Referer auf einen fremden Host oder
 *     Eingabeparameter dürfen den Redirect nicht umlenken (Ziel dann `/` bzw. eigener Host).
 *   - Eine Störung beim Neu-laden führt NICHT zu einer Fehlerseite: ebenfalls 302.
 *   - Nur POST (GET → 405).
 *
 *  Button (Blade-Teilansicht, eingebunden in `document` und `jobs`, auch im Slot-Fall)
 *   - Steht im HTML VOR dem Inhalt bzw. vor dem Slot (AK3).
 *   - `<form method="post" action="{route('consentlaw.neu-laden', typ)}">` mit CSRF-Feld
 *     (`@csrf`, Eingabefeld `_token`) und einem `<button>` mit dem Text „Neu laden".
 *   - Die Klasse `consentlaw-refresh` trägt das Formular oder ein Element darin.
 *   - Das Partial bringt einen `<style>`-Block mit Regeln für `.consentlaw-refresh` mit (AK9).
 *
 * Testaufbau: Der Config-Schalter wird beim Booten des Providers gelesen. Die Tests
 * bauen die App deshalb mit {@see AppTestCase::appNeuBauen()} neu auf (Umgebung + Config
 * vor dem Boot). Anfragen laufen echt durch Router und Middleware (`web` mit Session und
 * CSRF) der Test-App; Antworten werden über {@see self::sende()} als Status ausgewertet.
 * Die Umgebung ist bei Anfragen `local` (in `testing` überspringt Laravel die CSRF-Prüfung).
 */
final class NeuLadenButtonTest extends AppTestCase
{
    private const TYPEN = ['impressum', 'datenschutz', 'agb', 'cookie-richtlinie', 'avv'];

    private const ALT = 'Stand-Alt-Text';

    private const NEU = 'Stand-Neu-Text';

    // ── Hilfen ───────────────────────────────────────────────────────────────

    private function freigeben(string $umgebung = 'local'): void
    {
        $this->appNeuBauen($umgebung, ['refresh_button' => true]);
    }

    /** @return list<DOMElement> Formulare, die die Klasse `consentlaw-refresh` selbst oder in einem Kind tragen. */
    private function formulare(string $html): array
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><body>'.$html.'</body>');
        libxml_clear_errors();

        $klasse = "contains(concat(' ', normalize-space(@class), ' '), ' consentlaw-refresh ')";
        $treffer = (new DOMXPath($dom))->query("//form[{$klasse} or .//*[{$klasse}]]");

        return $treffer === false ? [] : array_values(iterator_to_array($treffer));
    }

    private function assertKeinButton(string $html): void
    {
        $this->assertStringNotContainsString('consentlaw-refresh', $html);
        $this->assertStringNotContainsString('neu-laden', $html);
        $this->assertStringNotContainsString('Neu laden', $html);
        $this->assertSame([], $this->formulare($html));
    }

    /** Ohne Freigabe: POST → 404 (nie 405/419/500) und kein API-Aufruf (AK1, AK7, AK10). */
    private function assertNeuLadenGesperrt(string $typ = 'impressum'): void
    {
        $vorher = $this->anzahlAufrufe();

        [$status] = $this->postNeuLaden($typ);

        $this->assertSame(404, $status, 'Ohne Schalter muss ein POST 404 liefern (nicht 405, 419 oder 500).');
        $this->assertSame($vorher, $this->anzahlAufrufe(), 'Ohne Schalter darf kein API-Aufruf stattfinden.');
    }

    /** Position des Formulars (öffnendes Tag) im HTML. */
    private function formularPosition(string $html): int
    {
        $pos = strpos($html, '<form');
        $this->assertNotFalse($pos, 'Kein Formular im HTML gefunden.');

        return $pos;
    }

    /** Prüft genau ein Formular mit POST, Ziel-URL, CSRF-Feld und Button-Text; liefert es zurück. */
    private function assertButtonFuer(string $typ, string $html): DOMElement
    {
        $formulare = $this->formulare($html);
        $this->assertCount(1, $formulare, "Genau ein Neu-laden-Formular für '{$typ}' erwartet.");
        $form = $formulare[0];

        $this->assertSame('post', strtolower($form->getAttribute('method')));
        $this->assertSame(route('consentlaw.neu-laden', $typ), $form->getAttribute('action'));
        $this->assertStringEndsWith('/consentlaw/neu-laden/'.$typ, $form->getAttribute('action'));

        $xpath = new DOMXPath($form->ownerDocument);
        $token = $xpath->query('.//input[@type="hidden"][@name="_token"]', $form);
        $this->assertSame(1, $token->length, 'CSRF-Feld `_token` fehlt im Formular.');
        $this->assertNotSame('', $token->item(0)->getAttribute('value'), 'CSRF-Token darf nicht leer sein.');

        $buttons = $xpath->query('.//button', $form);
        $this->assertGreaterThanOrEqual(1, $buttons->length, 'Das Formular braucht einen <button>.');
        $this->assertSame('Neu laden', trim(preg_replace('/\s+/', ' ', $buttons->item(0)->textContent)));

        return $form;
    }

    /** Startet die Session (wie StartSession) und liefert das CSRF-Token – die Blade-Ausgabe nutzt es. */
    private function sessionToken(): string
    {
        $store = $this->app['session']->driver();
        $store->start();

        return (string) $store->token();
    }

    /**
     * Schickt einen POST an die Neu-laden-Route durch Router und Middleware.
     *
     * @param  array<string, mixed>  $eingabe  zusätzliche Formularfelder (auch Arrays, z. B. für manipulierte `locale`)
     * @param  array<string, string>  $kopf  zusätzliche Server-Variablen (z. B. HTTP_REFERER, REMOTE_ADDR)
     * @return array{0: int, 1: ?SymfonyResponse} Status und (bei Erfolg) die Antwort
     */
    private function postNeuLaden(string $typ, bool $mitToken = true, array $eingabe = [], array $kopf = [], ?string $referer = 'http://localhost/seite'): array
    {
        $store = $this->app['session']->driver();
        $store->start();
        $token = (string) $store->token();
        $store->save();

        $server = $kopf + ['REMOTE_ADDR' => '203.0.113.5'];
        if ($referer !== null) {
            $server['HTTP_REFERER'] = $referer;
        }

        $daten = $eingabe + ($mitToken ? ['_token' => $token] : []);
        $anfrage = HttpRequest::create('http://localhost/consentlaw/neu-laden/'.$typ, 'POST', $daten, [$store->getName() => $store->getId()], [], $server);

        return $this->sende($anfrage);
    }

    /** @return array{0: int, 1: ?SymfonyResponse} */
    private function sende(HttpRequest $anfrage): array
    {
        $this->app->instance('request', $anfrage);

        try {
            $antwort = $this->app['router']->dispatch($anfrage);
        } catch (TokenMismatchException) {
            return [419, null]; // wie der Exception-Handler von Laravel
        } catch (ValidationException $e) {
            return [$e->status, null];
        } catch (HttpExceptionInterface $e) {
            return [$e->getStatusCode(), null];
        }

        return [$antwort->getStatusCode(), $antwort];
    }

    private function ziel(?SymfonyResponse $antwort): string
    {
        $this->assertNotNull($antwort);

        return (string) $antwort->headers->get('Location');
    }

    // ── AK1: Standard aus ────────────────────────────────────────────────────

    // AK1
    public function test_standardmaessig_ist_der_schalter_aus(): void
    {
        $this->assertFalse((require __DIR__.'/../src/Config/consentlaw.php')['refresh_button'] ?? null);
        $this->assertFalse($this->app['config']->get('consentlaw.refresh_button'));
    }

    // AK8
    public function test_umgebungsvariable_app_test_false_laesst_den_schalter_aus(): void
    {
        $this->setzeUmgebung('APP_TEST', 'false');
        $this->appNeuBauen('local');
        $this->api(['*' => $this->dokumentPayload(self::NEU)]);

        $this->assertFalse($this->app['config']->get('consentlaw.refresh_button'));
        $this->assertNeuLadenGesperrt();
    }

    // AK8
    public function test_umgebungsvariable_app_test_true_schaltet_den_button_ein(): void
    {
        $this->setzeUmgebung('APP_TEST', 'true');
        $this->appNeuBauen('local');

        $this->assertTrue($this->app['config']->get('consentlaw.refresh_button'));
        $this->assertTrue(Route::has('consentlaw.neu-laden'));
    }

    // AK8
    public function test_app_test_true_schaltet_den_button_auch_auf_production_ein(): void
    {
        $this->setzeUmgebung('APP_TEST', 'true');
        $this->appNeuBauen('production');

        $this->assertTrue($this->app['config']->get('consentlaw.refresh_button'));
        $this->assertTrue(Route::has('consentlaw.neu-laden'));
    }

    // AK8
    #[DataProvider('umgebungen')]
    public function test_alter_package_schalter_ohne_app_test_ist_wirkungslos(string $umgebung): void
    {
        $this->setzeUmgebung('CONSENTLAW_REFRESH_BUTTON', 'true');
        $this->appNeuBauen($umgebung);
        $this->api(['*' => $this->dokumentPayload(self::ALT)]);

        $this->assertFalse($this->app['config']->get('consentlaw.refresh_button'));

        $html = $this->rendere('<x-consentlaw::impressum />');
        $this->assertStringContainsString(self::ALT, $html, 'Vorbedingung: Das Dokument wird gerendert.');
        $this->assertKeinButton($html);
        $this->assertNeuLadenGesperrt();
    }

    // AK8
    public function test_alter_package_schalter_aendert_nichts_wenn_app_test_aus_ist(): void
    {
        $this->setzeUmgebung('CONSENTLAW_REFRESH_BUTTON', 'true');
        $this->setzeUmgebung('APP_TEST', 'false');
        $this->appNeuBauen('local');
        $this->api(['*' => $this->dokumentPayload(self::NEU)]);

        $this->assertFalse($this->app['config']->get('consentlaw.refresh_button'));
        $this->assertNeuLadenGesperrt();
    }

    // AK8
    public function test_die_config_kennt_keinen_eigenen_env_schalter_des_packages_mehr(): void
    {
        $quelle = (string) file_get_contents(__DIR__.'/../src/Config/consentlaw.php');

        $this->assertStringNotContainsString('CONSENTLAW_REFRESH_BUTTON', $quelle);
        $this->assertStringContainsString('APP_TEST', $quelle);
    }

    // AK1
    #[DataProvider('umgebungen')]
    public function test_app_test_false_hat_in_jeder_umgebung_keinen_button_und_antwortet_404(string $umgebung): void
    {
        $this->setzeUmgebung('APP_TEST', 'false');
        $this->appNeuBauen($umgebung);
        $this->api(['*' => $this->dokumentPayload(self::ALT)]);

        $this->assertKeinButton($this->rendere('<x-consentlaw::impressum />'));
        $this->assertSame(1, $this->anzahlAufrufe(), 'Nur der Abruf beim Rendern.');

        $this->assertNeuLadenGesperrt();
        $this->assertSame(1, $this->anzahlAufrufe(), 'Kein Neu laden.');
    }

    // AK1
    #[DataProvider('umgebungen')]
    public function test_ohne_app_test_hat_jede_umgebung_keinen_button_und_antwortet_404(string $umgebung): void
    {
        $this->appNeuBauen($umgebung);
        $this->api(['*' => $this->jobsPayload(['title' => 'Alt-Stelle'])]);

        $html = $this->rendere('<x-consentlaw::jobs />');
        $this->assertStringContainsString('Alt-Stelle', $html);
        $this->assertKeinButton($html);

        $this->assertNeuLadenGesperrt('jobs');
    }

    // AK1
    #[DataProvider('dokumentTypen')]
    public function test_ohne_schalter_zeigt_die_dokument_komponente_keinen_button(string $typ): void
    {
        $this->api(['*' => $this->dokumentPayload(self::ALT)]);

        $html = $this->rendere("<x-consentlaw::document type=\"{$typ}\" />");

        $this->assertStringContainsString(self::ALT, $html, 'Vorbedingung: Das Dokument wird gerendert.');
        $this->assertKeinButton($html);
    }

    // AK1
    public function test_ohne_schalter_zeigt_die_kurzform_keinen_button(): void
    {
        $this->api(['*' => $this->dokumentPayload(self::ALT)]);

        $html = $this->rendere('<x-consentlaw::impressum />');

        $this->assertStringContainsString(self::ALT, $html);
        $this->assertKeinButton($html);
    }

    // AK1
    public function test_ohne_schalter_zeigt_die_jobs_komponente_keinen_button(): void
    {
        $this->api(['*' => $this->jobsPayload(['title' => 'Alt-Stelle'])]);

        $html = $this->rendere('<x-consentlaw::jobs />');

        $this->assertStringContainsString('Alt-Stelle', $html, 'Vorbedingung: Die Jobs werden gerendert.');
        $this->assertKeinButton($html);
    }

    // AK1
    public function test_ohne_schalter_ist_auch_der_slot_fall_ohne_button(): void
    {
        $this->api(['*' => 404]);

        $this->assertKeinButton($this->rendere('<x-consentlaw::impressum>Slot-Inhalt</x-consentlaw::impressum>'));
        $this->assertKeinButton($this->rendere('<x-consentlaw::jobs>Slot-Inhalt</x-consentlaw::jobs>'));
    }

    // AK1
    public function test_ohne_schalter_antwortet_ein_post_mit_404_ohne_api_aufruf(): void
    {
        $this->api(['*' => $this->dokumentPayload(self::NEU)]);

        $this->assertNeuLadenGesperrt();
        $this->assertSame(0, $this->anzahlAufrufe());
    }

    // ── AK2: Auch auf Production ─────────────────────────────────────────────

    // AK2
    #[DataProvider('dokumentTypen')]
    public function test_auf_production_zeigt_die_dokument_komponente_mit_schalter_den_button(string $typ): void
    {
        $this->freigeben('production');
        $this->api(['*' => $this->dokumentPayload(self::ALT)]);
        $this->sessionToken();

        $html = $this->rendere("<x-consentlaw::document type=\"{$typ}\" />");

        $this->assertStringContainsString(self::ALT, $html);
        $this->assertButtonFuer($typ, $html);
    }

    // AK2
    public function test_auf_production_zeigt_die_jobs_komponente_mit_schalter_den_button(): void
    {
        $this->freigeben('production');
        $this->api(['*' => $this->jobsPayload(['title' => 'Alt-Stelle'])]);
        $this->sessionToken();

        $html = $this->rendere('<x-consentlaw::jobs />');

        $this->assertStringContainsString('Alt-Stelle', $html);
        $this->assertButtonFuer('jobs', $html);
    }

    // AK2
    public function test_auf_production_hat_auch_der_slot_fall_den_button(): void
    {
        $this->freigeben('production');
        $this->api(['*' => 404]);
        $this->sessionToken();

        $this->assertButtonFuer('datenschutz', $this->rendere('<x-consentlaw::datenschutz>Slot-Inhalt</x-consentlaw::datenschutz>'));
        $this->assertButtonFuer('jobs', $this->rendere('<x-consentlaw::jobs>Slot-Inhalt</x-consentlaw::jobs>'));
    }

    // AK2
    public function test_auf_production_ist_die_route_mit_schalter_registriert(): void
    {
        $this->freigeben('production');

        $this->assertTrue(Route::has('consentlaw.neu-laden'));
        $this->assertStringEndsWith('/consentlaw/neu-laden/impressum', route('consentlaw.neu-laden', 'impressum'));
    }

    // AK2
    public function test_auf_production_funktioniert_neu_laden_fuer_dokumente(): void
    {
        $this->freigeben('production');
        $this->api(['*' => $this->dokumentPayload(self::ALT)]);
        $this->rendere('<x-consentlaw::impressum />');

        $this->api(['*' => $this->dokumentPayload(self::NEU)]);
        [$status, $antwort] = $this->postNeuLaden('impressum', referer: 'http://localhost/impressum');

        $this->assertSame(302, $status);
        $this->assertSame('http://localhost/impressum', $this->ziel($antwort));
        $this->assertSame(2, $this->anzahlAufrufe());
        $html = $this->rendere('<x-consentlaw::impressum />');
        $this->assertStringContainsString(self::NEU, $html);
        $this->assertStringNotContainsString(self::ALT, $html);
    }

    // AK2
    public function test_auf_production_funktioniert_neu_laden_fuer_jobs(): void
    {
        $this->freigeben('production');
        $this->api(['*' => $this->jobsPayload(['title' => 'Alt-Stelle'])]);
        $this->rendere('<x-consentlaw::jobs />');

        $this->api(['*' => $this->jobsPayload(['title' => 'Neu-Stelle'])]);
        [$status] = $this->postNeuLaden('jobs');

        $this->assertSame(302, $status);
        $this->assertSame(2, $this->anzahlAufrufe());
        $this->assertStringContainsString('Neu-Stelle', $this->rendere('<x-consentlaw::jobs />'));
    }

    // AK2
    public function test_auf_production_ist_ueber_app_test_alles_wirksam(): void
    {
        $this->setzeUmgebung('APP_TEST', 'true');
        $this->appNeuBauen('production');
        $this->api(['*' => $this->dokumentPayload(self::ALT)]);
        $this->sessionToken();

        $this->assertButtonFuer('agb', $this->rendere('<x-consentlaw::agb />'));

        $this->api(['*' => $this->dokumentPayload(self::NEU)]);
        [$status] = $this->postNeuLaden('agb');
        $this->assertSame(302, $status);
        $this->assertStringContainsString(self::NEU, $this->rendere('<x-consentlaw::agb />'));
    }

    // AK2
    public function test_auf_production_bleibt_der_csrf_schutz_aktiv(): void
    {
        $this->freigeben('production');
        $this->api(['*' => $this->dokumentPayload(self::NEU)]);

        [$status] = $this->postNeuLaden('impressum', mitToken: false);

        $this->assertSame(419, $status);
        $this->assertSame(0, $this->anzahlAufrufe());
    }

    // ── AK3: Button sichtbar und oben ─────────────────────────────────────────────────

    /** @return array<string, array{string}> */
    public static function umgebungen(): array
    {
        return ['local' => ['local'], 'testing' => ['testing'], 'staging' => ['staging'], 'production' => ['production']];
    }

    // AK3
    #[DataProvider('umgebungen')]
    public function test_mit_schalter_ist_in_jeder_umgebung_die_route_registriert(string $umgebung): void
    {
        $this->freigeben($umgebung);

        $this->assertTrue(Route::has('consentlaw.neu-laden'));
        $this->assertStringEndsWith('/consentlaw/neu-laden/agb', route('consentlaw.neu-laden', 'agb'));
    }

    // AK3
    #[DataProvider('umgebungen')]
    public function test_dokument_komponente_zeigt_den_button_mit_csrf_und_typ(string $umgebung): void
    {
        $this->freigeben($umgebung);
        $this->api(['*' => $this->dokumentPayload(self::ALT)]);
        $this->sessionToken();

        $html = $this->rendere('<x-consentlaw::document type="impressum" />');

        $this->assertStringContainsString(self::ALT, $html, 'Der Inhalt bleibt neben dem Button erhalten.');
        $this->assertButtonFuer('impressum', $html);
    }

    // AK3
    #[DataProvider('dokumentTypen')]
    public function test_jede_dokument_kurzform_uebermittelt_ihren_eigenen_typ(string $typ): void
    {
        $this->freigeben();
        $this->api(['*' => $this->dokumentPayload(self::ALT)]);
        $this->sessionToken();

        $html = $this->rendere("<x-consentlaw::{$typ} />");

        $this->assertButtonFuer($typ, $html);
    }

    // AK3
    public function test_das_csrf_token_im_button_ist_das_der_sitzung(): void
    {
        $this->freigeben();
        $this->api(['*' => $this->dokumentPayload(self::ALT)]);
        $token = $this->sessionToken();

        $form = $this->assertButtonFuer('impressum', $this->rendere('<x-consentlaw::impressum />'));

        $feld = (new DOMXPath($form->ownerDocument))->query('.//input[@name="_token"]', $form)->item(0);
        $this->assertSame($token, $feld->getAttribute('value'));
    }

    // AK3
    #[DataProvider('umgebungen')]
    public function test_jobs_komponente_zeigt_den_button_mit_typ_jobs(string $umgebung): void
    {
        $this->freigeben($umgebung);
        $this->api(['*' => $this->jobsPayload(['title' => 'Alt-Stelle'])]);
        $this->sessionToken();

        $html = $this->rendere('<x-consentlaw::jobs />');

        $this->assertStringContainsString('Alt-Stelle', $html);
        $this->assertButtonFuer('jobs', $html);
    }

    // AK3
    #[DataProvider('slotFaelle')]
    public function test_button_erscheint_auch_ohne_inhalt_im_slot_fall(string $typ, mixed $apiAntwort): void
    {
        $this->freigeben();
        $this->api(['*' => $apiAntwort]);
        $this->sessionToken();

        $blade = $typ === 'jobs'
            ? '<x-consentlaw::jobs>Slot-Inhalt</x-consentlaw::jobs>'
            : "<x-consentlaw::{$typ}>Slot-Inhalt</x-consentlaw::{$typ}>";

        $html = $this->rendere($blade);

        $this->assertStringContainsString('Slot-Inhalt', $html, 'Der Slot bleibt erhalten.');
        $this->assertButtonFuer($typ, $html);
    }

    // AK3
    public function test_button_erscheint_auch_bei_leerer_jobliste(): void
    {
        $this->freigeben();
        $this->api(['*' => ['jobs' => []]]);
        $this->sessionToken();

        $this->assertButtonFuer('jobs', $this->rendere('<x-consentlaw::jobs />'));
    }

    // AK3
    #[DataProvider('dokumentTypen')]
    public function test_button_steht_vor_dem_dokumentinhalt(string $typ): void
    {
        $this->freigeben();
        $this->api(['*' => $this->dokumentPayload(self::ALT)]);
        $this->sessionToken();

        $html = $this->rendere("<x-consentlaw::{$typ} />");

        $inhalt = strpos($html, self::ALT);
        $this->assertNotFalse($inhalt, 'Vorbedingung: Der Inhalt wird gerendert.');
        $this->assertLessThan($inhalt, $this->formularPosition($html), 'Das Formular muss vor dem Inhalt stehen.');
    }

    // AK3
    public function test_button_steht_in_der_document_komponente_vor_dem_inhalt(): void
    {
        $this->freigeben();
        $this->api(['*' => $this->dokumentPayload(self::ALT)]);
        $this->sessionToken();

        $html = $this->rendere('<x-consentlaw::document type="datenschutz" />');

        $this->assertLessThan(strpos($html, self::ALT), $this->formularPosition($html));
    }

    // AK3
    public function test_button_steht_vor_den_stellenangeboten(): void
    {
        $this->freigeben();
        $this->api(['*' => $this->jobsPayload(['title' => 'Alt-Stelle'])]);
        $this->sessionToken();

        $html = $this->rendere('<x-consentlaw::jobs />');

        $inhalt = strpos($html, 'Alt-Stelle');
        $this->assertNotFalse($inhalt);
        $this->assertLessThan($inhalt, $this->formularPosition($html), 'Das Formular muss vor den Jobs stehen.');
    }

    // AK3
    #[DataProvider('slotFaelle')]
    public function test_button_steht_im_slot_fall_vor_dem_slot(string $typ, mixed $apiAntwort): void
    {
        $this->freigeben();
        $this->api(['*' => $apiAntwort]);
        $this->sessionToken();

        $html = $this->rendere("<x-consentlaw::{$typ}>Slot-Inhalt</x-consentlaw::{$typ}>");

        $slot = strpos($html, 'Slot-Inhalt');
        $this->assertNotFalse($slot, 'Vorbedingung: Der Slot wird gerendert.');
        $this->assertLessThan($slot, $this->formularPosition($html), 'Das Formular muss vor dem Slot stehen.');
    }

    // AK3
    public function test_button_steht_vor_dem_inhalt_auch_auf_production(): void
    {
        $this->freigeben('production');
        $this->api(['*' => $this->dokumentPayload(self::ALT)]);
        $this->sessionToken();

        $html = $this->rendere('<x-consentlaw::impressum />');

        $this->assertLessThan(strpos($html, self::ALT), $this->formularPosition($html));
    }

    // ── AK9: Eigene Darstellung ──────────────────────────────────────────────

    /** @return string Inhalt des ersten <style>-Blocks mit Regeln für `.consentlaw-refresh` (oder ''). */
    private function stylesFuerButton(string $html): string
    {
        if (preg_match_all('#<style\b[^>]*>(.*?)</style>#si', $html, $treffer) === 0) {
            return '';
        }

        foreach ($treffer[1] as $css) {
            if (str_contains($css, '.consentlaw-refresh')) {
                return $css;
            }
        }

        return '';
    }

    // AK9
    #[DataProvider('dokumentTypen')]
    public function test_dokument_bringt_styles_fuer_den_button_mit(string $typ): void
    {
        $this->freigeben();
        $this->api(['*' => $this->dokumentPayload(self::ALT)]);
        $this->sessionToken();

        $html = $this->rendere("<x-consentlaw::{$typ} />");

        $css = $this->stylesFuerButton($html);
        $this->assertNotSame('', $css, 'Ein <style>-Block mit Regeln für .consentlaw-refresh fehlt.');
        $this->assertMatchesRegularExpression('/\.consentlaw-refresh[^{]*\{[^}]+:[^}]+\}/', $css, 'Die Regel braucht Deklarationen.');
        $this->assertButtonFuer($typ, $html);
    }

    // AK9
    public function test_jobs_bringen_styles_fuer_den_button_mit(): void
    {
        $this->freigeben();
        $this->api(['*' => $this->jobsPayload(['title' => 'Alt-Stelle'])]);
        $this->sessionToken();

        $html = $this->rendere('<x-consentlaw::jobs />');

        $this->assertNotSame('', $this->stylesFuerButton($html), 'Ein <style>-Block mit Regeln für .consentlaw-refresh fehlt.');
        $this->assertButtonFuer('jobs', $html);
    }

    // AK9
    public function test_styles_sind_auch_im_slot_fall_und_auf_production_da(): void
    {
        $this->freigeben('production');
        $this->api(['*' => 404]);
        $this->sessionToken();

        $this->assertNotSame('', $this->stylesFuerButton($this->rendere('<x-consentlaw::impressum>Slot</x-consentlaw::impressum>')));
        $this->assertNotSame('', $this->stylesFuerButton($this->rendere('<x-consentlaw::jobs>Slot</x-consentlaw::jobs>')));
    }

    // AK9
    public function test_die_klasse_consentlaw_refresh_bleibt_am_formular_oder_darin(): void
    {
        $this->freigeben();
        $this->api(['*' => $this->dokumentPayload(self::ALT)]);
        $this->sessionToken();

        // formulare() findet nur Formulare, die die Klasse selbst oder in einem Kind tragen.
        $this->assertCount(1, $this->formulare($this->rendere('<x-consentlaw::impressum />')));
    }

    // AK9
    public function test_ohne_schalter_gibt_es_auch_keine_styles(): void
    {
        $this->api(['*' => $this->dokumentPayload(self::ALT)]);

        $html = $this->rendere('<x-consentlaw::impressum />');

        $this->assertSame('', $this->stylesFuerButton($html));
        $this->assertStringNotContainsString('<style', $html);
    }

    // ── AK4: Neu laden ───────────────────────────────────────────────────────

    // AK4
    public function test_neu_laden_holt_das_dokument_frisch_und_leitet_zurueck(): void
    {
        $this->freigeben();
        $this->api(['*' => $this->dokumentPayload(self::ALT)]);
        $this->assertStringContainsString(self::ALT, $this->rendere('<x-consentlaw::impressum />'));
        $this->assertSame(1, $this->anzahlAufrufe(), 'Vorbedingung: Alter Stand ist gecacht.');

        $this->api(['*' => $this->dokumentPayload(self::NEU)]);
        [$status, $antwort] = $this->postNeuLaden('impressum', referer: 'http://localhost/impressum');

        $this->assertSame(302, $status);
        $this->assertSame('http://localhost/impressum', $this->ziel($antwort), 'Rückleitung auf die vorherige Seite.');
        $this->assertSame(2, $this->anzahlAufrufe(), 'Genau ein zusätzlicher HTTP-Aufruf.');

        $html = $this->rendere('<x-consentlaw::impressum />');
        $this->assertStringContainsString(self::NEU, $html);
        $this->assertStringNotContainsString(self::ALT, $html);
        $this->assertSame(2, $this->anzahlAufrufe(), 'Danach liefert der aktualisierte Cache, ohne neuen Aufruf.');
    }

    // AK4
    public function test_neu_laden_aktualisiert_die_fallback_datei(): void
    {
        $this->freigeben();
        $this->api(['*' => $this->dokumentPayload(self::ALT)]);
        $this->rendere('<x-consentlaw::impressum />');
        $dateien = $this->fallbackDateien();
        $this->assertCount(1, $dateien, 'Vorbedingung: Fallback-Datei existiert.');
        $this->assertStringContainsString(self::ALT, (string) file_get_contents($dateien[0]));

        $this->api(['*' => $this->dokumentPayload(self::NEU)]);
        $this->postNeuLaden('impressum');

        $inhalt = (string) file_get_contents($this->fallbackDateien()[0]);
        $this->assertStringContainsString(self::NEU, $inhalt);
        $this->assertStringNotContainsString(self::ALT, $inhalt);
    }

    // AK4
    #[DataProvider('dokumentTypen')]
    public function test_neu_laden_funktioniert_fuer_jeden_dokumenttyp(string $typ): void
    {
        $this->freigeben();
        $this->api(['*' => $this->dokumentPayload(self::ALT)]);
        $this->rendere("<x-consentlaw::document type=\"{$typ}\" />");

        $this->api(['*' => $this->dokumentPayload(self::NEU)]);
        [$status] = $this->postNeuLaden($typ);

        $this->assertSame(302, $status);
        $this->assertSame(2, $this->anzahlAufrufe());
        $this->assertStringContainsString(self::NEU, $this->rendere("<x-consentlaw::document type=\"{$typ}\" />"));
    }

    // AK4
    public function test_neu_laden_holt_die_jobs_frisch(): void
    {
        $this->freigeben();
        $this->api(['*' => $this->jobsPayload(['title' => 'Alt-Stelle'])]);
        $this->assertStringContainsString('Alt-Stelle', $this->rendere('<x-consentlaw::jobs />'));

        $this->api(['*' => $this->jobsPayload(['title' => 'Neu-Stelle'])]);
        [$status, $antwort] = $this->postNeuLaden('jobs', referer: 'http://localhost/karriere');

        $this->assertSame(302, $status);
        $this->assertSame('http://localhost/karriere', $this->ziel($antwort));
        $this->assertSame(2, $this->anzahlAufrufe(), 'Genau ein zusätzlicher HTTP-Aufruf.');

        $html = $this->rendere('<x-consentlaw::jobs />');
        $this->assertStringContainsString('Neu-Stelle', $html);
        $this->assertStringNotContainsString('Alt-Stelle', $html);
        $this->assertSame(2, $this->anzahlAufrufe());
    }

    // AK4
    public function test_neu_laden_holt_nur_den_angeforderten_typ(): void
    {
        $this->freigeben();
        $this->api(['*' => $this->dokumentPayload(self::ALT)]);
        $this->rendere('<x-consentlaw::impressum />');
        $this->rendere('<x-consentlaw::datenschutz />');
        $vorher = $this->anzahlAufrufe();

        $this->api(['*' => $this->dokumentPayload(self::NEU)]);
        $this->postNeuLaden('impressum');

        $this->assertSame($vorher + 1, $this->anzahlAufrufe());
        $this->assertStringContainsString('/api/impressum/', (string) end($this->aufrufe));

        $this->rendere('<x-consentlaw::datenschutz />');
        $this->assertSame($vorher + 1, $this->anzahlAufrufe(), 'Der gecachte Datenschutz bleibt unberührt.');
    }

    // AK4
    public function test_neu_laden_umgeht_die_sperrzeit_nach_einer_stoerung(): void
    {
        $this->freigeben();
        $this->api(['*' => $this->dokumentPayload(self::ALT)]);
        $this->rendere('<x-consentlaw::impressum />');
        $this->cacheAblaufenLassen();
        $this->api(['*' => self::STOERUNG]);
        $this->rendere('<x-consentlaw::impressum />');
        $this->rendere('<x-consentlaw::impressum />');
        $this->assertSame(2, $this->anzahlAufrufe(), 'Vorbedingung: Sperrzeit aktiv (kein dritter Aufruf).');

        $this->api(['*' => $this->dokumentPayload(self::NEU)]);
        [$status] = $this->postNeuLaden('impressum');

        $this->assertSame(302, $status);
        $this->assertSame(3, $this->anzahlAufrufe(), 'Trotz Sperrzeit genau ein zusätzlicher Aufruf.');
        $this->assertStringContainsString(self::NEU, $this->rendere('<x-consentlaw::impressum />'));
    }

    // AK4
    public function test_neu_laden_umgeht_die_sperrzeit_auch_ohne_vorhandenen_inhalt(): void
    {
        $this->freigeben();
        $this->api(['*' => self::STOERUNG]);
        $this->rendere('<x-consentlaw::jobs />');
        $this->assertSame(1, $this->anzahlAufrufe(), 'Vorbedingung: Sperrzeit gesetzt.');

        $this->api(['*' => $this->jobsPayload(['title' => 'Neu-Stelle'])]);
        [$status] = $this->postNeuLaden('jobs');

        $this->assertSame(302, $status);
        $this->assertSame(2, $this->anzahlAufrufe());
        $this->assertStringContainsString('Neu-Stelle', $this->rendere('<x-consentlaw::jobs />'));
    }

    // AK4
    public function test_nach_dem_neu_laden_erscheint_ein_zuvor_fehlender_inhalt(): void
    {
        $this->freigeben();
        $this->api(['*' => 404]);
        $this->assertStringNotContainsString(self::NEU, $this->rendere('<x-consentlaw::agb />'));

        $this->api(['*' => $this->dokumentPayload(self::NEU)]);
        $this->postNeuLaden('agb');

        $this->assertStringContainsString(self::NEU, $this->rendere('<x-consentlaw::agb />'));
    }

    // ── AK4: Sprachvariante (locale) ─────────────────────────────────────────

    /** Fake-API, die je nach `locale` in der URL einen anderen Text liefert. */
    private function apiMehrsprachig(string $de, string $en): void
    {
        $this->api(['*' => fn (Request $anfrage): array => $this->dokumentPayload(
            str_contains($anfrage->url(), 'locale=en') ? $en : $de,
        )]);
    }

    /** Liefert das versteckte Feld `locale` des Formulars (oder null). */
    private function localeFeld(DOMElement $form): ?DOMElement
    {
        $felder = (new DOMXPath($form->ownerDocument))->query('.//input[@type="hidden"][@name="locale"]', $form);

        return $felder->length === 0 ? null : $felder->item(0);
    }

    // AK4
    #[DataProvider('dokumentTypen')]
    public function test_kurzform_mit_locale_uebermittelt_die_sprache_als_verstecktes_feld(string $typ): void
    {
        $this->freigeben();
        $this->api(['*' => $this->dokumentPayload(self::ALT)]);
        $this->sessionToken();

        $form = $this->assertButtonFuer($typ, $this->rendere("<x-consentlaw::{$typ} locale=\"en\" />"));

        $feld = $this->localeFeld($form);
        $this->assertNotNull($feld, 'Verstecktes Feld `locale` fehlt im Formular.');
        $this->assertSame('en', $feld->getAttribute('value'));
    }

    // AK4
    public function test_document_komponente_mit_locale_uebermittelt_die_sprache(): void
    {
        $this->freigeben();
        $this->api(['*' => $this->dokumentPayload(self::ALT)]);
        $this->sessionToken();

        $form = $this->assertButtonFuer('impressum', $this->rendere('<x-consentlaw::document type="impressum" locale="en" />'));

        $this->assertSame('en', $this->localeFeld($form)?->getAttribute('value'));
    }

    // AK4
    public function test_jobs_komponente_mit_locale_uebermittelt_die_sprache(): void
    {
        $this->freigeben();
        $this->api(['*' => $this->jobsPayload(['title' => 'Alt-Stelle'])]);
        $this->sessionToken();

        $form = $this->assertButtonFuer('jobs', $this->rendere('<x-consentlaw::jobs locale="en" />'));

        $this->assertSame('en', $this->localeFeld($form)?->getAttribute('value'));
    }

    // AK4
    public function test_locale_feld_erscheint_auch_im_slot_fall(): void
    {
        $this->freigeben();
        $this->api(['*' => 404]);
        $this->sessionToken();

        $impressum = $this->assertButtonFuer('impressum', $this->rendere('<x-consentlaw::impressum locale="en">Slot</x-consentlaw::impressum>'));
        $jobs = $this->assertButtonFuer('jobs', $this->rendere('<x-consentlaw::jobs locale="en">Slot</x-consentlaw::jobs>'));

        $this->assertSame('en', $this->localeFeld($impressum)?->getAttribute('value'));
        $this->assertSame('en', $this->localeFeld($jobs)?->getAttribute('value'));
    }

    // AK4
    public function test_ohne_locale_attribut_gibt_es_kein_locale_feld(): void
    {
        $this->freigeben();
        $this->api(['*' => $this->dokumentPayload(self::ALT)]);
        $this->sessionToken();

        $dokument = $this->assertButtonFuer('impressum', $this->rendere('<x-consentlaw::impressum />'));
        $this->api(['*' => $this->jobsPayload(['title' => 'Alt-Stelle'])]);
        $jobs = $this->assertButtonFuer('jobs', $this->rendere('<x-consentlaw::jobs />'));

        $this->assertNull($this->localeFeld($dokument), 'Ohne locale-Attribut darf es kein `locale`-Feld geben.');
        $this->assertNull($this->localeFeld($jobs), 'Ohne locale-Attribut darf es kein `locale`-Feld geben.');
    }

    // AK4
    public function test_neu_laden_mit_locale_holt_genau_diese_sprachvariante_und_laesst_die_standardvariante_unberuehrt(): void
    {
        $this->freigeben();
        $this->apiMehrsprachig('ALT-DE', 'ALT-EN');
        $this->assertStringContainsString('ALT-DE', $this->rendere('<x-consentlaw::impressum />'));
        $this->assertStringContainsString('ALT-EN', $this->rendere('<x-consentlaw::impressum locale="en" />'));
        $this->assertSame(2, $this->anzahlAufrufe(), 'Vorbedingung: beide Varianten sind gecacht.');

        $this->apiMehrsprachig('NEU-DE', 'NEU-EN');
        [$status, $antwort] = $this->postNeuLaden('impressum', eingabe: ['locale' => 'en'], referer: 'http://localhost/en/impressum');

        $this->assertSame(302, $status);
        $this->assertSame('http://localhost/en/impressum', $this->ziel($antwort));
        $this->assertSame(3, $this->anzahlAufrufe(), 'Genau ein zusätzlicher HTTP-Aufruf.');
        $this->assertStringContainsString('locale=en', (string) end($this->aufrufe), 'Der Aufruf geht an die API mit locale=en.');

        $en = $this->rendere('<x-consentlaw::impressum locale="en" />');
        $this->assertStringContainsString('NEU-EN', $en);
        $this->assertStringNotContainsString('ALT-EN', $en);

        $de = $this->rendere('<x-consentlaw::impressum />');
        $this->assertStringContainsString('ALT-DE', $de, 'Die Standardvariante bleibt unberührt.');
        $this->assertStringNotContainsString('NEU-DE', $de);
        $this->assertSame(3, $this->anzahlAufrufe(), 'Danach liefert der Cache, ohne neuen Aufruf.');
    }

    // AK4
    public function test_neu_laden_ohne_locale_laedt_nur_die_standardvariante(): void
    {
        $this->freigeben();
        $this->apiMehrsprachig('ALT-DE', 'ALT-EN');
        $this->rendere('<x-consentlaw::impressum />');
        $this->rendere('<x-consentlaw::impressum locale="en" />');

        $this->apiMehrsprachig('NEU-DE', 'NEU-EN');
        [$status] = $this->postNeuLaden('impressum');

        $this->assertSame(302, $status);
        $this->assertSame(3, $this->anzahlAufrufe());
        $this->assertStringNotContainsString('locale=en', (string) end($this->aufrufe));
        $this->assertStringContainsString('NEU-DE', $this->rendere('<x-consentlaw::impressum />'));
        $this->assertStringContainsString('ALT-EN', $this->rendere('<x-consentlaw::impressum locale="en" />'), 'Die EN-Variante bleibt unberührt.');
    }

    // AK4
    public function test_neu_laden_mit_locale_holt_die_jobs_dieser_sprache_frisch(): void
    {
        $this->freigeben();
        $this->api(['*' => fn (Request $anfrage): array => $this->jobsPayload([
            'title' => str_contains($anfrage->url(), 'locale=en') ? 'Alt-Job-EN' : 'Alt-Job-DE',
        ])]);
        $this->rendere('<x-consentlaw::jobs />');
        $this->assertStringContainsString('Alt-Job-EN', $this->rendere('<x-consentlaw::jobs locale="en" />'));

        $this->api(['*' => fn (Request $anfrage): array => $this->jobsPayload([
            'title' => str_contains($anfrage->url(), 'locale=en') ? 'Neu-Job-EN' : 'Neu-Job-DE',
        ])]);
        [$status] = $this->postNeuLaden('jobs', eingabe: ['locale' => 'en']);

        $this->assertSame(302, $status);
        $this->assertSame(3, $this->anzahlAufrufe());
        $this->assertStringContainsString('locale=en', (string) end($this->aufrufe));
        $this->assertStringContainsString('Neu-Job-EN', $this->rendere('<x-consentlaw::jobs locale="en" />'));
        $this->assertStringContainsString('Alt-Job-DE', $this->rendere('<x-consentlaw::jobs />'), 'Die Standardvariante bleibt unberührt.');
    }

    /** @return array<string, array{string}> */
    public static function gueltigeLocales(): array
    {
        return ['de' => ['de'], 'en' => ['en'], 'Unterstrich en_GB' => ['en_GB'], 'Bindestrich pt-BR' => ['pt-BR']];
    }

    // AK4
    #[DataProvider('gueltigeLocales')]
    public function test_gueltige_locale_wird_akzeptiert_und_neu_geladen(string $locale): void
    {
        $this->freigeben();
        $this->api(['*' => $this->dokumentPayload(self::NEU)]);

        [$status] = $this->postNeuLaden('impressum', eingabe: ['locale' => $locale]);

        $this->assertSame(302, $status);
        $this->assertSame(1, $this->anzahlAufrufe());
    }

    /** @return array<string, array{mixed}> */
    public static function ungueltigeLocales(): array
    {
        return [
            'Pfad-Traversal' => ['../x'],
            'Semikolon' => ['de;drop'],
            'Wort' => ['deutsch'],
            'Grossbuchstaben' => ['EN'],
            'Zeilenumbruch' => ["en\nX-Header: 1"],
            'Anfuehrungszeichen' => ['"en"'],
            'Array' => [['en']],
            'Array mit Schluessel' => [['x' => 'en']],
        ];
    }

    // AK4
    #[DataProvider('ungueltigeLocales')]
    public function test_ungueltige_locale_wird_mit_422_abgewiesen_ohne_api_aufruf(mixed $locale): void
    {
        $this->freigeben();
        $this->assertTrue(Route::has('consentlaw.neu-laden'), 'Vorbedingung: Die Route ist registriert.');
        $this->api(['*' => $this->dokumentPayload(self::NEU)]);

        [$status] = $this->postNeuLaden('impressum', eingabe: ['locale' => $locale]);

        $this->assertSame(422, $status);
        $this->assertSame(0, $this->anzahlAufrufe(), 'Bei ungültiger Sprache darf es keinen API-Aufruf geben.');
    }

    // AK4
    public function test_ungueltige_locale_wird_auch_bei_jobs_mit_422_abgewiesen(): void
    {
        $this->freigeben();
        $this->api(['*' => $this->jobsPayload(['title' => 'Neu-Stelle'])]);

        [$status] = $this->postNeuLaden('jobs', eingabe: ['locale' => '../x']);

        $this->assertSame(422, $status);
        $this->assertSame(0, $this->anzahlAufrufe());
    }

    // AK4
    public function test_abgewiesene_locale_laesst_den_cache_unberuehrt(): void
    {
        $this->freigeben();
        $this->api(['*' => $this->dokumentPayload(self::ALT)]);
        $this->rendere('<x-consentlaw::impressum />');
        $this->api(['*' => $this->dokumentPayload(self::NEU)]);

        $this->postNeuLaden('impressum', eingabe: ['locale' => 'de;drop']);

        $this->assertStringContainsString(self::ALT, $this->rendere('<x-consentlaw::impressum />'));
        $this->assertSame(1, $this->anzahlAufrufe());
    }

    // ── AK5: Nur gültige Typen ───────────────────────────────────────────────

    /** @return array<string, array{string}> */
    public static function ungueltigeTypen(): array
    {
        return [
            'unbekannt' => ['unbekannt'],
            'englischer Name' => ['privacy'],
            'Dateiendung' => ['wp-login.php'],
            'Kombination' => ['impressum-jobs'],
            'Punkte' => ['..'],
        ];
    }

    // AK5
    #[DataProvider('ungueltigeTypen')]
    public function test_unbekannter_typ_wird_abgewiesen_ohne_api_aufruf(string $typ): void
    {
        $this->freigeben();
        $this->assertTrue(Route::has('consentlaw.neu-laden'), 'Vorbedingung: Die Route ist registriert (sonst wäre 404 trivial).');
        $this->api(['*' => $this->dokumentPayload(self::NEU)]);

        [$status] = $this->postNeuLaden($typ);

        $this->assertContains($status, [404, 422], "Typ '{$typ}' muss mit 404 oder 422 abgewiesen werden.");
        $this->assertSame(0, $this->anzahlAufrufe(), 'Für ungültige Typen darf es keinen API-Aufruf geben.');
    }

    // AK5
    public function test_abgewiesener_typ_laesst_den_cache_unberuehrt(): void
    {
        $this->freigeben();
        $this->assertTrue(Route::has('consentlaw.neu-laden'), 'Vorbedingung: Die Route ist registriert.');
        $this->api(['*' => $this->dokumentPayload(self::ALT)]);
        $this->rendere('<x-consentlaw::impressum />');
        $this->api(['*' => $this->dokumentPayload(self::NEU)]);

        $this->postNeuLaden('unbekannt');

        $this->assertStringContainsString(self::ALT, $this->rendere('<x-consentlaw::impressum />'));
        $this->assertSame(1, $this->anzahlAufrufe());
    }

    // ── AK6: Missbrauchsschutz ───────────────────────────────────────────────

    // AK6
    public function test_die_siebte_anfrage_in_einer_minute_wird_mit_429_abgewiesen(): void
    {
        $this->freigeben();
        $this->api(['*' => $this->dokumentPayload(self::NEU)]);

        for ($i = 1; $i <= 6; $i++) {
            [$status] = $this->postNeuLaden(self::TYPEN[$i % 5]);
            $this->assertSame(302, $status, "Anfrage {$i} von 6 muss erlaubt sein.");
        }
        $aufrufeVorher = $this->anzahlAufrufe();

        [$status] = $this->postNeuLaden('impressum');

        $this->assertSame(429, $status);
        $this->assertSame($aufrufeVorher, $this->anzahlAufrufe(), 'Die gesperrte Anfrage darf nicht neu laden.');
    }

    // AK6
    public function test_das_limit_gilt_je_ip(): void
    {
        $this->freigeben();
        $this->api(['*' => $this->dokumentPayload(self::NEU)]);

        for ($i = 0; $i < 6; $i++) {
            $this->postNeuLaden('impressum', kopf: ['REMOTE_ADDR' => '203.0.113.5']);
        }
        [$gesperrt] = $this->postNeuLaden('impressum', kopf: ['REMOTE_ADDR' => '203.0.113.5']);
        [$andereIp] = $this->postNeuLaden('impressum', kopf: ['REMOTE_ADDR' => '198.51.100.7']);

        $this->assertSame(429, $gesperrt);
        $this->assertSame(302, $andereIp, 'Eine andere IP ist vom Limit nicht betroffen.');
    }

    // AK6
    public function test_nach_einer_minute_ist_wieder_eine_anfrage_erlaubt(): void
    {
        $this->freigeben();
        $this->api(['*' => $this->dokumentPayload(self::NEU)]);

        for ($i = 0; $i < 7; $i++) {
            [$status] = $this->postNeuLaden('impressum');
        }
        $this->assertSame(429, $status, 'Vorbedingung: Limit erreicht.');

        $this->reise(61);
        [$status] = $this->postNeuLaden('impressum');

        $this->assertSame(302, $status);
    }

    // AK6
    public function test_ohne_csrf_token_wird_nicht_neu_geladen(): void
    {
        $this->freigeben();
        $this->api(['*' => $this->dokumentPayload(self::NEU)]);

        [$status] = $this->postNeuLaden('impressum', mitToken: false);

        $this->assertSame(419, $status);
        $this->assertSame(0, $this->anzahlAufrufe());
    }

    // AK6
    public function test_mit_falschem_csrf_token_wird_nicht_neu_geladen(): void
    {
        $this->freigeben();
        $this->api(['*' => $this->dokumentPayload(self::NEU)]);

        [$status] = $this->postNeuLaden('impressum', mitToken: false, eingabe: ['_token' => 'gefaelscht']);

        $this->assertSame(419, $status);
        $this->assertSame(0, $this->anzahlAufrufe());
    }

    // AK6
    public function test_ohne_sitzung_und_token_wird_nicht_neu_geladen(): void
    {
        $this->freigeben();
        $this->api(['*' => $this->dokumentPayload(self::NEU)]);

        $anfrage = HttpRequest::create('http://localhost/consentlaw/neu-laden/impressum', 'POST', [], [], [], ['REMOTE_ADDR' => '203.0.113.5']);
        [$status] = $this->sende($anfrage);

        $this->assertSame(419, $status);
        $this->assertSame(0, $this->anzahlAufrufe());
    }

    // AK6
    public function test_die_route_nimmt_nur_post_an(): void
    {
        $this->freigeben();
        $this->api(['*' => $this->dokumentPayload(self::NEU)]);

        $anfrage = HttpRequest::create('http://localhost/consentlaw/neu-laden/impressum', 'GET', [], [], [], ['REMOTE_ADDR' => '203.0.113.5']);
        [$status] = $this->sende($anfrage);

        $this->assertSame(405, $status, 'Ein GET darf nie neu laden (CSRF über Link/Bild).');
        $this->assertSame(0, $this->anzahlAufrufe());
    }

    // ── AK7: Freigabe auch in der Aktion ─────────────────────────────────────
    //
    // Ein echter Route-Cache aus anderer Umgebung lässt sich in der Test-App nicht
    // nachstellen. Gleichwertig: Die Route wird bei freigegebener App registriert und
    // bleibt bestehen, die Freigabe wird danach OHNE Neubau der App entzogen – entweder
    // über die Config zur Laufzeit (Schalter aus) oder durch Umstellen der Umgebung auf
    // production (detectEnvironment setzt das `env`-Binding, das app()->environment() liest).

    // AK7
    public function test_entzogener_schalter_zur_laufzeit_liefert_404_ohne_api_aufruf(): void
    {
        $this->freigeben();
        $this->api(['*' => $this->dokumentPayload(self::NEU)]);
        $this->assertTrue(Route::has('consentlaw.neu-laden'), 'Vorbedingung: Die Route ist registriert.');

        $this->app['config']->set('consentlaw.refresh_button', false);
        $this->assertTrue(Route::has('consentlaw.neu-laden'), 'Vorbedingung: Die Route existiert weiterhin.');

        [$status] = $this->postNeuLaden('impressum');

        $this->assertSame(404, $status);
        $this->assertSame(0, $this->anzahlAufrufe(), 'Ohne Freigabe darf nicht neu geladen werden.');
    }

    // AK7
    public function test_entzogener_schalter_liefert_auch_auf_production_404_ohne_api_aufruf(): void
    {
        $this->freigeben('production');
        $this->api(['*' => $this->dokumentPayload(self::NEU)]);
        $this->assertTrue(Route::has('consentlaw.neu-laden'), 'Vorbedingung: Die Route ist registriert.');

        $this->app['config']->set('consentlaw.refresh_button', false);

        [$status] = $this->postNeuLaden('jobs');

        $this->assertSame(404, $status);
        $this->assertSame(0, $this->anzahlAufrufe());
    }

    // AK7
    #[DataProvider('dokumentTypen')]
    public function test_ohne_freigabe_ist_kein_typ_neu_ladbar(string $typ): void
    {
        $this->freigeben();
        $this->api(['*' => $this->dokumentPayload(self::NEU)]);
        $this->app['config']->set('consentlaw.refresh_button', false);

        [$status] = $this->postNeuLaden($typ);

        $this->assertSame(404, $status);
        $this->assertSame(0, $this->anzahlAufrufe());
    }

    // AK7
    public function test_ohne_freigabe_bleibt_der_cache_unberuehrt(): void
    {
        $this->freigeben();
        $this->api(['*' => $this->dokumentPayload(self::ALT)]);
        $this->rendere('<x-consentlaw::impressum />');
        $this->api(['*' => $this->dokumentPayload(self::NEU)]);
        $this->app['config']->set('consentlaw.refresh_button', false);

        $this->postNeuLaden('impressum');

        $this->assertStringContainsString(self::ALT, $this->rendere('<x-consentlaw::impressum />'));
        $this->assertSame(1, $this->anzahlAufrufe());
    }

    // AK7
    public function test_ohne_freigabe_wird_auch_mit_locale_nicht_neu_geladen(): void
    {
        $this->freigeben();
        $this->api(['*' => $this->dokumentPayload(self::NEU)]);
        $this->app['config']->set('consentlaw.refresh_button', false);

        [$status] = $this->postNeuLaden('impressum', eingabe: ['locale' => 'en']);

        $this->assertContains($status, [404, 422]);
        $this->assertSame(0, $this->anzahlAufrufe());
    }

    // ── AK10: Unabhängig vom Route-Cache ─────────────────────────────────────
    //
    // Route und Limiter `consentlaw-neu-laden` werden beim Boot IMMER registriert. Im
    // Fehlerfall trug die gecachte Route `throttle:consentlaw-neu-laden`, der Provider
    // definierte den Limiter bei Schalter aus aber nicht → MissingRateLimiterException (500).
    // Der Route-Cache wird nachgestellt: Die Routen einer App werden wie bei `route:cache`
    // kompiliert (`getRoutes()->compile()`) und in einer neu gebauten App mit anderem
    // Schalterstand per `setCompiledRoutes()` an die Stelle der Boot-Routen gesetzt.

    /** @return array<string, mixed> kompilierte Routen einer App, die mit dem Schalterstand gebaut wurde (wie `route:cache`). */
    private function routeCacheMitSchalter(bool $schalter, string $umgebung = 'local'): array
    {
        $this->appNeuBauen($umgebung, ['refresh_button' => $schalter]);

        return $this->app['router']->getRoutes()->compile();
    }

    /** @param  array<string, mixed>  $cache */
    private function appMitRouteCache(array $cache, bool $laufzeitSchalter, string $umgebung = 'local'): void
    {
        $this->appNeuBauen($umgebung, ['refresh_button' => $laufzeitSchalter]);
        $this->app['router']->setCompiledRoutes($cache);
    }

    // AK10
    #[DataProvider('umgebungen')]
    public function test_route_und_limiter_sind_auch_bei_schalter_aus_registriert(string $umgebung): void
    {
        $this->appNeuBauen($umgebung, ['refresh_button' => false]);

        $this->assertTrue(Route::has('consentlaw.neu-laden'), 'Die Route wird immer registriert.');
        $this->assertNotNull(RateLimiter::limiter('consentlaw-neu-laden'), 'Der Limiter wird immer definiert.');
    }

    // AK10
    #[DataProvider('umgebungen')]
    public function test_route_und_limiter_sind_auch_bei_schalter_an_registriert(string $umgebung): void
    {
        $this->appNeuBauen($umgebung, ['refresh_button' => true]);

        $this->assertTrue(Route::has('consentlaw.neu-laden'));
        $this->assertNotNull(RateLimiter::limiter('consentlaw-neu-laden'));
    }

    // AK10
    public function test_standardmaessig_ohne_app_test_sind_route_und_limiter_da(): void
    {
        $this->appNeuBauen('production');

        $this->assertTrue(Route::has('consentlaw.neu-laden'));
        $this->assertNotNull(RateLimiter::limiter('consentlaw-neu-laden'));
    }

    // AK10
    #[DataProvider('umgebungen')]
    public function test_cache_mit_schalter_an_und_laufzeit_aus_liefert_404_statt_500(string $umgebung): void
    {
        $cache = $this->routeCacheMitSchalter(true, $umgebung);
        $this->appMitRouteCache($cache, false, $umgebung);
        $this->api(['*' => $this->dokumentPayload(self::NEU)]);

        $this->assertTrue(Route::has('consentlaw.neu-laden'), 'Vorbedingung: Die gecachte Route ist vorhanden.');
        $this->assertNotNull(RateLimiter::limiter('consentlaw-neu-laden'), 'Der Limiter muss auch bei Schalter aus existieren, sonst 500.');

        $this->assertKeinButton($this->rendere('<x-consentlaw::impressum />'));
        $this->assertNeuLadenGesperrt();
        $this->assertNeuLadenGesperrt('jobs');
    }

    // AK10
    public function test_cache_mit_schalter_aus_und_laufzeit_aus_liefert_404_ohne_api_aufruf(): void
    {
        $cache = $this->routeCacheMitSchalter(false);
        $this->appMitRouteCache($cache, false);
        $this->api(['*' => $this->dokumentPayload(self::NEU)]);

        $this->assertNeuLadenGesperrt();
        $this->assertSame(0, $this->anzahlAufrufe());
    }

    // AK10
    #[DataProvider('umgebungen')]
    public function test_cache_mit_schalter_aus_und_laufzeit_an_zeigt_button_und_laedt_neu(string $umgebung): void
    {
        $cache = $this->routeCacheMitSchalter(false, $umgebung);
        $this->appMitRouteCache($cache, true, $umgebung);
        $this->api(['*' => $this->dokumentPayload(self::ALT)]);

        $this->assertTrue(Route::has('consentlaw.neu-laden'), 'Die Route gehört in jeden Cache, unabhängig vom Schalter.');

        $this->sessionToken();
        $html = $this->rendere('<x-consentlaw::impressum />');
        $this->assertStringContainsString(self::ALT, $html);
        $this->assertButtonFuer('impressum', $html);
        $aufrufeNachRender = $this->anzahlAufrufe();
        $this->assertSame(1, $aufrufeNachRender, 'Vorbedingung: Nur der Abruf beim Rendern.');

        $this->api(['*' => $this->dokumentPayload(self::NEU)]);
        [$status, $antwort] = $this->postNeuLaden('impressum', referer: 'http://localhost/impressum');

        $this->assertSame(302, $status);
        $this->assertSame('http://localhost/impressum', $this->ziel($antwort));
        $this->assertSame($aufrufeNachRender + 1, $this->anzahlAufrufe(), 'Genau ein zusätzlicher HTTP-Aufruf.');
        $this->assertStringContainsString(self::NEU, $this->rendere('<x-consentlaw::impressum />'));
    }

    // AK10
    public function test_cache_mit_schalter_aus_und_laufzeit_an_laedt_auch_die_jobs_neu(): void
    {
        $cache = $this->routeCacheMitSchalter(false);
        $this->appMitRouteCache($cache, true);
        $this->api(['*' => $this->jobsPayload(['title' => 'Alt-Stelle'])]);
        $this->rendere('<x-consentlaw::jobs />');
        $aufrufeNachRender = $this->anzahlAufrufe();
        $this->assertSame(1, $aufrufeNachRender, 'Vorbedingung: Nur der Abruf beim Rendern.');

        $this->api(['*' => $this->jobsPayload(['title' => 'Neu-Stelle'])]);
        [$status] = $this->postNeuLaden('jobs');

        $this->assertSame(302, $status);
        $this->assertSame($aufrufeNachRender + 1, $this->anzahlAufrufe(), 'Genau ein zusätzlicher HTTP-Aufruf.');
        $this->assertStringContainsString('Neu-Stelle', $this->rendere('<x-consentlaw::jobs />'));
    }

    // AK10
    public function test_cache_mit_schalter_an_und_laufzeit_an_funktioniert_wie_bisher(): void
    {
        $cache = $this->routeCacheMitSchalter(true, 'production');
        $this->appMitRouteCache($cache, true, 'production');
        $this->api(['*' => $this->dokumentPayload(self::NEU)]);

        [$status] = $this->postNeuLaden('impressum');

        $this->assertSame(302, $status);
        $this->assertSame(1, $this->anzahlAufrufe());
    }

    // AK10
    public function test_das_limit_gilt_auch_bei_einer_gecachten_route(): void
    {
        $cache = $this->routeCacheMitSchalter(false);
        $this->appMitRouteCache($cache, true);
        $this->api(['*' => $this->dokumentPayload(self::NEU)]);

        for ($i = 0; $i < 6; $i++) {
            [$status] = $this->postNeuLaden('impressum');
            $this->assertSame(302, $status);
        }

        [$status] = $this->postNeuLaden('impressum');
        $this->assertSame(429, $status);
    }

    // ── Randfälle ────────────────────────────────────────────────────────────

    // AK4 (Randfall: Störung beim Neu-laden)
    public function test_stoerung_beim_neu_laden_leitet_trotzdem_zurueck_und_zeigt_den_bisherigen_stand(): void
    {
        $this->freigeben();
        $this->api(['*' => $this->dokumentPayload(self::ALT)]);
        $this->rendere('<x-consentlaw::impressum />');

        $this->api(['*' => self::STOERUNG]);
        [$status, $antwort] = $this->postNeuLaden('impressum', referer: 'http://localhost/impressum');

        $this->assertSame(302, $status, 'Keine Fehlerseite bei Störung.');
        $this->assertSame('http://localhost/impressum', $this->ziel($antwort));

        $html = $this->rendere('<x-consentlaw::impressum />');
        $this->assertStringContainsString(self::ALT, $html, 'Der Fallback (bisheriger Stand) bleibt sichtbar.');
    }

    // AK4 (Randfall: Störung beim Neu-laden)
    #[DataProvider('fehlerAntworten')]
    public function test_fehlerantworten_der_api_fuehren_nicht_zu_einer_fehlerseite(mixed $antwort): void
    {
        $this->freigeben();
        $this->api(['*' => $this->jobsPayload(['title' => 'Alt-Stelle'])]);
        $this->rendere('<x-consentlaw::jobs />');

        $this->api(['*' => $antwort]);
        [$status] = $this->postNeuLaden('jobs');

        $this->assertSame(302, $status);
        $this->assertStringContainsString('Alt-Stelle', $this->rendere('<x-consentlaw::jobs />'));
    }

    // AK4 (Randfall: Störung beim Neu-laden)
    public function test_stoerung_ohne_vorhandenen_stand_leitet_ebenfalls_zurueck(): void
    {
        $this->freigeben();
        $this->api(['*' => self::STOERUNG]);

        [$status] = $this->postNeuLaden('impressum');

        $this->assertSame(302, $status);
    }

    // Randfall: Rückleitung nur auf die eigene Seite
    #[DataProvider('fremdeReferer')]
    public function test_fremder_referer_fuehrt_nicht_zu_einem_offenen_redirect(string $referer): void
    {
        $this->freigeben();
        $this->api(['*' => $this->dokumentPayload(self::NEU)]);

        [$status, $antwort] = $this->postNeuLaden('impressum', referer: $referer);

        $this->assertSame(302, $status);
        $this->assertSame('localhost', parse_url($this->ziel($antwort), PHP_URL_HOST), 'Redirect muss auf der eigenen Domain bleiben, war: '.$this->ziel($antwort));
        $this->assertStringNotContainsString('boese', $this->ziel($antwort));
    }

    // Randfall: Rückleitung nur auf die eigene Seite
    public function test_ohne_referer_geht_es_auf_die_eigene_startseite(): void
    {
        $this->freigeben();
        $this->api(['*' => $this->dokumentPayload(self::NEU)]);

        [$status, $antwort] = $this->postNeuLaden('impressum', referer: null);

        $this->assertSame(302, $status);
        $this->assertSame('localhost', parse_url($this->ziel($antwort), PHP_URL_HOST));
    }

    // Randfall: Rückleitung nur auf die eigene Seite
    #[DataProvider('umleitungsParameter')]
    public function test_eingabeparameter_koennen_den_redirect_nicht_umlenken(string $name): void
    {
        $this->freigeben();
        $this->api(['*' => $this->dokumentPayload(self::NEU)]);

        [$status, $antwort] = $this->postNeuLaden('impressum', eingabe: [$name => 'https://boese.example/phishing'], referer: 'http://localhost/impressum');

        $this->assertSame(302, $status);
        $this->assertSame('http://localhost/impressum', $this->ziel($antwort));
    }

    // Randfall: Rückleitung nur auf die eigene Seite
    public function test_eigener_referer_mit_query_bleibt_erhalten(): void
    {
        $this->freigeben();
        $this->api(['*' => $this->dokumentPayload(self::NEU)]);

        [, $antwort] = $this->postNeuLaden('impressum', referer: 'http://localhost/impressum?lang=de');

        $this->assertSame('http://localhost/impressum?lang=de', $this->ziel($antwort));
    }

    // ── Daten ────────────────────────────────────────────────────────────────

    /** @return array<string, array{string}> */
    public static function dokumentTypen(): array
    {
        return array_combine(self::TYPEN, array_map(static fn (string $typ): array => [$typ], self::TYPEN));
    }

    /** @return array<string, array{string, mixed}> */
    public static function slotFaelle(): array
    {
        $faelle = [];

        foreach ([...self::TYPEN, 'jobs'] as $typ) {
            $faelle["{$typ} nicht veröffentlicht (404)"] = [$typ, 404];
            $faelle["{$typ} Störung ohne Fallback"] = [$typ, self::STOERUNG];
        }

        return $faelle;
    }

    /** @return array<string, array{mixed}> */
    public static function fehlerAntworten(): array
    {
        return ['Störung' => [self::STOERUNG], 'Serverfehler 503' => [503], 'Rate-Limit der API 429' => [429]];
    }

    /** @return array<string, array{string}> */
    public static function fremdeReferer(): array
    {
        return [
            'fremde Domain' => ['https://boese.example'],
            'fremde Domain mit Pfad' => ['https://boese.example/phishing?x=1'],
            'Benutzerinfo-Trick' => ['http://localhost@boese.example/'],
            'Subdomain-Trick' => ['http://localhost.boese.example/'],
            'protokollrelativ' => ['//boese.example/phishing'],
        ];
    }

    /** @return array<string, array{string}> */
    public static function umleitungsParameter(): array
    {
        return array_combine(
            ['redirect', 'return', 'next', 'url', 'back', '_previous'],
            array_map(static fn (string $name): array => [$name], ['redirect', 'return', 'next', 'url', 'back', '_previous']),
        );
    }
}
