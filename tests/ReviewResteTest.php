<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Tests;

use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Blade;
use ITHilbert\ConsentLaw\Data\Document;
use ITHilbert\ConsentLaw\Support\MarkdownRenderer;
use Monolog\Level;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Tests zu .project/features/review-reste/spec.md (AK1–AK3, Randfall 1 und 2).
 */
final class ReviewResteTest extends AppTestCase
{
    private const MARKDOWN = "## Titel\n\nText mit **Betonung**.";

    private const HTML = '<h2>Titel</h2><p>Text mit <em>HTML</em> &amp; mehr.</p>';

    private const LEERTEXT = 'Zurzeit sind keine Stellen ausgeschrieben.';

    private function doc(string $inhalt, ?string $format): Document
    {
        return Document::fromApi('impressum', ['content' => $inhalt] + ($format === null ? [] : ['format' => $format]));
    }

    private function quelle(string $pfad): string
    {
        return (string) file_get_contents(__DIR__.'/../src/'.$pfad);
    }

    /** Baut die App mit aktivierten Paket-Routen. */
    private function mitRouten(): void
    {
        // mergeConfigFrom mischt flach: Teil-Arrays ersetzen die Standardwerte ganz, also vollständig angeben.
        // Cache und Störungssperre aus: Jeder Abruf von KomponentenInhalt wird so zu einer sichtbaren API-Anfrage.
        $this->appNeuBauen('testing', [
            'cache' => ['enabled' => false, 'store' => null, 'ttl' => 0, 'prefix' => 'consentlaw'],
            'fallback' => ['enabled' => true, 'disk' => 'local', 'path' => 'consentlaw', 'warn_after' => 604800, 'retry_after' => 0],
            'routes' => [
                'enabled' => true,
                'middleware' => ['web'],
                'prefix' => '',
                'layout' => null,
                'paths' => [
                    'impressum' => 'impressum',
                    'datenschutz' => 'datenschutz',
                    'jobs' => 'jobs',
                ],
            ],
        ]);
    }

    /** @return array{0: int, 1: string} */
    private function aufruf(string $pfad): array
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

    private function jobsAbrufe(): int
    {
        return count(array_filter($this->aufrufe, static fn (string $url): bool => str_contains($url, 'api/jobs')));
    }

    private function warnungen(): int
    {
        return count(array_filter(
            $this->logHandler->getRecords(),
            static fn ($eintrag): bool => $eintrag->level->value >= Level::Warning->value,
        ));
    }

    // ── AK1 ──────────────────────────────────────────────────────────────────

    // AK1
    public function test_content_html_rendert_markdown(): void
    {
        $dokument = $this->doc(self::MARKDOWN, 'markdown');

        $this->assertSame(MarkdownRenderer::render(self::MARKDOWN), $dokument->contentHtml());
        $this->assertStringContainsString('<strong>Betonung</strong>', $dokument->contentHtml());
    }

    // AK1
    public function test_content_html_gibt_html_unveraendert_zurueck(): void
    {
        $this->assertSame(self::HTML, $this->doc(self::HTML, 'html')->contentHtml());
    }

    // AK1
    public function test_beide_document_views_unterscheiden_nicht_mehr_selbst(): void
    {
        foreach (['Resources/views/components/document.blade.php', 'Resources/views/pages/document.blade.php'] as $datei) {
            $inhalt = $this->quelle($datei);

            $this->assertStringContainsString('contentHtml()', $inhalt, "{$datei} gibt den Inhalt über contentHtml() aus.");
            $this->assertStringNotContainsString('isMarkdown', $inhalt, "{$datei} unterscheidet nicht selbst.");
            $this->assertStringNotContainsString('MarkdownRenderer', $inhalt, "{$datei} rendert nicht selbst.");
        }
    }

    // AK1
    public function test_komponente_gibt_markdown_dokument_gerendert_aus(): void
    {
        $this->api(['*' => $this->dokumentPayload(self::MARKDOWN, 'markdown')]);

        $html = Blade::render('<x-consentlaw::document type="impressum" />');

        $this->assertStringContainsString(MarkdownRenderer::render(self::MARKDOWN), $html);
        $this->assertStringContainsString('<strong>Betonung</strong>', $html);
    }

    // AK1
    public function test_komponente_gibt_html_dokument_unveraendert_aus(): void
    {
        $this->api(['*' => $this->dokumentPayload(self::HTML, 'html')]);

        $html = Blade::render('<x-consentlaw::document type="impressum" />');

        $this->assertStringContainsString(self::HTML, $html);
    }

