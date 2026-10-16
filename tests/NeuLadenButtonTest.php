<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Tests;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Button „Neu laden" (Release 1.2.0) – AK1 bis AK6 aus
 * .project/features/neu-laden-button/spec.md.
 *
 * VERTRAG (neu, noch nicht vorhanden – der Coder baut danach):
 *
 *  Config
 *   - `consentlaw.refresh_button` (bool), env `CONSENTLAW_REFRESH_BUTTON`, Standard false.
 *   - Freigabe = Schalter an UND `app()->environment('production')` false.
 *     Die Freigabe-Logik liegt an EINER Stelle (Route-Registrierung und Komponenten nutzen sie).
 *
 *  Route (wird beim Booten des Providers registriert, nur bei Freigabe)
 *   - `POST consentlaw/neu-laden/{typ}`, Name `consentlaw.neu-laden`.
 *   - Middleware: Gruppe `web` (Session + CSRF) und ein Limiter, 6 Anfragen/Minute je IP
 *     (Test-App kennt Alias `throttle` und benannte Limiter über die RateLimiter-Facade).
 *   - `typ` ∈ impressum, datenschutz, agb, cookie-richtlinie, avv, jobs; alles andere
 *     → 404 (oder 422), ohne API-Aufruf.
 *   - Aktion: ruft `refresh()` des passenden Endpoints (Cache, Sperrzeit und Fallback-Datei
 *     werden umgangen/aktualisiert) und antwortet mit Redirect 302 zurück (`back()`).
 *   - Rückleitung nur auf die eigene Seite: Ein Referer auf einen fremden Host oder
 *     Eingabeparameter dürfen den Redirect nicht umlenken (Ziel dann `/` bzw. eigener Host).
 *   - Eine Störung beim Neu-laden führt NICHT zu einer Fehlerseite: ebenfalls 302.
 *   - Nur POST (GET → 405).
 *
 *  Button (Blade-Teilansicht, eingebunden in `document` und `jobs`, auch im Slot-Fall)
 *   - `<form method="post" action="{route('consentlaw.neu-laden', typ)}">` mit CSRF-Feld
 *     (`@csrf`, Eingabefeld `_token`) und einem `<button>` mit dem Text „Neu laden".
 *   - Die Klasse `consentlaw-refresh` trägt das Formular oder ein Element darin.
 *
 * Testaufbau: Routen und Limiter werden beim Booten des Providers entschieden. Die Tests
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
     * @param  array<string, string>  $eingabe  zusätzliche Formularfelder
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

    // AK1
    public function test_umgebungsvariable_false_laesst_den_schalter_aus(): void
    {
        $this->setzeUmgebung('CONSENTLAW_REFRESH_BUTTON', 'false');
        $this->appNeuBauen('local');

        $this->assertFalse($this->app['config']->get('consentlaw.refresh_button'));
        $this->assertFalse(Route::has('consentlaw.neu-laden'));
    }

    // AK1
    public function test_umgebungsvariable_true_schaltet_den_button_ein(): void
    {
        $this->setzeUmgebung('CONSENTLAW_REFRESH_BUTTON', 'true');
        $this->appNeuBauen('local');

        $this->assertTrue($this->app['config']->get('consentlaw.refresh_button'));
        $this->assertTrue(Route::has('consentlaw.neu-laden'));
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
    public function test_ohne_schalter_ist_die_route_nicht_registriert(): void
    {
        $this->assertFalse(Route::has('consentlaw.neu-laden'));

        $this->api(['*' => $this->dokumentPayload(self::NEU)]);
        [$status] = $this->postNeuLaden('impressum');

        $this->assertContains($status, [404, 405]);
        $this->assertSame(0, $this->anzahlAufrufe());
    }

    // ── AK2: Nie auf Production ──────────────────────────────────────────────

    // AK2
    #[DataProvider('dokumentTypen')]
    public function test_auf_production_zeigt_die_dokument_komponente_trotz_schalter_keinen_button(string $typ): void
    {
        $this->freigeben('production');
        $this->api(['*' => $this->dokumentPayload(self::ALT)]);

        $html = $this->rendere("<x-consentlaw::document type=\"{$typ}\" />");

        $this->assertStringContainsString(self::ALT, $html);
        $this->assertKeinButton($html);
    }

    // AK2
    public function test_auf_production_zeigt_die_jobs_komponente_trotz_schalter_keinen_button(): void
    {
        $this->freigeben('production');
        $this->api(['*' => $this->jobsPayload(['title' => 'Alt-Stelle'])]);

        $html = $this->rendere('<x-consentlaw::jobs />');

        $this->assertStringContainsString('Alt-Stelle', $html);
        $this->assertKeinButton($html);
    }

    // AK2
    public function test_auf_production_ist_auch_der_slot_fall_ohne_button(): void
    {
        $this->freigeben('production');
        $this->api(['*' => 404]);

        $this->assertKeinButton($this->rendere('<x-consentlaw::datenschutz>Slot-Inhalt</x-consentlaw::datenschutz>'));
        $this->assertKeinButton($this->rendere('<x-consentlaw::jobs>Slot-Inhalt</x-consentlaw::jobs>'));
    }

    // AK2
    public function test_auf_production_ist_die_route_trotz_schalter_nicht_registriert(): void
    {
        $this->freigeben('production');
        $this->api(['*' => $this->dokumentPayload(self::NEU)]);

        $this->assertFalse(Route::has('consentlaw.neu-laden'));

        [$status] = $this->postNeuLaden('impressum');

        $this->assertContains($status, [404, 405]);
        $this->assertSame(0, $this->anzahlAufrufe(), 'Auf Production darf nie neu geladen werden.');
    }

    // ── AK3: Button sichtbar ─────────────────────────────────────────────────

    /** @return array<string, array{string}> */
    public static function umgebungen(): array
    {
        return ['local' => ['local'], 'testing' => ['testing'], 'staging' => ['staging']];
    }

    // AK3
    #[DataProvider('umgebungen')]
    public function test_mit_schalter_ausserhalb_von_production_ist_die_route_registriert(string $umgebung): void
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
