<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Tests;

use Illuminate\Support\Facades\Blade;
use ITHilbert\ConsentLaw\Data\Job;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * AK2–AK6 aus .project/features/jobs-google-for-jobs/spec.md – JSON-LD der Jobs.
 *
 * Gerendert werden `<x-consentlaw::jobs />` (gefakte API) und `<x-consentlaw::jobs-schema :jobs="$jobs" />`;
 * das JSON-LD wird aus dem HTML gelesen und per json_decode geprüft.
 */
final class JobGoogleFuerJobsSchemaTest extends AppTestCase
{
    private const ARBEITGEBER = [
        'name' => 'IT-Hilbert GmbH',
        'url' => 'https://it-hilbert.com',
        'logo' => 'https://it-hilbert.com/logo.png',
    ];

    /** @return list<array<string, mixed>> Alle JSON-LD-Blöcke, gültiges JSON vorausgesetzt. */
    private function jsonLd(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $treffer);

        $liste = [];
        foreach ($treffer[1] as $roh) {
            $daten = json_decode($roh, true);
            $this->assertIsArray($daten, 'JSON-LD muss gültiges JSON sein: '.json_last_error_msg());
            $liste[] = $daten;
        }

        return $liste;
    }

    /** @param  array<string, mixed>  $ueberschreibungen @return array<string, mixed> */
    private function schemaAusApi(array $ueberschreibungen): array
    {
        $this->api(['*' => $this->jobsPayload($ueberschreibungen)]);

        $bloecke = $this->jsonLd($this->rendere('<x-consentlaw::jobs />'));
        $this->assertCount(1, $bloecke, 'Genau ein JSON-LD-Block je Job erwartet.');

        return $bloecke[0];
    }

    /** @param  array<string, mixed>  $ueberschreibungen */
    private function jobObjekt(array $ueberschreibungen = []): Job
    {
        return Job::fromApi($this->jobsPayload($ueberschreibungen)['jobs'][0]);
    }

    // ── AK2: Arbeitgeber ─────────────────────────────────────────────────────

    // AK2
    public function test_arbeitgeber_steht_als_organization_im_schema(): void
    {
        $schema = $this->schemaAusApi(['hiring_organization' => self::ARBEITGEBER]);

        $this->assertSame([
            '@type' => 'Organization',
            'name' => 'IT-Hilbert GmbH',
            'sameAs' => 'https://it-hilbert.com',
            'logo' => 'https://it-hilbert.com/logo.png',
        ], $schema['hiringOrganization']);
    }

    // AK2
    public function test_same_as_und_logo_fehlen_wenn_nicht_gesetzt(): void
    {
        $schema = $this->schemaAusApi(['hiring_organization' => ['name' => 'Firma', 'url' => null, 'logo' => null]]);

        $this->assertSame(['@type' => 'Organization', 'name' => 'Firma'], $schema['hiringOrganization']);
    }

    // AK2
    public function test_url_und_logo_werden_nur_als_absolute_http_urls_uebernommen(): void
    {
        $schema = $this->schemaAusApi(['hiring_organization' => [
            'name' => 'Firma',
            'url' => 'javascript:alert(1)',
            'logo' => '/logo.png',
        ]]);

        $this->assertSame(['@type' => 'Organization', 'name' => 'Firma'], $schema['hiringOrganization']);
    }

    /** @return array<string, array{string}> */
    public static function ungueltigeUrls(): array
    {
        return [
            'Leerzeichen im Host' => ['https://a b'],
            'Leerzeichen im Pfad' => ['https://example.org/mein logo.png'],
            'führendes Leerzeichen' => [' https://example.org'],
            'Steuerzeichen im Host' => ["https://exa\x00mple.org"],
            'Zeilenumbruch im Pfad' => ["https://example.org/a\nb"],
            'Tabulator' => ["https://example.org/\tlogo.png"],
            'ohne Host' => ['https://'],
            'ohne Host, mit Pfad' => ['https:///logo.png'],
            'protokollrelativ' => ['//example.org/logo.png'],
        ];
    }

    // AK2
    #[DataProvider('ungueltigeUrls')]
    public function test_ungueltige_urls_ergeben_weder_same_as_noch_logo(string $url): void
    {
        $schema = $this->schemaAusApi(['hiring_organization' => ['name' => 'Firma', 'url' => $url, 'logo' => $url]]);

        $this->assertSame(['@type' => 'Organization', 'name' => 'Firma'], $schema['hiringOrganization']);
    }

    // AK2
    public function test_http_url_ohne_tls_wird_uebernommen_ftp_nicht(): void
    {
        $schema = $this->schemaAusApi(['hiring_organization' => [
            'name' => 'Firma',
            'url' => 'http://firma.example',
            'logo' => 'ftp://firma.example/logo.png',
        ]]);

        $this->assertSame('http://firma.example', $schema['hiringOrganization']['sameAs']);
        $this->assertArrayNotHasKey('logo', $schema['hiringOrganization']);
    }

    // AK2
    public function test_ohne_arbeitgeber_fehlt_hiring_organization(): void
    {
        $schema = $this->schemaAusApi([]);

        $this->assertArrayNotHasKey('hiringOrganization', $schema);
    }

    // AK2 (Randfall: String statt Objekt)
    public function test_arbeitgeber_als_string_wird_nicht_ausgegeben(): void
    {
        $schema = $this->schemaAusApi(['hiring_organization' => 'Firma GmbH']);

        $this->assertArrayNotHasKey('hiringOrganization', $schema);
    }

    // ── AK3: Homeoffice ──────────────────────────────────────────────────────

    // AK3
    public function test_remote_mit_laendern_liefert_telecommute_und_anforderungen(): void
    {
        $schema = $this->schemaAusApi(['remote' => true, 'applicant_countries' => ['DE', 'AT']]);

        $this->assertSame('TELECOMMUTE', $schema['jobLocationType']);
        $this->assertSame([
            ['@type' => 'Country', 'name' => 'DE'],
            ['@type' => 'Country', 'name' => 'AT'],
        ], $schema['applicantLocationRequirements']);
    }

    // AK3
    public function test_nicht_remote_hat_keine_homeoffice_felder(): void
    {
        $schema = $this->schemaAusApi(['remote' => false, 'applicant_countries' => ['DE']]);

        $this->assertArrayNotHasKey('jobLocationType', $schema);
        $this->assertArrayNotHasKey('applicantLocationRequirements', $schema);
    }

    // AK3
    public function test_remote_ohne_laender_liefert_nur_job_location_type(): void
    {
        $schema = $this->schemaAusApi(['remote' => true, 'applicant_countries' => []]);

        $this->assertSame('TELECOMMUTE', $schema['jobLocationType']);
        $this->assertArrayNotHasKey('applicantLocationRequirements', $schema);
    }

    // AK3 (Randfall: unsaubere Ländercodes)
    public function test_nur_gueltige_laendercodes_landen_im_schema(): void
    {
        $schema = $this->schemaAusApi(['remote' => true, 'applicant_countries' => ['de', 'DEU', '', null, 'DE']]);

        $this->assertSame([['@type' => 'Country', 'name' => 'DE']], $schema['applicantLocationRequirements']);
    }

    // ── AK4: Ort bleibt ──────────────────────────────────────────────────────

    // AK4
    public function test_job_location_bleibt_auch_bei_remote_erhalten(): void
    {
        $erwartet = [
            '@type' => 'Place',
            'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Kiel'],
        ];

        $hybrid = $this->schemaAusApi(['location' => 'Kiel', 'remote' => true, 'applicant_countries' => ['DE']]);
        $this->assertSame($erwartet, $hybrid['jobLocation']);
        $this->assertSame('TELECOMMUTE', $hybrid['jobLocationType'], 'Vorbedingung: Hybrid-Stelle hat auch das Homeoffice-Feld.');

        $vorort = $this->schemaAusApi(['location' => 'Kiel']);
        $this->assertSame($erwartet, $vorort['jobLocation']);
    }

    // ── AK5: jobs-schema einzeln nutzbar ─────────────────────────────────────

    // AK5
    public function test_jobs_schema_gibt_je_job_nur_ein_script_aus(): void
    {
        $jobs = collect([
            $this->jobObjekt(['id' => 'a', 'title' => 'Erste Stelle']),
            $this->jobObjekt(['id' => 'b', 'title' => 'Zweite Stelle']),
        ]);

        $html = Blade::render('<x-consentlaw::jobs-schema :jobs="$jobs" />', ['jobs' => $jobs]);

        $bloecke = $this->jsonLd($html);
        $this->assertCount(2, $bloecke);
        $this->assertSame('Erste Stelle', $bloecke[0]['title']);
        $this->assertSame('Zweite Stelle', $bloecke[1]['title']);

        $uebrig = preg_replace('#<script type="application/ld\+json">.*?</script>#s', '', $html);
        $this->assertSame('', trim((string) $uebrig), 'Außer den Scripts darf nichts ausgegeben werden.');
    }

    // AK5
    public function test_jobs_schema_enthaelt_die_neuen_felder(): void
    {
        $jobs = collect([$this->jobObjekt([
            'hiring_organization' => self::ARBEITGEBER,
            'remote' => true,
            'applicant_countries' => ['DE'],
        ])]);

        $schema = $this->jsonLd(Blade::render('<x-consentlaw::jobs-schema :jobs="$jobs" />', ['jobs' => $jobs]))[0];

        $this->assertSame('Organization', $schema['hiringOrganization']['@type']);
        $this->assertSame('TELECOMMUTE', $schema['jobLocationType']);
        $this->assertSame([['@type' => 'Country', 'name' => 'DE']], $schema['applicantLocationRequirements']);
    }

    // AK5 (Randfall: leere Liste)
    public function test_jobs_schema_mit_leerer_liste_gibt_nichts_aus(): void
    {
        $html = Blade::render('<x-consentlaw::jobs-schema :jobs="$jobs" />', ['jobs' => collect()]);

        $this->assertSame('', trim($html));
    }

    // AK5
    public function test_jobs_komponente_erzeugt_dasselbe_json_ld_wie_jobs_schema(): void
    {
        $payload = $this->jobsPayload([
            'hiring_organization' => self::ARBEITGEBER,
            'remote' => true,
            'applicant_countries' => ['DE'],
            'location' => 'Kiel',
            'valid_through' => '2027-01-31T00:00:00+00:00',
        ]);
        $this->api(['*' => $payload]);
        $ausListe = $this->jsonLd($this->rendere('<x-consentlaw::jobs />'));

        $jobs = collect([Job::fromApi($payload['jobs'][0])]);
        $einzeln = $this->jsonLd(Blade::render('<x-consentlaw::jobs-schema :jobs="$jobs" />', ['jobs' => $jobs]));

        $this->assertSame($einzeln, $ausListe);
        $this->assertArrayHasKey('hiringOrganization', $ausListe[0], 'Vorbedingung: neue Felder sind enthalten.');
    }

    // AK5
    public function test_with_schema_false_unterdrueckt_das_json_ld(): void
    {
        $this->api(['*' => $this->jobsPayload(['hiring_organization' => self::ARBEITGEBER])]);

        $html = $this->rendere('<x-consentlaw::jobs :with-schema="false" />');

        $this->assertStringContainsString('PHP-Entwickler', $html, 'Vorbedingung: Die Liste wird gerendert.');
        $this->assertStringNotContainsString('application/ld+json', $html);
    }

    // AK5
    public function test_bisherige_felder_bleiben_unveraendert(): void
    {
        $schema = $this->schemaAusApi([
            'location' => 'Kiel',
            'valid_through' => '2027-01-31T00:00:00+00:00',
        ]);

        $this->assertSame('https://schema.org', $schema['@context']);
        $this->assertSame('JobPosting', $schema['@type']);
        $this->assertSame('PHP-Entwickler', $schema['title']);
        $this->assertSame('Wir suchen Verstärkung.', trim(strip_tags($schema['description'])));
        $this->assertSame('FULL_TIME', $schema['employmentType']);
        $this->assertSame('2026-09-01T10:00:00+00:00', $schema['datePosted']);
        $this->assertSame('2027-01-31T00:00:00+00:00', $schema['validThrough']);
        $this->assertSame('Kiel', $schema['jobLocation']['address']['addressLocality']);
    }

    // ── AK6: Sicher ausgegeben ───────────────────────────────────────────────

    // AK6
    public function test_arbeitgeber_name_kann_das_script_nicht_verlassen(): void
    {
        $boese = '</script><script>alert(1)</script>';
        $this->api(['*' => $this->jobsPayload(['hiring_organization' => ['name' => $boese]])]);

        $html = $this->rendere('<x-consentlaw::jobs />');

        $this->assertStringNotContainsString('</script><script>', $html);
        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertSame($boese, $this->jsonLd($html)[0]['hiringOrganization']['name'], 'Der Wert bleibt nach json_decode unverändert.');
    }

    // AK6
    public function test_auch_jobs_schema_escaped_den_arbeitgeber_namen(): void
    {
        $boese = '</script><script>alert(1)</script>';
        $jobs = collect([$this->jobObjekt(['hiring_organization' => ['name' => $boese]])]);

        $html = Blade::render('<x-consentlaw::jobs-schema :jobs="$jobs" />', ['jobs' => $jobs]);

        $this->assertStringNotContainsString('</script><script>', $html);
        $this->assertSame(1, substr_count($html, '</script>'), 'Genau ein schließendes Script-Tag.');
        $this->assertSame($boese, $this->jsonLd($html)[0]['hiringOrganization']['name']);
    }
}
