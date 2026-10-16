<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Tests;

/**
 * AK6 – Standard-URL ist https://www.consentlaw.eu (consentlaw.eu antwortet mit 301).
 *
 * AppTestCase entfernt alle CONSENTLAW_*-Variablen aus der Umgebung, die
 * Standardwerte stammen daher aus src/Config/consentlaw.php.
 */
final class StandardUrlTest extends AppTestCase
{
    private const STANDARD_URL = 'https://www.consentlaw.eu';

    /** @return array<string, mixed> */
    private function paketConfig(): array
    {
        return require __DIR__.'/../src/Config/consentlaw.php';
    }

    // AK6
    public function test_standard_url_in_der_config_ist_die_www_adresse(): void
    {
        $this->assertSame(self::STANDARD_URL, $this->paketConfig()['base_url']);
    }

    // AK6
    public function test_ohne_consentlaw_url_geht_die_anfrage_an_die_www_adresse(): void
    {
        $this->api(['*' => $this->dokumentPayload('# Impressum')]);

        $this->client()->impressum()->get();

        $this->assertCount(1, $this->aufrufe);
        $this->assertStringStartsWith(
            self::STANDARD_URL.'/api/impressum/'.self::TOKEN,
            $this->aufrufe[0],
            'Ohne CONSENTLAW_URL muss direkt an www.consentlaw.eu gefragt werden (kein 301-Umweg).',
        );
    }

    // AK6
    public function test_jobs_anfrage_geht_ebenfalls_an_die_www_adresse(): void
    {
        $this->api(['*' => $this->jobsPayload()]);

        $this->client()->jobs()->all();

        $this->assertStringStartsWith(self::STANDARD_URL.'/api/jobs/'.self::TOKEN, $this->aufrufe[0]);
    }

    // AK6 (Abgrenzung): Eine gesetzte CONSENTLAW_URL hat weiterhin Vorrang.
    public function test_gesetzte_consentlaw_url_hat_vorrang_in_der_config(): void
    {
        $this->setzeUmgebung('CONSENTLAW_URL', 'https://consentlaw.example.test');

        $this->assertSame('https://consentlaw.example.test', $this->paketConfig()['base_url']);
    }
}