    // AK1
    public function test_seite_gibt_markdown_dokument_gerendert_aus(): void
    {
        $this->mitRouten();
        $this->api(['*' => $this->dokumentPayload(self::MARKDOWN, 'markdown')]);

        [$status, $html] = $this->aufruf('impressum');

        $this->assertSame(200, $status);
        $this->assertStringContainsString('<article class="consentlaw-page">', $html);
        $this->assertStringContainsString(MarkdownRenderer::render(self::MARKDOWN), $html);
        $this->assertStringContainsString('<strong>Betonung</strong>', $html);
    }

    // AK1
    public function test_seite_gibt_html_dokument_unveraendert_aus(): void
    {
        $this->mitRouten();
        $this->api(['*' => $this->dokumentPayload(self::HTML, 'html')]);

        [$status, $html] = $this->aufruf('impressum');

        $this->assertSame(200, $status);
        $this->assertStringContainsString(self::HTML, $html);
    }

    // ── Randfall 1 ───────────────────────────────────────────────────────────

    // Randfall 1
    public function test_content_html_ist_bei_leerem_inhalt_ein_leerer_string(): void
    {
        foreach (['markdown', 'html'] as $format) {
            $dokument = $this->doc('', $format);

            $this->assertTrue($dokument->isEmpty());
            $this->assertSame('', $dokument->contentHtml(), "Format {$format}, Inhalt ''.");
        }

        $nurLeerraum = $this->doc("  \n\t ", 'html');
        $this->assertTrue($nurLeerraum->isEmpty());
        $this->assertSame('', trim($nurLeerraum->contentHtml()), 'Nur Leerraum gilt als leer.');
    }

    // Randfall 1
    public function test_komponente_bleibt_bei_leerem_dokument_leer_und_zeigt_den_slot(): void
    {
        $this->api(['*' => $this->dokumentPayload('', 'markdown')]);

        $html = Blade::render('<x-consentlaw::document type="impressum">Kein Text da</x-consentlaw::document>');

        $this->assertStringContainsString('Kein Text da', $html, 'Slot erscheint.');
        $this->assertStringNotContainsString('consentlaw-document', $html);
    }

    // Randfall 1
    public function test_seite_antwortet_bei_leerem_dokument_mit_404(): void
    {
        $this->mitRouten();
        $this->api(['*' => $this->dokumentPayload('', 'markdown')]);

        $this->assertSame(404, $this->aufruf('impressum')[0]);
        $this->assertGreaterThan(0, $this->anzahlAufrufe(), 'Gegenprobe: Der Inhalt wurde abgerufen.');
    }

    // ── Randfall 2 ───────────────────────────────────────────────────────────

    // Randfall 2
    public function test_content_html_folgt_is_markdown_bei_unbekanntem_oder_fehlendem_format(): void
    {
        $inhalt = 'Text **fett**';

        foreach (['docx', 'MARKDOWN-x', '', null] as $format) {
            $dokument = $this->doc($inhalt, $format);
            $erwartet = $dokument->isMarkdown() ? MarkdownRenderer::render($inhalt) : $inhalt;

            $this->assertSame($erwartet, $dokument->contentHtml(), 'Format '.var_export($format, true));
        }
    }

    // Randfall 2
    public function test_content_html_folgt_is_markdown_bei_format_unter_content_format(): void
    {
        $dokument = Document::fromApi('impressum', ['content' => $inhalt = 'Text **fett**', 'content_format' => 'html']);

        $this->assertFalse($dokument->isMarkdown());
        $this->assertSame($inhalt, $dokument->contentHtml());
    }

    // ── AK2 ──────────────────────────────────────────────────────────────────

    // AK2
    public function test_jobs_seite_ruft_die_stellen_genau_einmal_ab_und_zeigt_sie(): void
    {
        $this->mitRouten();
        $this->api(['*' => $this->jobsPayload(['title' => 'Eindeutige Stelle'])]);

        [$status, $html] = $this->aufruf('jobs');

        $this->assertSame(200, $status);
        $this->assertStringContainsString('Eindeutige Stelle', $html, 'Gegenprobe: Die Stelle wurde abgerufen und angezeigt.');
        $this->assertSame(1, $this->jobsAbrufe(), 'Genau eine Anfrage an die Jobs-API.');
    }

    // AK2
    public function test_jobs_seite_ruft_auch_ohne_stellen_nur_einmal_ab(): void
    {
        $this->mitRouten();
        $this->api(['*' => ['jobs' => []]]);

        [$status, $html] = $this->aufruf('jobs');

        $this->assertSame(200, $status);
        $this->assertSame(1, $this->jobsAbrufe());
        $this->assertSame(1, substr_count($html, self::LEERTEXT), 'Leertext erscheint einmal.');
    }

