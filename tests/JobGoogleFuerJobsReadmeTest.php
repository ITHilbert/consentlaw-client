<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Tests;

use Illuminate\Support\Facades\Blade;

/**
 * AK7 aus .project/features/jobs-google-for-jobs/spec.md – README „Eigenes Markup“.
 *
 * Das Beispiel der README wird nicht nur nach Stichworten durchsucht, sondern
 * ausgelesen und gerendert: Ein Beispiel, das beim Abschreiben leere oder falsche
 * Ausgabe liefert (Review 1: HTML-Beschreibungen durch den Markdown-Renderer), fällt so auf.
 */
final class JobGoogleFuerJobsReadmeTest extends AppTestCase
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

    /** Der erste Blade-Codeblock des Abschnitts. */
    private function beispiel(): string
    {
        if (preg_match('/```blade\n(.*?)\n```/s', $this->abschnitt(), $m) !== 1) {
            $this->fail('Der Abschnitt „Eigenes Markup“ enthält keinen ```blade-Codeblock.');
        }

        return $m[1];
    }

    // AK7
    public function test_readme_hat_einen_abschnitt_zu_eigenem_markup_fuer_jobs(): void
    {
        $this->assertStringContainsStringIgnoringCase('job', $this->abschnitt());
    }

    // AK7
    public function test_beispiel_zeigt_daten_weg_ohne_token_button_und_json_ld(): void
    {
        $beispiel = $this->beispiel();

        $this->assertStringContainsString('KomponentenInhalt', $beispiel, 'Der Weg, der ohne Token nicht wirft.');
        $this->assertStringContainsString('consentlaw::components.partials.neu-laden', $beispiel);
        $this->assertStringContainsString('<x-consentlaw::jobs-schema', $beispiel);
    }

    // AK7
    public function test_beispiel_hat_keine_eigene_fallunterscheidung(): void
    {
        $beispiel = $this->beispiel();

        $this->assertStringNotContainsString('isMarkdown', $beispiel);
        $this->assertStringNotContainsString('MarkdownRenderer', $beispiel);
        $this->assertStringContainsString('descriptionHtml', $beispiel, 'Die Beschreibung kommt über Job::descriptionHtml() (AK8).');
    }

    // AK7
    public function test_beispiel_rendert_markdown_und_html_beschreibung_vollstaendig(): void
    {
        $this->api(['*' => ['jobs' => [
            $this->jobsPayload([
                'id' => 'md', 'title' => 'Markdown-Stelle',
                'description' => "## Aufgaben\n\nWir suchen **Verstärkung** im Team.",
                'description_format' => 'markdown',
            ])['jobs'][0],
            $this->jobsPayload([
                'id' => 'html', 'title' => 'HTML-Stelle',
                'description' => '<p>Wir bieten <em>flexible</em> Arbeitszeiten.</p>',
                'description_format' => 'html',
            ])['jobs'][0],
        ]]]);

        $html = Blade::render($this->beispiel());

        $this->assertStringContainsString('Markdown-Stelle', $html);
        $this->assertStringContainsString('HTML-Stelle', $html);
        $this->assertStringContainsString('<strong>Verstärkung</strong>', $html, 'Markdown-Beschreibung ist gerendert und vollständig.');
        $this->assertStringContainsString('Wir suchen', $html);
        $this->assertStringContainsString('<p>Wir bieten <em>flexible</em> Arbeitszeiten.</p>', $html, 'HTML-Beschreibung erscheint vollständig und unverändert.');
        $this->assertSame(2, substr_count($html, 'application/ld+json'), 'Je Stelle ein JSON-LD.');
    }

    // AK7
    public function test_beispiel_rendert_ohne_token_ohne_fehler_und_ohne_api_aufruf(): void
    {
        $this->konfig('token', '');
        $this->api(['*' => $this->jobsPayload()]);

        $html = Blade::render($this->beispiel());

        $this->assertStringNotContainsString('PHP-Entwickler', $html);
        $this->assertSame(0, $this->anzahlAufrufe());
    }
}
