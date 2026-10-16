<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Tests;

use ITHilbert\ConsentLaw\Data\Document;
use ITHilbert\ConsentLaw\Data\Job;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * AK10 (Allowlist) – Nur ein Format exakt `html` wird ungefiltert ausgegeben.
 * Jeder andere Wert (leer, `HTML`, `text`, unbekannt, falscher Datentyp) gilt als
 * Markdown und läuft durch den MarkdownRenderer – für Dokumente (`format`) und
 * Job-Beschreibungen (`description_format`).
 */
final class FormatAllowlistTest extends AppTestCase
{
    /** Der Markdown-Absatz stellt sicher, dass nach dem Filtern Inhalt übrig bleibt (reines rohes HTML fällt komplett weg). */
    private const GEFAEHRLICH = "Sichtbarer Text\n\n".'<p>x</p><script>alert(1)</script><img src=x onerror=alert(2)>';

    /** @return array<string, array{0: mixed}> */
    public static function keinExaktesHtml(): array
    {
        return [
            'leer' => [''],
            'HTML grossgeschrieben' => ['HTML'],
            'Html gemischt' => ['Html'],
            'text' => ['text'],
            'unbekannt' => ['unbekannt'],
            'Zahl' => [123],
            'null' => [null],
        ];
    }

    private function pruefeSicher(string $html): void
    {
        $this->assertStringNotContainsStringIgnoringCase('<script', $html);
        $this->assertStringNotContainsStringIgnoringCase('onerror', $html);
    }

    /** @return array{0: string, 1: list<array<string, mixed>>} HTML ohne JSON-LD und die dekodierten JSON-LD-Blöcke. */
    private function trenneJsonLd(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $treffer);

        $daten = array_map(static fn (string $json): array => json_decode(trim($json), true, 512, JSON_THROW_ON_ERROR), $treffer[1]);

        return [preg_replace('#<script type="application/ld\+json">.*?</script>#s', '', $html) ?? $html, $daten];
    }

    // AK10
    #[DataProvider('keinExaktesHtml')]
    public function test_dokument_mit_anderem_format_als_html_gilt_als_markdown(mixed $format): void
    {
        $dokument = Document::fromApi('agb', ['content' => self::GEFAEHRLICH, 'format' => $format]);

        $this->assertTrue($dokument->isMarkdown());
    }

    // AK10
    #[DataProvider('keinExaktesHtml')]
    public function test_dokument_komponente_filtert_bei_anderem_format_als_html(mixed $format): void
    {
        $this->api(['*' => ['content' => self::GEFAEHRLICH, 'format' => $format, 'generated_at' => '2026-10-01T10:00:00+00:00']]);

        $html = $this->rendere('<x-consentlaw::agb />');

        $this->assertStringContainsString('Sichtbarer Text', $html);
        $this->pruefeSicher($html);
    }

    // AK10
    #[DataProvider('keinExaktesHtml')]
    public function test_job_mit_anderem_description_format_als_html_gilt_als_markdown(mixed $format): void
    {
        $payload = $this->jobsPayload(['description' => self::GEFAEHRLICH, 'description_format' => $format])['jobs'][0];

        $this->assertTrue(Job::fromApi($payload)->isMarkdown());
    }

    // AK10
    #[DataProvider('keinExaktesHtml')]
    public function test_jobs_komponente_filtert_html_und_json_ld_bei_anderem_description_format_als_html(mixed $format): void
    {
        $this->api(['*' => $this->jobsPayload(['description' => self::GEFAEHRLICH, 'description_format' => $format])]);

        $html = $this->rendere('<x-consentlaw::jobs />');
        [$ohneJsonLd, $jsonLd] = $this->trenneJsonLd($html);

        $this->assertStringContainsString('Sichtbarer Text', $ohneJsonLd);
        $this->pruefeSicher($ohneJsonLd);
        $this->assertCount(1, $jsonLd, 'Pro Job genau ein JSON-LD-Block.');
        // Die JSON-Kodierung maskiert "<" – deshalb wird der DEKODIERTE Text geprüft.
        $beschreibung = (string) ($jsonLd[0]['description'] ?? '');
        $this->assertStringContainsString('Sichtbarer Text', $beschreibung);
        $this->pruefeSicher($beschreibung);
    }

    // AK10 – Gegenprobe: exakt `html` bleibt html (Dokument)
    public function test_dokument_mit_exakt_html_bleibt_html_und_ungefiltert(): void
    {
        $this->api(['*' => $this->dokumentPayload('<p>Absatz</p><span class="x">a</span>', 'html')]);

        $dokument = $this->client()->agb()->get();
        $html = $this->rendere('<x-consentlaw::agb />');

        $this->assertNotNull($dokument);
        $this->assertFalse($dokument->isMarkdown());
        $this->assertStringContainsString('<p>Absatz</p><span class="x">a</span>', $html);
    }

    // AK10 – Gegenprobe: exakt `html` bleibt html (Job)
    public function test_job_mit_exakt_html_bleibt_html_und_ungefiltert(): void
    {
        $this->api(['*' => $this->jobsPayload(['description' => '<p>Absatz</p><span class="x">a</span>', 'description_format' => 'html'])]);

        $job = Job::fromApi($this->jobsPayload(['description_format' => 'html'])['jobs'][0]);
        $html = $this->rendere('<x-consentlaw::jobs />');
        [$ohneJsonLd] = $this->trenneJsonLd($html);

        $this->assertFalse($job->isMarkdown());
        $this->assertStringContainsString('<p>Absatz</p><span class="x">a</span>', $ohneJsonLd);
    }
}
