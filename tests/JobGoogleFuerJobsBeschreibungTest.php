<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Tests;

use ITHilbert\ConsentLaw\Data\Job;
use ITHilbert\ConsentLaw\Support\MarkdownRenderer;
use PHPUnit\Framework\TestCase;

/** AK8 und AK9 aus .project/features/jobs-google-for-jobs/spec.md. */
final class JobGoogleFuerJobsBeschreibungTest extends TestCase
{
    private function job(string $beschreibung, string $format): Job
    {
        return Job::fromApi([
            'id' => 'j1', 'title' => 'T', 'description' => $beschreibung, 'description_format' => $format,
        ]);
    }

    private function quelle(string $pfad): string
    {
        return (string) file_get_contents(__DIR__.'/../src/'.$pfad);
    }

    // AK8
    public function test_description_html_rendert_markdown(): void
    {
        $job = $this->job("## Aufgaben\n\nWir suchen **Verstärkung**.", 'markdown');

        $this->assertSame(MarkdownRenderer::render($job->description), $job->descriptionHtml());
        $this->assertStringContainsString('<strong>Verstärkung</strong>', $job->descriptionHtml());
    }

    // AK8
    public function test_description_html_gibt_html_unveraendert_zurueck(): void
    {
        $html = '<p>Wir bieten <em>flexible</em> Arbeitszeiten.</p><ul><li>Homeoffice</li></ul>';

        $this->assertSame($html, $this->job($html, 'html')->descriptionHtml());
    }

    // AK8
    public function test_description_html_ohne_format_angabe_folgt_dem_bisherigen_standard(): void
    {
        $job = Job::fromApi(['id' => 'j1', 'title' => 'T', 'description' => 'Text **fett**']);

        $erwartet = $job->isMarkdown() ? MarkdownRenderer::render('Text **fett**') : 'Text **fett**';
        $this->assertSame($erwartet, $job->descriptionHtml());
    }

    // AK8
    public function test_markdown_html_unterscheidung_steht_nur_noch_in_job(): void
    {
        foreach (['Resources/views/components/jobs.blade.php', 'Resources/views/components/jobs-schema.blade.php', 'Support/JobPostingSchema.php'] as $datei) {
            $inhalt = $this->quelle($datei);

            $this->assertStringNotContainsString('isMarkdown', $inhalt, "{$datei} unterscheidet nicht selbst.");
            $this->assertStringNotContainsString('MarkdownRenderer::render(', $inhalt, "{$datei} rendert nicht selbst.");
        }

        foreach (['Resources/views/components/jobs.blade.php', 'Support/JobPostingSchema.php'] as $datei) {
            $this->assertStringContainsString('descriptionHtml()', $this->quelle($datei), "{$datei} nutzt Job::descriptionHtml().");
        }

        $this->assertStringContainsString('MarkdownRenderer::render(', $this->quelle('Data/Job.php'));
    }

    // AK9
    public function test_kopfkommentar_der_komponente_empfiehlt_nicht_mehr_consentlaw_jobs(): void
    {
        $quelle = $this->quelle('Resources/views/components/jobs.blade.php');

        $this->assertSame(1, preg_match('/\A\{\{--(.*?)--\}\}/s', $quelle, $m), 'Die Komponente beginnt mit einem Kopfkommentar.');
        $this->assertStringNotContainsString('ConsentLaw::jobs()', $m[1], 'wirft ohne Token (AK7).');
        $this->assertMatchesRegularExpression('/README|Eigenes Markup|jobs-schema|KomponentenInhalt/', $m[1], 'Verweis auf README bzw. den Weg aus AK7.');
    }
}
