<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Tests;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\Request as HttpRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Tests zu .project/features/jobs-standardtext/spec.md (AK1–AK8, Randfälle).
 *
 * Gerendert wird `<x-consentlaw::jobs …>` gegen die gefakte Jobs-API (AppTestCase),
 * die Paket-Seite `/jobs` über den Router der kleinen Test-App.
 */
final class JobsStandardtextTest extends AppTestCase
{
    private const STANDARDTEXT = 'Zurzeit sind keine offenen Stellen ausgeschrieben.';

    private const ALTER_SEITENTEXT = 'Zurzeit sind keine Stellen ausgeschrieben.';

    private const ADRESSE = 'jobs@example.de';

    private const ANDERE_ADRESSE = 'bewerbung@example.org';

    private const LEER_XPATH = "//p[contains(concat(' ', normalize-space(@class), ' '), ' consentlaw-jobs__leer ')]";

    // ── Hilfen ───────────────────────────────────────────────────────────────

    private function keineStellen(): void
    {
        $this->api(['*' => ['jobs' => []]]);
    }

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>');
        libxml_clear_errors();

        return new DOMXPath($dom);
    }

    /** Der Absatz `p.consentlaw-jobs__leer`; scheitert, wenn es ihn nicht genau einmal gibt. */
    private function leerAbsatz(string $html): DOMElement
    {
        $treffer = $this->xpath($html)->query(self::LEER_XPATH);

        $this->assertNotFalse($treffer);
        $this->assertSame(1, $treffer->length, 'Genau ein <p class="consentlaw-jobs__leer"> erwartet. HTML: '.$html);

        $absatz = $treffer->item(0);
        $this->assertInstanceOf(DOMElement::class, $absatz);

        return $absatz;
    }

    private function anzahlLeerAbsaetze(string $html): int
    {
        $treffer = $this->xpath($html)->query(self::LEER_XPATH);

        return $treffer === false ? 0 : $treffer->length;
    }

    private function text(DOMElement $element): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $element->textContent));
    }

    private function initiativSatz(string $adresse): string
    {
        return 'Initiativbewerbungen gern an '.$adresse.'.';
    }

    /** Prüft im Absatz einen mailto-Link auf $adresse und gibt den Link zurück. */
    private function assertMailtoLink(DOMElement $absatz, string $adresse): DOMElement
    {
        $links = $absatz->getElementsByTagName('a');
        $this->assertSame(1, $links->length, 'Genau ein Link im Absatz erwartet.');

        $link = $links->item(0);
        $this->assertInstanceOf(DOMElement::class, $link);

        $href = $link->getAttribute('href');
        $this->assertStringStartsWith('mailto:', $href);
        $this->assertSame($adresse, rawurldecode(substr($href, strlen('mailto:'))), 'Der Link führt an die Adresse.');
        $this->assertSame($adresse, $this->text($link), 'Der Linktext ist die Adresse.');

        return $link;
    }

    /** Baut die App mit aktivierten Paket-Routen (wie ReviewResteTest). */
    private function mitRouten(): void
    {
        $this->appNeuBauen('testing', [
            'cache' => ['enabled' => false, 'store' => null, 'ttl' => 0, 'prefix' => 'consentlaw'],
            'fallback' => ['enabled' => true, 'disk' => 'local', 'path' => 'consentlaw', 'warn_after' => 604800, 'retry_after' => 0],
            'routes' => [
                'enabled' => true,
                'middleware' => ['web'],
                'prefix' => '',
                'layout' => null,
                'paths' => ['jobs' => 'jobs'],
            ],
        ]);
    }

    /** @return array{0: int, 1: string} */
    private function seite(string $pfad): array
    {
        $anfrage = HttpRequest::create('http://localhost/'.$pfad, 'GET');
        $this->app->instance('request', $anfrage);

        try {
            $antwort = $this->app['router']->dispatch($anfrage);
        } catch (HttpExceptionInterface $e) {
            return [$e->getStatusCode(), ''];
        }

        return [$antwort->getStatusCode(), (string) $antwort->getContent()];
    }

    // ── AK1 ──────────────────────────────────────────────────────────────────

    // AK1
    public function test_ohne_stelle_und_slot_steht_der_standardtext_im_leer_absatz(): void
    {
        $this->keineStellen();

        $absatz = $this->leerAbsatz($this->rendere('<x-consentlaw::jobs />'));

        $this->assertSame(self::STANDARDTEXT, $this->text($absatz));
        $this->assertGreaterThan(0, $this->anzahlAufrufe(), 'Gegenprobe: Die Stellen wurden abgefragt.');
    }

    // AK1
    public function test_standardtext_ist_uebersetzbar(): void
    {
        $this->keineStellen();
        // JSON-Übersetzung wie in einer App (lang/de.json); addLines() scheitert am Punkt im Schlüssel.
        $ordner = $this->basis.'/lang-test';
        mkdir($ordner);
        file_put_contents($ordner.'/de.json', (string) json_encode([self::STANDARDTEXT => 'Übersetzt: keine Stellen offen.']));
        $this->app['translator']->addJsonPath($ordner);

        $absatz = $this->leerAbsatz($this->rendere('<x-consentlaw::jobs />'));

        $this->assertSame('Übersetzt: keine Stellen offen.', $this->text($absatz));
    }

    // AK1
    public function test_attribute_der_komponente_landen_am_leer_absatz(): void
    {
        $this->keineStellen();

        $absatz = $this->leerAbsatz($this->rendere('<x-consentlaw::jobs class="seiten-hinweis" data-test="leer" />'));

        $klassen = preg_split('/\s+/', trim($absatz->getAttribute('class'))) ?: [];
        $this->assertContains('consentlaw-jobs__leer', $klassen);
        $this->assertContains('seiten-hinweis', $klassen, 'Das class-Attribut wird am Absatz angewendet.');
        $this->assertSame('leer', $absatz->getAttribute('data-test'), 'Weitere Attribute werden am Absatz angewendet.');
    }

    // ── AK2 ──────────────────────────────────────────────────────────────────

    // AK2
    public function test_initiativ_satz_aus_der_konfiguration_im_selben_absatz_mit_mailto_link(): void
    {
        $this->keineStellen();
        $this->konfig('jobs.initiativ_email', self::ADRESSE);

        $absatz = $this->leerAbsatz($this->rendere('<x-consentlaw::jobs />'));

        $this->assertSame(self::STANDARDTEXT.' '.$this->initiativSatz(self::ADRESSE), $this->text($absatz));
        $this->assertMailtoLink($absatz, self::ADRESSE);
    }

    // AK2
    public function test_initiativ_satz_aus_der_umgebungsvariable(): void
    {
        $this->setzeUmgebung('CONSENTLAW_JOBS_INITIATIV_EMAIL', self::ADRESSE);
        $this->appNeuBauen('testing');
        $this->keineStellen();

        $this->assertSame(self::ADRESSE, $this->app['config']->get('consentlaw.jobs.initiativ_email'));

        $absatz = $this->leerAbsatz($this->rendere('<x-consentlaw::jobs />'));

        $this->assertStringContainsString($this->initiativSatz(self::ADRESSE), $this->text($absatz));
        $this->assertMailtoLink($absatz, self::ADRESSE);
    }

    // AK2, Randfall 2
    public function test_initiativ_satz_aus_dem_attribut_bei_leerer_konfiguration(): void
    {
        $this->keineStellen();

        $absatz = $this->leerAbsatz($this->rendere('<x-consentlaw::jobs initiativ-email="'.self::ADRESSE.'" />'));

        $this->assertSame(self::STANDARDTEXT.' '.$this->initiativSatz(self::ADRESSE), $this->text($absatz));
        $this->assertMailtoLink($absatz, self::ADRESSE);
    }

    // AK2
    public function test_attribut_geht_vor_konfiguration(): void
    {
        $this->keineStellen();
        $this->konfig('jobs.initiativ_email', self::ANDERE_ADRESSE);

        $ohneAttribut = $this->text($this->leerAbsatz($this->rendere('<x-consentlaw::jobs />')));
        $this->assertStringContainsString(self::ANDERE_ADRESSE, $ohneAttribut, 'Gegenprobe: Die Konfiguration greift.');

        $absatz = $this->leerAbsatz($this->rendere('<x-consentlaw::jobs initiativ-email="'.self::ADRESSE.'" />'));

        $this->assertSame(self::STANDARDTEXT.' '.$this->initiativSatz(self::ADRESSE), $this->text($absatz));
        $this->assertMailtoLink($absatz, self::ADRESSE);
    }

    // Randfall 2
    public function test_leeres_attribut_laesst_die_konfiguration_gelten(): void
    {
        $this->keineStellen();
        $this->konfig('jobs.initiativ_email', self::ANDERE_ADRESSE);

        $absatz = $this->leerAbsatz($this->rendere('<x-consentlaw::jobs initiativ-email="" />'));

        $this->assertSame(self::STANDARDTEXT.' '.$this->initiativSatz(self::ANDERE_ADRESSE), $this->text($absatz));
        $this->assertMailtoLink($absatz, self::ANDERE_ADRESSE);
    }

    /** @return array<string, array{0: mixed, 1: string|null}> Konfiguration, Attribut */
    public static function ohneGueltigeAdresse(): array
    {
        return [
            'nichts gesetzt' => [null, null],
            'Konfiguration leer' => ['', null],
            'Konfiguration nur Leerzeichen' => ['   ', null],
            'Konfiguration ohne @' => ['keine-adresse', null],
            'Konfiguration mit Leerzeichen' => [' '.self::ADRESSE.' x', null],
            'Attribut ohne @' => [null, 'keine-adresse'],
            'Attribut nur Leerzeichen' => [null, '   '],
            'Attribut leer' => [null, ''],
        ];
    }

    // AK2
    #[DataProvider('ohneGueltigeAdresse')]
    public function test_ohne_gueltige_adresse_entfaellt_der_satz_und_sonst_aendert_sich_nichts(mixed $konfig, ?string $attribut): void
    {
        $this->keineStellen();
        $basis = $this->rendere('<x-consentlaw::jobs />');
        $this->assertSame(self::STANDARDTEXT, $this->text($this->leerAbsatz($basis)), 'Gegenprobe: Der Standardtext erscheint.');

        $this->konfig('jobs.initiativ_email', $konfig);
        $blade = $attribut === null ? '<x-consentlaw::jobs />' : '<x-consentlaw::jobs initiativ-email="'.$attribut.'" />';
        $html = $this->rendere($blade);

        $this->assertSame(self::STANDARDTEXT, $this->text($this->leerAbsatz($html)), 'Kein Initiativ-Satz.');
        $this->assertSame(trim($basis), trim($html), 'Die Ausgabe gleicht der ohne Adresse.');
    }

    // AK2, Randfall 1
    public function test_adresse_mit_html_zeichen_wird_escaped(): void
    {
        $adresse = '"<b>x</b>"@example.de';
        $this->assertNotFalse(filter_var($adresse, FILTER_VALIDATE_EMAIL), 'Voraussetzung: Die Adresse ist gültig.');
        $this->keineStellen();
        $this->konfig('jobs.initiativ_email', $adresse);

        $html = $this->rendere('<x-consentlaw::jobs />');
        $absatz = $this->leerAbsatz($html);

        $this->assertSame(self::STANDARDTEXT.' '.$this->initiativSatz($adresse), $this->text($absatz), 'Die Adresse steht als Text im Satz.');
        $this->assertSame(0, $absatz->getElementsByTagName('b')->length, 'Kein Markup aus der Adresse.');
        $this->assertStringNotContainsString('<b>x</b>', $html);
        $this->assertMailtoLink($absatz, $adresse);
    }

    // AK2, Randfall 1
    public function test_kaufmanns_und_in_der_adresse_wird_escaped(): void
    {
        $adresse = 'a&b@example.de';
        $this->keineStellen();
        $this->konfig('jobs.initiativ_email', $adresse);

        $html = $this->rendere('<x-consentlaw::jobs />');

        $this->assertStringContainsString('a&amp;b@example.de', $html);
        $this->assertStringNotContainsString('a&b@', $html);
        $this->assertStringContainsString($this->initiativSatz($adresse), $this->text($this->leerAbsatz($html)));
    }

    // ── AK3 ──────────────────────────────────────────────────────────────────

    // AK3
    public function test_nicht_leerer_slot_ersetzt_standardtext_und_initiativ_satz(): void
    {
        $this->keineStellen();
        $this->konfig('jobs.initiativ_email', self::ADRESSE);

        $ohneSlot = $this->rendere('<x-consentlaw::jobs />');
        $this->assertStringContainsString($this->initiativSatz(self::ADRESSE), $this->text($this->leerAbsatz($ohneSlot)), 'Gegenprobe: Ohne Slot erscheinen beide.');

        $html = $this->rendere('<x-consentlaw::jobs><p class="eigener-slot">Eigener Slot-Hinweis</p></x-consentlaw::jobs>');

        $this->assertStringContainsString('Eigener Slot-Hinweis', $html);
        $this->assertStringNotContainsString(self::STANDARDTEXT, $html);
        $this->assertStringNotContainsString('Initiativbewerbungen gern an', $html);
        $this->assertStringNotContainsString(self::ADRESSE, $html);
        $this->assertSame(0, $this->anzahlLeerAbsaetze($html));
    }

    /** @return array<string, array{0: string}> */
    public static function leereSlots(): array
    {
        return [
            'leer' => ['<x-consentlaw::jobs></x-consentlaw::jobs>'],
            'nur Leerzeichen' => ['<x-consentlaw::jobs>   </x-consentlaw::jobs>'],
            'nur Zeilenumbrüche und Tabs' => ["<x-consentlaw::jobs>\n\t\n  </x-consentlaw::jobs>"],
        ];
    }

    // AK3
    #[DataProvider('leereSlots')]
    public function test_leerer_slot_zaehlt_als_kein_slot(string $blade): void
    {
        $this->keineStellen();

        $absatz = $this->leerAbsatz($this->rendere($blade));

        $this->assertSame(self::STANDARDTEXT, $this->text($absatz));
    }

    // ── AK4 ──────────────────────────────────────────────────────────────────

    // AK4
    public function test_mit_stelle_weder_standardtext_noch_initiativ_satz_liste_und_json_ld_bleiben(): void
    {
        $this->konfig('jobs.initiativ_email', self::ADRESSE);

        $this->keineStellen();
        $leer = $this->rendere('<x-consentlaw::jobs />');
        $this->assertStringContainsString($this->initiativSatz(self::ADRESSE), $this->text($this->leerAbsatz($leer)), 'Gegenprobe: Ohne Stelle erscheinen beide.');

        // Neue App: Der Cache hält sonst die leere Liste fest.
        $this->appNeuBauen('testing');
        $this->konfig('jobs.initiativ_email', self::ADRESSE);
        $this->api(['*' => $this->jobsPayload(['title' => 'Eindeutige Stelle'])]);
        $html = $this->rendere('<x-consentlaw::jobs />');

        $this->assertStringContainsString('Eindeutige Stelle', $html);
        $this->assertSame(1, substr_count($html, 'class="consentlaw-job"'), 'Die Liste bleibt.');
        $this->assertSame(1, substr_count($html, 'application/ld+json'), 'Das JSON-LD bleibt.');
        $this->assertStringNotContainsString(self::STANDARDTEXT, $html);
        $this->assertStringNotContainsString('Initiativbewerbungen gern an', $html);
        $this->assertSame(0, $this->anzahlLeerAbsaetze($html));
    }

    // ── AK5 ──────────────────────────────────────────────────────────────────

    /** @return array<string, array{0: string|null}> */
    public static function mitUndOhneAdresse(): array
    {
        return [
            'ohne Adresse' => [null],
            'mit Adresse' => [self::ADRESSE],
        ];
    }

    // AK5
    #[DataProvider('mitUndOhneAdresse')]
    public function test_kein_json_ld_im_leeren_fall(?string $adresse): void
    {
        $this->konfig('jobs.initiativ_email', $adresse);

        $this->api(['*' => $this->jobsPayload()]);
        $this->assertStringContainsString('application/ld+json', $this->rendere('<x-consentlaw::jobs />'), 'Gegenprobe: Mit Stelle gibt es JSON-LD.');

        // Neue App: Der Cache hält sonst die Stelle fest.
        $this->appNeuBauen('testing');
        $this->konfig('jobs.initiativ_email', $adresse);
        $this->keineStellen();
        $html = $this->rendere('<x-consentlaw::jobs />');

        $this->assertSame(self::STANDARDTEXT, substr($this->text($this->leerAbsatz($html)), 0, strlen(self::STANDARDTEXT)), 'Der leere Fall wurde gerendert.');
        $this->assertStringNotContainsString('application/ld+json', $html);
    }

    // ── AK6 ──────────────────────────────────────────────────────────────────

    // AK6
    public function test_ohne_token_erscheint_der_standardtext(): void
    {
        $this->konfig('token', '');
        $this->api(['*' => $this->jobsPayload()]);

        $absatz = $this->leerAbsatz($this->rendere('<x-consentlaw::jobs />'));

        $this->assertSame(self::STANDARDTEXT, $this->text($absatz));
        $this->assertSame(0, $this->anzahlAufrufe(), 'Ohne Token kein API-Aufruf.');
    }

    // AK6
    public function test_seite_antwortet_ohne_token_ohne_fehler_mit_standardtext(): void
    {
        $this->mitRouten();
        $this->konfig('token', '');
        $this->api(['*' => $this->jobsPayload()]);

        [$status, $html] = $this->seite('jobs');

        $this->assertSame(200, $status);
        $this->assertSame(self::STANDARDTEXT, $this->text($this->leerAbsatz($html)));
    }

    // ── AK7 ──────────────────────────────────────────────────────────────────

    // AK7
    public function test_paket_seite_uebergibt_keinen_eigenen_slot(): void
    {
        $quelle = (string) file_get_contents(__DIR__.'/../src/Resources/views/pages/jobs.blade.php');

        $this->assertStringContainsString('<x-consentlaw::jobs', $quelle, 'Gegenprobe: Die Seite nutzt die Komponente.');
        $this->assertStringNotContainsString('</x-consentlaw::jobs>', $quelle);
        $this->assertStringNotContainsString(self::ALTER_SEITENTEXT, $quelle);
    }

    // AK7
    public function test_paket_seite_zeigt_ohne_stelle_den_standardtext(): void
    {
        $this->mitRouten();
        $this->keineStellen();

        [$status, $html] = $this->seite('jobs');

        $this->assertSame(200, $status);
        $this->assertStringContainsString('<h1>Stellenangebote</h1>', $html, 'Gegenprobe: Die Paket-Seite wurde gerendert.');
        $this->assertSame(self::STANDARDTEXT, $this->text($this->leerAbsatz($html)));
        $this->assertStringNotContainsString(self::ALTER_SEITENTEXT, $html);
    }

    // AK7
    public function test_paket_seite_zeigt_den_initiativ_satz_falls_konfiguriert(): void
    {
        $this->mitRouten();
        $this->konfig('jobs.initiativ_email', self::ADRESSE);
        $this->keineStellen();

        [$status, $html] = $this->seite('jobs');

        $this->assertSame(200, $status);
        $absatz = $this->leerAbsatz($html);
        $this->assertSame(self::STANDARDTEXT.' '.$this->initiativSatz(self::ADRESSE), $this->text($absatz));
        $this->assertMailtoLink($absatz, self::ADRESSE);
    }

    // ── AK8 ──────────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function configDatei(): array
    {
        return require __DIR__.'/../src/Config/consentlaw.php';
    }

    // AK8
    public function test_config_enthaelt_initiativ_email_mit_standard_null(): void
    {
        $config = $this->configDatei();

        $this->assertArrayHasKey('jobs', $config);
        $this->assertIsArray($config['jobs']);
        $this->assertArrayHasKey('initiativ_email', $config['jobs']);
        $this->assertNull($config['jobs']['initiativ_email']);
        $this->assertNull($this->app['config']->get('consentlaw.jobs.initiativ_email', 'fehlt'), 'Auch in der App ist der Standard null.');
    }

    // AK8
    public function test_config_liest_die_umgebungsvariable(): void
    {
        $this->setzeUmgebung('CONSENTLAW_JOBS_INITIATIV_EMAIL', self::ADRESSE);

        $this->assertSame(self::ADRESSE, $this->configDatei()['jobs']['initiativ_email'] ?? null);
    }

    /** Abschnitt der README (von Überschrift zu Überschrift), der das Attribut initiativ-email nennt. */
    private function readmeAbschnittJobs(): string
    {
        $readme = (string) file_get_contents(__DIR__.'/../README.md');

        foreach (preg_split('/^(?=#{1,6} )/m', $readme) ?: [] as $abschnitt) {
            if (str_contains($abschnitt, 'initiativ-email')) {
                return $abschnitt;
            }
        }

        $this->fail('Kein README-Abschnitt nennt das Attribut initiativ-email.');
    }

    // AK8
    public function test_readme_abschnitt_jobs_beschreibt_standardtext_konfiguration_attribut_und_slot(): void
    {
        $abschnitt = $this->readmeAbschnittJobs();

        $this->assertStringContainsString('<x-consentlaw::jobs', $abschnitt, 'Es ist der Abschnitt zur Jobs-Komponente.');
        $this->assertStringContainsString(self::STANDARDTEXT, $abschnitt, 'Der Standardtext wird genannt.');
        $this->assertStringContainsString('initiativ_email', $abschnitt, 'Die Konfiguration wird genannt.');
        $this->assertStringContainsString('CONSENTLAW_JOBS_INITIATIV_EMAIL', $abschnitt, 'Die Umgebungsvariable wird genannt.');
        $this->assertStringContainsString('initiativ-email', $abschnitt, 'Das Attribut wird genannt.');
        $this->assertMatchesRegularExpression('/Slot[^\n]*ersetzt|ersetzt[^\n]*Slot/iu', $abschnitt, 'Dass ein Slot den Standardtext ersetzt.');
    }
}
