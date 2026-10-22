<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Tests;

use Illuminate\Support\Facades\Blade;
use ITHilbert\ConsentLaw\Data\BaseSalary;
use ITHilbert\ConsentLaw\Data\Job;
use ITHilbert\ConsentLaw\Data\JobAddress;
use ITHilbert\ConsentLaw\Support\JobPostingSchema;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * AK10–AK14 aus .project/features/google-job-schema/spec.md (Abschnitt consentlaw-client).
 *
 * Festgelegte Namen:
 *  - Job::$workLocation (?string: 'remote' | 'vor_ort' | null)
 *  - Job::$address (?JobAddress: streetAddress, postalCode, addressLocality, addressCountry – alle ?string)
 *  - Job::$baseSalary (?BaseSalary: currency string, unit string, value / minValue / maxValue je int|float|null)
 */
final class JobGoogleSchemaOrtGehaltTest extends AppTestCase
{
    private const ADRESSE = [
        'street_address' => 'Hafenstraße 1',
        'postal_code' => '24103',
        'address_locality' => 'Kiel',
        'address_country' => 'DE',
    ];

    /** @param  array<string, mixed>  $ueberschreibungen */
    private function job(array $ueberschreibungen = []): Job
    {
        return Job::fromApi($this->jobsPayload($ueberschreibungen)['jobs'][0]);
    }

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
        // Jeder Aufruf rendert den eigenen Payload: sonst liefert der Client-Cache den des ersten Aufrufs.
        $this->app['cache']->flush();
        $this->api(['*' => $this->jobsPayload($ueberschreibungen)]);

        $bloecke = $this->jsonLd($this->rendere('<x-consentlaw::jobs />'));
        $this->assertCount(1, $bloecke, 'Genau ein JSON-LD-Block je Job erwartet.');

