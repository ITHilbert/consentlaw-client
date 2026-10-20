<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Tests;

use ITHilbert\ConsentLaw\Data\HiringOrganization;
use ITHilbert\ConsentLaw\Data\Job;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * AK1 aus .project/features/jobs-google-for-jobs/spec.md – neue Felder lesen.
 *
 * Öffentliche Namen: Job::$hiringOrganization (?HiringOrganization mit name, url, logo),
 * Job::$remote (bool), Job::$applicantCountries (list<string>).
 */
final class JobGoogleFuerJobsDatenTest extends TestCase
{
    /** @param  array<string, mixed>  $ueberschreibungen */
    private function job(array $ueberschreibungen = []): Job
    {
        return Job::fromApi(array_merge([
            'id' => 'job-1',
            'title' => 'PHP-Entwickler',
            'description' => 'Text',
            'description_format' => 'markdown',
        ], $ueberschreibungen));
    }

    // AK1
    public function test_liest_arbeitgeber_remote_und_laender(): void
    {
        $job = $this->job([
            'hiring_organization' => ['name' => 'IT-Hilbert GmbH', 'url' => 'https://it-hilbert.com', 'logo' => 'https://it-hilbert.com/logo.png'],
            'remote' => true,
            'applicant_countries' => ['DE', 'AT'],
        ]);

        $this->assertInstanceOf(HiringOrganization::class, $job->hiringOrganization);
        $this->assertSame('IT-Hilbert GmbH', $job->hiringOrganization->name);
        $this->assertSame('https://it-hilbert.com', $job->hiringOrganization->url);
        $this->assertSame('https://it-hilbert.com/logo.png', $job->hiringOrganization->logo);
        $this->assertTrue($job->remote);
        $this->assertSame(['DE', 'AT'], $job->applicantCountries);
    }

    // AK1
    public function test_arbeitgeber_ohne_url_und_logo_hat_null(): void
    {
        $job = $this->job(['hiring_organization' => ['name' => 'Firma', 'url' => null, 'logo' => null]]);

        $this->assertSame('Firma', $job->hiringOrganization->name);
        $this->assertNull($job->hiringOrganization->url);
        $this->assertNull($job->hiringOrganization->logo);
    }

    // AK1
    public function test_antwort_ohne_die_neuen_felder_gilt_als_nicht_remote_ohne_arbeitgeber(): void
    {
        $job = $this->job();

        $this->assertNull($job->hiringOrganization);
        $this->assertFalse($job->remote);
        $this->assertSame([], $job->applicantCountries);
    }

    // AK1
    #[DataProvider('falscheTypen')]
    public function test_falsche_typen_werfen_nicht_und_ergeben_die_standardwerte(mixed $arbeitgeber, mixed $remote, mixed $laender): void
    {
        $job = $this->job([
            'hiring_organization' => $arbeitgeber,
            'remote' => $remote,
            'applicant_countries' => $laender,
        ]);

        $this->assertNull($job->hiringOrganization);
        $this->assertFalse($job->remote);
        $this->assertSame([], $job->applicantCountries);
    }

    /** @return array<string, array{mixed, mixed, mixed}> */
    public static function falscheTypen(): array
    {
        return [
            'String / String / String' => ['Firma GmbH', 'ja', 'DE'],
            'Liste / Zahl / Objekt-Array' => [['Firma', 'GmbH'], 1, ['land' => ['DE']]],
            'null überall' => [null, null, null],
            'Zahl / Array / Zahl' => [42, ['x'], 7],
        ];
    }

    // AK1 (Randfall: Liste als Arbeitgeber)
    public function test_arbeitgeber_als_liste_von_objekten_gilt_als_fehlend(): void
    {
        $job = $this->job(['hiring_organization' => [['name' => 'Firma']]]);

        $this->assertNull($job->hiringOrganization);
    }

    // AK1
    #[DataProvider('arbeitgeberOhneNamen')]
    public function test_arbeitgeber_ohne_nicht_leeren_namen_gilt_als_fehlend(mixed $arbeitgeber): void
    {
        $job = $this->job(['hiring_organization' => $arbeitgeber]);

        $this->assertNull($job->hiringOrganization);
    }

    /** @return array<string, array{mixed}> */
    public static function arbeitgeberOhneNamen(): array
    {
        return [
            'Name fehlt' => [['url' => 'https://example.org']],
            'Name leer' => [['name' => '', 'url' => 'https://example.org']],
            'Name null' => [['name' => null]],
            'Name keine Zeichenkette' => [['name' => 12]],
            'leeres Objekt' => [[]],
        ];
    }

    // AK1
    public function test_laendercodes_werden_normalisiert_gefiltert_und_dedupliziert(): void
    {
        $job = $this->job([
            'remote' => true,
            'applicant_countries' => ['de', 'DEU', '', null, 'DE', 'at', 'D', 'D1', 5, 'AT', 'ch'],
        ]);

        $this->assertSame(['DE', 'AT', 'CH'], array_values($job->applicantCountries));
    }

    // AK1
    public function test_remote_ist_nur_bei_echtem_true_gesetzt(): void
    {
        $this->assertTrue($this->job(['remote' => true])->remote);
        $this->assertFalse($this->job(['remote' => false])->remote);
        $this->assertFalse($this->job(['remote' => 'true'])->remote);
    }
}