    // AK2
    public function test_jobs_seite_gibt_stellen_und_json_ld_unveraendert_aus(): void
    {
        $this->mitRouten();
        $this->api(['*' => $this->jobsPayload(['title' => 'PHP-Entwickler'])]);

        $html = $this->aufruf('jobs')[1];

        $this->assertStringContainsString('<h1>Stellenangebote</h1>', $html);
        $this->assertStringContainsString('consentlaw-job', $html);
        $this->assertSame(1, substr_count($html, 'application/ld+json'), 'Je Stelle ein JSON-LD.');
        $this->assertStringNotContainsString(self::LEERTEXT, $html);
    }

    // AK2
    public function test_jobs_seite_warnt_bei_stoerung_genau_einmal(): void
    {
        $this->mitRouten();
        $this->api(['*' => self::STOERUNG]);

        [$status, $html] = $this->aufruf('jobs');

        $this->assertSame(200, $status);
        $this->assertGreaterThan(0, $this->jobsAbrufe(), 'Gegenprobe: Es gab einen Abruf.');
        $this->assertSame(1, $this->warnungen(), 'Die Warnung steht genau einmal im Log: '.$this->logText());
        $this->assertSame(1, substr_count($html, self::LEERTEXT), 'Leertext bleibt gleich.');
    }

    // AK2
    public function test_jobs_seite_warnt_ohne_token_genau_einmal(): void
    {
        $this->mitRouten();
        $this->konfig('token', '');
        $this->api(['*' => $this->jobsPayload()]);

        [$status, $html] = $this->aufruf('jobs');

        $this->assertSame(200, $status);
        $this->assertSame(1, substr_count($this->logText(), 'CONSENTLAW_TOKEN fehlt'), 'Warnung genau einmal: '.$this->logText());
        $this->assertSame(0, $this->anzahlAufrufe(), 'Ohne Token kein API-Aufruf.');
        $this->assertSame(1, substr_count($html, self::LEERTEXT));
    }

    // AK2
    public function test_jeder_seitenaufruf_ruft_ohne_cache_genau_einmal_ab(): void
    {
        $this->mitRouten();
        $this->api(['*' => $this->jobsPayload()]);

        $this->aufruf('jobs');
        $erste = $this->jobsAbrufe();
        $this->assertSame(1, $erste, 'Gegenprobe: Der erste Aufruf holt die Stellen.');

        $this->aufruf('jobs');
        $this->assertSame(2, $this->jobsAbrufe(), 'Ohne Cache holt jeder Seitenaufruf genau einmal.');
    }

    // ── AK3 ──────────────────────────────────────────────────────────────────

    private function readme(): string
    {
        return (string) file_get_contents(__DIR__.'/../README.md');
    }

    /** Absatz (durch Leerzeilen getrennt), der die statische Form nennt. */
    private function absatzMitStatischerForm(): string
    {
        foreach (preg_split('/\n\s*\n/', $this->readme()) ?: [] as $absatz) {
            if (preg_match('/(?<![:\w-])with-schema="false"/', $absatz) === 1) {
                return $absatz;
            }
        }

        $this->fail('Die README nennt die statische Form with-schema="false" nicht.');
    }

    // AK3
    public function test_readme_beschreibt_with_schema_mit_gebundener_form(): void
    {
        $this->assertStringContainsString('<x-consentlaw::jobs', $this->readme());
        $this->assertStringContainsString(':with-schema="false"', $this->readme());
    }

    // AK3
    public function test_readme_weist_darauf_hin_dass_die_statische_form_das_json_ld_nicht_unterdrueckt(): void
    {
        $absatz = $this->absatzMitStatischerForm();

        $this->assertMatchesRegularExpression('/nicht/iu', $absatz, 'Der Absatz zur statischen Form verneint die Wirkung.');
        $this->assertMatchesRegularExpression('/JSON-LD/u', $absatz);
    }

    // AK3
    public function test_readme_aussage_stimmt_gebunden_unterdrueckt_statisch_nicht(): void
    {
        $this->api(['*' => $this->jobsPayload()]);

        $standard = Blade::render('<x-consentlaw::jobs />');
        $this->assertStringContainsString('application/ld+json', $standard, 'Gegenprobe: Standard gibt JSON-LD aus.');

        $this->assertStringNotContainsString('application/ld+json', Blade::render('<x-consentlaw::jobs :with-schema="false" />'));
        $this->assertStringContainsString('application/ld+json', Blade::render('<x-consentlaw::jobs with-schema="false" />'));
    }
}
