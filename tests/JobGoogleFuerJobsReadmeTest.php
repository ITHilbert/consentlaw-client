<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Tests;

use PHPUnit\Framework\TestCase;

/** AK7 aus .project/features/jobs-google-for-jobs/spec.md – README „Eigenes Markup“. */
final class JobGoogleFuerJobsReadmeTest extends TestCase
{
    /** Text des Abschnitts „Eigenes Markup“ (Überschrift bis zur nächsten Überschrift gleicher oder höherer Ebene). */
    private function abschnitt(): string
    {
        $readme = (string) file_get_contents(__DIR__.'/../README.md');

        if (preg_match('/^(#{2,4}) [^\n]*Eigenes Markup[^\n]*\n(.*?)(?=^#{1,4} |\z)/ms', $readme, $m) !== 1) {
            $this->fail('Die README hat keinen Abschnitt „Eigenes Markup“.');
        }

        return $m[2];
    }

    // AK7
    public function test_readme_hat_einen_abschnitt_zu_eigenem_markup_fuer_jobs(): void
    {
        $this->assertStringContainsStringIgnoringCase('job', $this->abschnitt());
    }

    // AK7
    public function test_abschnitt_zeigt_daten_weg_ohne_token_exception(): void
    {
        $this->assertStringContainsString('KomponentenInhalt', $this->abschnitt(), 'Der Weg, der ohne Token nicht wirft.');
    }

    // AK7
    public function test_abschnitt_zeigt_button_per_partial(): void
    {
        $this->assertStringContainsString('consentlaw::components.partials.neu-laden', $this->abschnitt());
    }

    // AK7
    public function test_abschnitt_zeigt_json_ld_per_jobs_schema(): void
    {
        $this->assertStringContainsString('<x-consentlaw::jobs-schema', $this->abschnitt());
    }
}