        return $bloecke[0];
    }

    // ── AK10: Daten lesen ────────────────────────────────────────────────────

    // AK10
    public function test_liest_arbeitsort_adresse_und_gehalt(): void
    {
        $job = $this->job([
            'work_location' => 'vor_ort',
            'address' => self::ADRESSE,
            'base_salary' => ['currency' => 'EUR', 'min_value' => 3500, 'max_value' => 5000.5, 'unit' => 'MONTH'],
        ]);

        $this->assertSame('vor_ort', $job->workLocation);

        $this->assertInstanceOf(JobAddress::class, $job->address);
        $this->assertSame('Hafenstraße 1', $job->address->streetAddress);
        $this->assertSame('24103', $job->address->postalCode);
        $this->assertSame('Kiel', $job->address->addressLocality);
        $this->assertSame('DE', $job->address->addressCountry);

        $this->assertInstanceOf(BaseSalary::class, $job->baseSalary);
        $this->assertSame('EUR', $job->baseSalary->currency);
        $this->assertSame('MONTH', $job->baseSalary->unit);
        $this->assertEquals(3500, $job->baseSalary->minValue);
        $this->assertEquals(5000.5, $job->baseSalary->maxValue);
        $this->assertNull($job->baseSalary->value);
    }

    // AK10
    public function test_liest_remote_als_arbeitsort(): void
    {
        $job = $this->job(['work_location' => 'remote', 'remote' => true, 'applicant_countries' => ['DE'], 'address' => null, 'base_salary' => null]);

        $this->assertSame('remote', $job->workLocation);
        $this->assertNull($job->address);
        $this->assertNull($job->baseSalary);
        $this->assertSame(['DE'], $job->applicantCountries);
    }

    // AK10
    public function test_liest_einzelbetrag_als_value(): void
    {
        $job = $this->job(['base_salary' => ['currency' => 'CHF', 'value' => 60, 'unit' => 'HOUR']]);

        $this->assertEquals(60, $job->baseSalary->value);
        $this->assertNull($job->baseSalary->minValue);
        $this->assertNull($job->baseSalary->maxValue);
        $this->assertSame('CHF', $job->baseSalary->currency);
        $this->assertSame('HOUR', $job->baseSalary->unit);
    }

    // AK10
    public function test_einzelne_adresswerte_null_bleiben_null(): void
    {
        $job = $this->job(['address' => ['street_address' => null, 'postal_code' => null, 'address_locality' => 'Kiel', 'address_country' => 'DE']]);

        $this->assertInstanceOf(JobAddress::class, $job->address);
        $this->assertNull($job->address->streetAddress);
        $this->assertNull($job->address->postalCode);
        $this->assertSame('Kiel', $job->address->addressLocality);
    }

    // AK10
    public function test_heutige_antwort_ohne_die_felder_hat_weder_adresse_noch_gehalt(): void
    {
        $job = $this->job();

        $this->assertNull($job->workLocation);
        $this->assertNull($job->address);
        $this->assertNull($job->baseSalary);
    }

    /** @return array<string, array{mixed}> */
    public static function falscheTypenFuerAdresse(): array
    {
        return [
            'String' => ['Kiel'],
            'Zahl' => [42],
            'true' => [true],
            'Liste' => [['Kiel', 'Hamburg']],
        ];
    }

    // AK10
    #[DataProvider('falscheTypenFuerAdresse')]
    public function test_falscher_typ_bei_adresse_ergibt_keine_adresse_ohne_fehler(mixed $wert): void
    {
        $job = $this->job(['address' => $wert]);

        $this->assertNull($job->address);
    }

    /** @return array<string, array{mixed}> */
    public static function ungueltigesGehalt(): array
    {
        return [
            'String statt Objekt' => ['3000 EUR'],
            'Zahl statt Objekt' => [3000],
            'leeres Objekt' => [[]],
            'Einheit unbekannt' => [['currency' => 'EUR', 'value' => 3000, 'unit' => 'DECADE']],
            'Einheit klein geschrieben' => [['currency' => 'EUR', 'value' => 3000, 'unit' => 'month']],
            'Einheit fehlt' => [['currency' => 'EUR', 'value' => 3000]],
            'Einheit kein String' => [['currency' => 'EUR', 'value' => 3000, 'unit' => 5]],
            'Währung zu lang' => [['currency' => 'EURO', 'value' => 3000, 'unit' => 'MONTH']],
            'Währung zu kurz' => [['currency' => 'EU', 'value' => 3000, 'unit' => 'MONTH']],
            'Währung mit Ziffer' => [['currency' => 'E1R', 'value' => 3000, 'unit' => 'MONTH']],
            'Währung fehlt' => [['value' => 3000, 'unit' => 'MONTH']],
            'Währung kein String' => [['currency' => 978, 'value' => 3000, 'unit' => 'MONTH']],
            'Betrag Text' => [['currency' => 'EUR', 'value' => 'viel', 'unit' => 'MONTH']],
            'Betrag negativ' => [['currency' => 'EUR', 'value' => -5, 'unit' => 'MONTH']],
            'Spanne mit Text' => [['currency' => 'EUR', 'min_value' => 'a', 'max_value' => 'b', 'unit' => 'MONTH']],
            'Spanne negativ' => [['currency' => 'EUR', 'min_value' => -1, 'max_value' => 10, 'unit' => 'MONTH']],
            'kein Betrag' => [['currency' => 'EUR', 'unit' => 'MONTH']],
            'Betrag null' => [['currency' => 'EUR', 'value' => null, 'unit' => 'MONTH']],
        ];
    }

    // AK10
    #[DataProvider('ungueltigesGehalt')]
    public function test_ungueltiges_gehalt_ergibt_kein_gehalt_ohne_fehler(mixed $wert): void
    {
        $job = $this->job(['base_salary' => $wert]);

        $this->assertNull($job->baseSalary);
    }

    /** @return array<string, array{string}> */
    public static function gueltigeEinheiten(): array
    {
        return ['HOUR' => ['HOUR'], 'DAY' => ['DAY'], 'WEEK' => ['WEEK'], 'MONTH' => ['MONTH'], 'YEAR' => ['YEAR']];
    }

    // AK10
    #[DataProvider('gueltigeEinheiten')]
    public function test_alle_gehaltseinheiten_aus_der_liste_werden_gelesen(string $einheit): void
    {
        $job = $this->job(['base_salary' => ['currency' => 'EUR', 'value' => 100, 'unit' => $einheit]]);

        $this->assertSame($einheit, $job->baseSalary?->unit);
    }

    // AK10
    public function test_falscher_typ_bei_arbeitsort_ergibt_keinen_arbeitsort(): void
    {
        foreach ([123, true, ['remote'], 'hybrid', ''] as $wert) {
            $this->assertNull($this->job(['work_location' => $wert])->workLocation, 'Wert: '.json_encode($wert));
        }
    }

    // AK10
    public function test_laendercodes_nur_mit_zwei_buchstaben_auch_bei_arbeitsort_remote(): void
    {
        $job = $this->job(['work_location' => 'remote', 'applicant_countries' => ['de', 'DEU', '', null, 5, 'AT', 'DE']]);

        $this->assertSame(['DE', 'AT'], $job->applicantCountries);
    }

    // AK10
    public function test_land_der_adresse_nur_mit_zwei_buchstaben(): void
    {
        $this->assertNull($this->job(['address' => array_merge(self::ADRESSE, ['address_country' => 'DEU'])])->address?->addressCountry);
        $this->assertSame('AT', $this->job(['address' => array_merge(self::ADRESSE, ['address_country' => 'at'])])->address?->addressCountry);
    }

    // ── AK11: Ort im Schema ──────────────────────────────────────────────────

    // AK11
    public function test_adresse_wird_zu_place_mit_postal_address(): void
    {
        $schema = $this->schemaAusApi(['work_location' => 'vor_ort', 'address' => self::ADRESSE]);

        $this->assertEquals([
            '@type' => 'Place',
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => 'Hafenstraße 1',
                'postalCode' => '24103',
                'addressLocality' => 'Kiel',
                'addressCountry' => 'DE',
            ],
        ], $schema['jobLocation']);
    }

    // AK11
    public function test_leere_adressteile_entfallen_im_schema(): void
    {
        $schema = $this->schemaAusApi(['work_location' => 'vor_ort', 'address' => [
            'street_address' => null, 'postal_code' => '', 'address_locality' => 'Kiel', 'address_country' => 'DE',
        ]]);

        $this->assertEquals([
            '@type' => 'PostalAddress',
            'addressLocality' => 'Kiel',
            'addressCountry' => 'DE',
        ], $schema['jobLocation']['address']);
    }

    // AK11
    public function test_adresse_gewinnt_gegen_den_freitext_ort(): void
    {
        $schema = $this->schemaAusApi(['location' => 'Hamburg', 'work_location' => 'vor_ort', 'address' => self::ADRESSE]);

        $this->assertSame('Kiel', $schema['jobLocation']['address']['addressLocality']);
        $this->assertSame('DE', $schema['jobLocation']['address']['addressCountry']);
    }

    // AK11
    public function test_ohne_adresse_aber_mit_location_bleibt_job_location_wie_heute(): void
    {
        $erwartet = ['@type' => 'Place', 'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Kiel']];

        $this->assertEquals($erwartet, $this->schemaAusApi(['location' => 'Kiel'])['jobLocation']);
        $this->assertEquals(
            ['@type' => 'Place', 'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Hamburg']],
            $this->schemaAusApi(['location' => 'Hamburg', 'work_location' => 'vor_ort', 'address' => null])['jobLocation'],
            'Der zweite Aufruf rendert seinen eigenen Payload.',
        );
    }

    // AK11
    public function test_ohne_adresse_und_ohne_location_fehlt_job_location(): void
    {
        $schema = $this->schemaAusApi(['location' => null, 'work_location' => 'vor_ort', 'address' => null]);

        $this->assertArrayNotHasKey('jobLocation', $schema);
    }

    // ── AK12: Remote und vor Ort ─────────────────────────────────────────────

    // AK12
    public function test_remote_liefert_telecommute_und_laender_aber_keine_job_location(): void
    {
        $schema = $this->schemaAusApi([
            'work_location' => 'remote', 'remote' => true, 'applicant_countries' => ['DE', 'AT'], 'address' => null,
        ]);

        $this->assertSame('TELECOMMUTE', $schema['jobLocationType']);
        $this->assertSame([
            ['@type' => 'Country', 'name' => 'DE'],
            ['@type' => 'Country', 'name' => 'AT'],
        ], $schema['applicantLocationRequirements']);
        $this->assertArrayNotHasKey('jobLocation', $schema);
    }

    // AK12
    public function test_remote_laesst_job_location_auch_bei_gesetztem_location_und_adresse_weg(): void
    {
        $schema = $this->schemaAusApi([
            'location' => 'Kiel', 'work_location' => 'remote', 'remote' => true,
            'applicant_countries' => ['DE'], 'address' => self::ADRESSE,
        ]);

        $this->assertSame('TELECOMMUTE', $schema['jobLocationType']);
        $this->assertArrayNotHasKey('jobLocation', $schema);
    }

    // AK12
    public function test_work_location_remote_gilt_auch_wenn_remote_flag_fehlt(): void
    {
        $schema = $this->schemaAusApi(['work_location' => 'remote', 'applicant_countries' => ['DE']]);

        $this->assertSame('TELECOMMUTE', $schema['jobLocationType']);
        $this->assertSame([['@type' => 'Country', 'name' => 'DE']], $schema['applicantLocationRequirements']);
        $this->assertArrayNotHasKey('jobLocation', $schema);
    }

    // AK12
    public function test_vor_ort_hat_weder_job_location_type_noch_bewerberlaender(): void
    {
        $schema = $this->schemaAusApi([
            'work_location' => 'vor_ort', 'remote' => false, 'applicant_countries' => [], 'address' => self::ADRESSE,
        ]);

        $this->assertArrayNotHasKey('jobLocationType', $schema);
        $this->assertArrayNotHasKey('applicantLocationRequirements', $schema);
        $this->assertArrayHasKey('jobLocation', $schema);
    }

    // AK12
    public function test_vor_ort_gewinnt_gegen_ein_widerspruechliches_remote_flag(): void
    {
        $schema = $this->schemaAusApi([
            'work_location' => 'vor_ort', 'remote' => true, 'applicant_countries' => ['DE'], 'address' => self::ADRESSE,
        ]);

        $this->assertArrayNotHasKey('jobLocationType', $schema);
        $this->assertArrayNotHasKey('applicantLocationRequirements', $schema);
    }

    // AK12
    public function test_ohne_work_location_gilt_weiter_das_remote_flag(): void
    {
        $remote = $this->schemaAusApi(['remote' => true, 'applicant_countries' => ['DE']]);
        $this->assertSame('TELECOMMUTE', $remote['jobLocationType']);
        $this->assertSame([['@type' => 'Country', 'name' => 'DE']], $remote['applicantLocationRequirements']);

        $keinRemote = $this->schemaAusApi(['remote' => false, 'applicant_countries' => ['DE']]);
        $this->assertArrayNotHasKey('jobLocationType', $keinRemote);
        $this->assertArrayNotHasKey('applicantLocationRequirements', $keinRemote);
    }

    // AK12
    public function test_ohne_work_location_bleibt_job_location_auch_bei_remote_flag_erhalten(): void
    {
        $schema = $this->schemaAusApi(['location' => 'Kiel', 'remote' => true, 'applicant_countries' => ['DE']]);

        $this->assertSame('Kiel', $schema['jobLocation']['address']['addressLocality']);
        $this->assertSame('TELECOMMUTE', $schema['jobLocationType']);
    }

    // AK12
    public function test_unbekannter_arbeitsort_wird_wie_fehlender_behandelt(): void
    {
        $schema = $this->schemaAusApi(['work_location' => 'hybrid', 'remote' => true, 'applicant_countries' => ['DE'], 'location' => 'Kiel']);

        $this->assertSame('TELECOMMUTE', $schema['jobLocationType']);
        $this->assertSame('Kiel', $schema['jobLocation']['address']['addressLocality']);
    }

    // ── AK13: Gehalt im Schema ───────────────────────────────────────────────

    // AK13
    public function test_einzelbetrag_wird_monetary_amount_mit_value(): void
    {
        $schema = $this->schemaAusApi(['base_salary' => ['currency' => 'EUR', 'value' => 4500, 'unit' => 'MONTH']]);

        $this->assertEquals([
            '@type' => 'MonetaryAmount',
            'currency' => 'EUR',
            'value' => ['@type' => 'QuantitativeValue', 'value' => 4500, 'unitText' => 'MONTH'],
        ], $schema['baseSalary']);
    }

    // AK13
    public function test_spanne_wird_min_value_und_max_value(): void
    {
        $schema = $this->schemaAusApi(['base_salary' => ['currency' => 'CHF', 'min_value' => 3500, 'max_value' => 5000.5, 'unit' => 'YEAR']]);

        $this->assertEquals([
            '@type' => 'MonetaryAmount',
            'currency' => 'CHF',
            'value' => ['@type' => 'QuantitativeValue', 'minValue' => 3500, 'maxValue' => 5000.5, 'unitText' => 'YEAR'],
        ], $schema['baseSalary']);
        $this->assertArrayNotHasKey('value', $schema['baseSalary']['value']);
    }

    // AK13
    public function test_einzelbetrag_hat_kein_min_und_max(): void
    {
        $schema = $this->schemaAusApi(['base_salary' => ['currency' => 'EUR', 'value' => 45, 'unit' => 'HOUR']]);

        $this->assertArrayNotHasKey('minValue', $schema['baseSalary']['value']);
        $this->assertArrayNotHasKey('maxValue', $schema['baseSalary']['value']);
    }

    // AK13
    public function test_ohne_gehalt_fehlt_base_salary(): void
    {
        $this->assertArrayNotHasKey('baseSalary', $this->schemaAusApi([]));
        $this->assertArrayNotHasKey('baseSalary', $this->schemaAusApi(['base_salary' => null]));
        $this->assertArrayNotHasKey('baseSalary', $this->schemaAusApi(['base_salary' => ['currency' => 'EUR', 'value' => 10, 'unit' => 'DECADE']]));
    }

    // AK13
    public function test_schema_array_und_json_stimmen_beim_gehalt_ueberein(): void
    {
        $job = $this->job(['base_salary' => ['currency' => 'USD', 'value' => 100, 'unit' => 'DAY']]);

        $this->assertEquals(JobPostingSchema::array($job)['baseSalary'], json_decode(JobPostingSchema::json($job), true)['baseSalary']);
        $this->assertSame('MonetaryAmount', JobPostingSchema::array($job)['baseSalary']['@type']);
    }

    // ── AK14: Sicher ausgegeben und dokumentiert ─────────────────────────────

    // AK14
    public function test_adresswerte_koennen_das_script_nicht_verlassen(): void
    {
        $boese = '</script><script>alert(1)</script>';
        $this->api(['*' => $this->jobsPayload([
            'work_location' => 'vor_ort',
            'address' => [
                'street_address' => $boese, 'postal_code' => $boese,
                'address_locality' => $boese, 'address_country' => 'DE',
            ],
            'hiring_organization' => ['name' => $boese, 'url' => 'https://firma.example'],
        ])]);

        $html = $this->rendere('<x-consentlaw::jobs />');

        $this->assertStringNotContainsString('</script><script>', $html);
        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertSame(1, substr_count($html, '</script>'), 'Genau ein schließendes Script-Tag.');

        $schema = $this->jsonLd($html)[0];
        $adresse = $schema['jobLocation']['address'];
        $this->assertSame($boese, $adresse['streetAddress']);
        $this->assertSame($boese, $adresse['postalCode']);
        $this->assertSame($boese, $adresse['addressLocality']);
        $this->assertSame($boese, $schema['hiringOrganization']['name']);
    }

    // AK14
    public function test_jobs_schema_komponente_escaped_die_adresse(): void
    {
        $boese = '</script><script>alert(1)</script>';
        $jobs = collect([$this->job(['work_location' => 'vor_ort', 'address' => array_merge(self::ADRESSE, ['street_address' => $boese])])]);

        $html = Blade::render('<x-consentlaw::jobs-schema :jobs="$jobs" />', ['jobs' => $jobs]);

        $this->assertStringNotContainsString('</script><script>', $html);
        $this->assertSame($boese, $this->jsonLd($html)[0]['jobLocation']['address']['streetAddress']);
    }

    // AK14
    public function test_readme_nennt_die_neuen_felder(): void
    {
        $readme = (string) file_get_contents(__DIR__.'/../README.md');

        foreach (['work_location', 'address', 'base_salary', 'workLocation', 'baseSalary', 'jobLocation', 'TELECOMMUTE'] as $begriff) {
            $this->assertStringContainsString($begriff, $readme, "Die README nennt „{$begriff}“ nicht.");
        }
        $this->assertMatchesRegularExpression('/`address`[^\n]*`address`|`address`[^\n]*JobAddress/', $readme, 'Die Tabelle „Felder je Stelle“ führt address mit der Job-Eigenschaft.');
        $this->assertMatchesRegularExpression('/`base_salary`[^\n]*`baseSalary`/', $readme, 'Die Tabelle „Felder je Stelle“ führt base_salary mit der Job-Eigenschaft.');
        $this->assertMatchesRegularExpression('/`work_location`[^\n]*`workLocation`/', $readme, 'Die Tabelle „Felder je Stelle“ führt work_location mit der Job-Eigenschaft.');
    }

    /** Der erste Blade-Codeblock des Abschnitts „Eigenes Markup“ (Muster: JobGoogleFuerJobsReadmeTest). */
    private function readmeBeispiel(): string
    {
        $readme = (string) file_get_contents(__DIR__.'/../README.md');

        if (preg_match('/^(#{2,4}) [^\n]*Eigenes Markup[^\n]*\n(.*?)(?=^#{1,4} |\z)/ms', $readme, $m) !== 1) {
            $this->fail('Die README hat keinen Abschnitt „Eigenes Markup“.');
        }
        if (preg_match('/```blade\n(.*?)\n```/s', $m[2], $b) !== 1) {
            $this->fail('Der Abschnitt „Eigenes Markup“ enthält keinen ```blade-Codeblock.');
        }

        return $b[1];
    }

    // AK14
    public function test_readme_beispiel_rendert_remote_und_vor_ort_stelle_mit_den_neuen_feldern(): void
    {
        $this->api(['*' => ['jobs' => [
            $this->jobsPayload([
                'id' => 'remote', 'title' => 'Remote-Stelle', 'location' => null,
                'work_location' => 'remote', 'remote' => true, 'applicant_countries' => ['DE', 'AT'], 'address' => null,
                'base_salary' => null,
                'hiring_organization' => ['name' => 'IT-Hilbert GmbH', 'url' => 'https://it-hilbert.com'],
            ])['jobs'][0],
            $this->jobsPayload([
                'id' => 'ort', 'title' => 'Vor-Ort-Stelle', 'location' => 'Kiel',
                'work_location' => 'vor_ort', 'remote' => false, 'applicant_countries' => [],
                'address' => self::ADRESSE,
                'base_salary' => ['currency' => 'EUR', 'min_value' => 3500, 'max_value' => 5000, 'unit' => 'MONTH'],
                'hiring_organization' => ['name' => 'IT-Hilbert GmbH', 'url' => 'https://it-hilbert.com'],
            ])['jobs'][0],
        ]]]);

        $html = Blade::render($this->readmeBeispiel());
        $bloecke = $this->jsonLd($html);

        $this->assertCount(2, $bloecke, 'Je Stelle ein JSON-LD.');
        [$remote, $ort] = $bloecke;

        $this->assertSame('Remote-Stelle', $remote['title']);
        $this->assertSame('TELECOMMUTE', $remote['jobLocationType']);
        $this->assertSame([['@type' => 'Country', 'name' => 'DE'], ['@type' => 'Country', 'name' => 'AT']], $remote['applicantLocationRequirements']);
        $this->assertArrayNotHasKey('jobLocation', $remote);
        $this->assertArrayNotHasKey('baseSalary', $remote);
        $this->assertSame('IT-Hilbert GmbH', $remote['hiringOrganization']['name']);

        $this->assertSame('Vor-Ort-Stelle', $ort['title']);
        $this->assertArrayNotHasKey('jobLocationType', $ort);
        $this->assertArrayNotHasKey('applicantLocationRequirements', $ort);
        $this->assertSame('Hafenstraße 1', $ort['jobLocation']['address']['streetAddress']);
        $this->assertSame('24103', $ort['jobLocation']['address']['postalCode']);
        $this->assertSame('Kiel', $ort['jobLocation']['address']['addressLocality']);
        $this->assertSame('DE', $ort['jobLocation']['address']['addressCountry']);
        $this->assertSame('MonetaryAmount', $ort['baseSalary']['@type']);
        $this->assertSame('EUR', $ort['baseSalary']['currency']);
        $this->assertEquals(3500, $ort['baseSalary']['value']['minValue']);
        $this->assertEquals(5000, $ort['baseSalary']['value']['maxValue']);
        $this->assertSame('MONTH', $ort['baseSalary']['value']['unitText']);
    }
}
