<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Tests;

use Illuminate\Http\Client\Request;
use ITHilbert\ConsentLaw\Console\ConsentLawLoadCommand;
use ITHilbert\ConsentLaw\Contracts\Transport;
use ITHilbert\ConsentLaw\Exceptions\ConsentLawException;
use ITHilbert\ConsentLaw\Facades\ConsentLaw;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Throwable;

/**
 * AK2 (Feature zeilenumbruch-token) – Kein Token in Meldungen.
 *
 * Bei Störung, 404 oder sonstigem Fehler beim Abruf enthält weder eine Exception-Meldung
 * noch ein Log-Eintrag (Meldung + Kontext) noch die Ausgabe von `consentlaw:load` den Token
 * im Klartext. URLs erscheinen mit maskiertem Token (höchstens die ersten 4 Zeichen,
 * Token bis 4 Zeichen vollständig maskiert). Das genaue Maskenzeichen ist bewusst nicht
 * festgelegt – geprüft wird nur, was vom Token sichtbar bleibt.
 */
final class TokenSchutzTest extends AppTestCase
{
    private const GEHEIM = 'geheim-token-12345';

    // ── Anbieter ─────────────────────────────────────────────────────────────

    /** @return array<string, array{mixed}> */
    public static function stoerungen(): array
    {
        return [
            'Verbindungsfehler/Timeout' => [self::STOERUNG],
            'Status 500' => [500],
            'Status 503' => [503],
            'Status 429' => [429],
            'Status 401' => [401],
            'Status 404' => [404],
            'ungültiges JSON (200)' => ['kaputt{ kein json'],
            'unerwarteter Transportfehler' => [
                static fn (Request $request): never => throw new RuntimeException('Boom bei '.$request->url()),
            ],
        ];
    }

    /** @return array<string, array{string, mixed}> */
    public static function kurzeTokensMitStoerungen(): array
    {
        $faelle = [];

        foreach (['abc', 'abcd'] as $token) {
            foreach (self::stoerungen() as $name => [$antwort]) {
                $faelle["Token '{$token}' – {$name}"] = [$token, $antwort];
            }
        }

        return $faelle;
    }

    // ── Hilfen ───────────────────────────────────────────────────────────────

    private function setzeToken(string $token): void
    {
        $this->konfig('token', $token);
    }

    /** Alles, was vom Token nicht im Text stehen darf. @return list<string> */
    private function verboten(string $token): array
    {
        $verboten = [$token];

        if (strlen($token) >= 10) {
            // Mehr als die ersten 4 Zeichen (Rest und Ende) dürfen nie sichtbar sein.
            $verboten[] = substr($token, 4);
            $verboten[] = substr($token, -5);
        }

        return $verboten;
    }

    private function pruefeOhneToken(string $text, string $token, string $herkunft): void
    {
        foreach ($this->verboten($token) as $teil) {
            $this->assertStringNotContainsString($teil, $text, "{$herkunft} enthält den Token ('{$teil}'):\n{$text}");
        }
    }

    /** Meldungen der Exception samt aller Vorgänger. */
    private function meldungen(Throwable $fehler): string
    {
        $meldungen = [];

        for ($e = $fehler; $e !== null; $e = $e->getPrevious()) {
            $meldungen[] = get_class($e).': '.$e->getMessage();
        }

        return implode("\n", $meldungen);
    }

    /** Ruft den Transport direkt auf und liefert die Meldungen der geworfenen Exception. */
    private function transportMeldung(string $endpoint): string
    {
        try {
            $this->app->make(Transport::class)->get($endpoint, ['locale' => 'de']);
        } catch (ConsentLawException $e) {
            return $this->meldungen($e);
        }

        $this->fail('Der Transport hätte eine ConsentLawException werfen müssen.');
    }

    /** Länge des gemeinsamen Anfangs von $a und $b. */
    private function gemeinsamerAnfang(string $a, string $b): int
    {
        $laenge = 0;

        while ($laenge < strlen($a) && $laenge < strlen($b) && $a[$laenge] === $b[$laenge]) {
            $laenge++;
        }

        return $laenge;
    }

    /** Prüft, dass die URL in der Meldung vorkommt und der Token-Pfadteil maskiert ist. */
    private function pruefeMaskierteUrl(string $meldung, string $pfad, string $token): void
    {
        $this->assertStringContainsString($pfad.'/', $meldung, "Die URL (ohne Token) soll zur Diagnose in der Meldung stehen:\n{$meldung}");

        preg_match('#'.preg_quote($pfad, '#').'/([^\s)?/]*)#u', $meldung, $treffer);
        $sichtbar = $treffer[1] ?? '';
        $erlaubt = strlen($token) <= 4 ? 0 : 4;

        $this->assertLessThanOrEqual(
            $erlaubt,
            $this->gemeinsamerAnfang($sichtbar, $token),
            "Vom Token dürfen höchstens {$erlaubt} Zeichen sichtbar sein ('{$sichtbar}'):\n{$meldung}",
        );
    }

    // ── Exception-Meldungen (HttpTransport direkt) ───────────────────────────

    // AK2
    #[DataProvider('stoerungen')]
    public function test_transport_meldung_fuer_dokument_enthaelt_keinen_token(mixed $antwort): void
    {
        $this->setzeToken(self::GEHEIM);
        $this->api(['*' => $antwort]);

        $meldung = $this->transportMeldung('api/impressum/'.self::GEHEIM);

        $this->pruefeOhneToken($meldung, self::GEHEIM, 'Exception-Meldung');
        $this->pruefeMaskierteUrl($meldung, 'api/impressum', self::GEHEIM);
    }

    // AK2
    #[DataProvider('stoerungen')]
    public function test_transport_meldung_fuer_jobs_enthaelt_keinen_token(mixed $antwort): void
    {
        $this->setzeToken(self::GEHEIM);
        $this->api(['*' => $antwort]);

        $meldung = $this->transportMeldung('api/jobs/'.self::GEHEIM);

        $this->pruefeOhneToken($meldung, self::GEHEIM, 'Exception-Meldung');
        $this->pruefeMaskierteUrl($meldung, 'api/jobs', self::GEHEIM);
    }

    // AK2 – auch mit Wiederholungsversuchen
    #[DataProvider('stoerungen')]
    public function test_transport_meldung_mit_wiederholungen_enthaelt_keinen_token(mixed $antwort): void
    {
        $this->setzeToken(self::GEHEIM);
        $this->konfig('http.retries', 2);
        $this->api(['*' => $antwort]);

        $meldung = $this->transportMeldung('api/impressum/'.self::GEHEIM);

        $this->pruefeOhneToken($meldung, self::GEHEIM, 'Exception-Meldung');
    }

    // AK2 – Randfall: Token bis 4 Zeichen vollständig maskiert
    #[DataProvider('kurzeTokensMitStoerungen')]
    public function test_transport_meldung_maskiert_kurze_tokens_vollstaendig(string $token, mixed $antwort): void
    {
        $this->setzeToken($token);
        $this->api(['*' => $antwort]);

        $meldung = $this->transportMeldung('api/impressum/'.$token);

        $this->pruefeOhneToken($meldung, $token, 'Exception-Meldung');
        $this->pruefeMaskierteUrl($meldung, 'api/impressum', $token);
    }

    // ── Log (über den vollständigen Ablauf) ──────────────────────────────────

    // AK2
    #[DataProvider('stoerungen')]
    public function test_log_fuer_dokument_enthaelt_keinen_token(mixed $antwort): void
    {
        $this->setzeToken(self::GEHEIM);
        $this->api(['*' => $antwort]);

        ConsentLaw::document('impressum')->abrufen();

        $this->assertGreaterThan(0, $this->anzahlLogEintraege(), 'Erwartet: Der Fehler wird geloggt (sonst prüft der Test nichts).');
        $this->pruefeOhneToken($this->logText(), self::GEHEIM, 'Log');
    }

    // AK2
    #[DataProvider('stoerungen')]
    public function test_log_fuer_jobs_enthaelt_keinen_token(mixed $antwort): void
    {
        $this->setzeToken(self::GEHEIM);
        $this->api(['*' => $antwort]);

        ConsentLaw::jobs()->abrufen();

        $this->assertGreaterThan(0, $this->anzahlLogEintraege(), 'Erwartet: Der Fehler wird geloggt (sonst prüft der Test nichts).');
        $this->pruefeOhneToken($this->logText(), self::GEHEIM, 'Log');
    }

    // AK2 – Fallback vorhanden und veraltet (Warn-Pfad mit Fehler im Kontext)
    #[DataProvider('stoerungen')]
    public function test_log_bei_veraltetem_fallback_enthaelt_keinen_token(mixed $antwort): void
    {
        $this->setzeToken(self::GEHEIM);
        $this->api([
            '/api/impressum/' => $this->dokumentPayload('# Impressum'),
            '/api/jobs/' => $this->jobsPayload(),
        ]);
        ConsentLaw::document('impressum')->abrufen();
        ConsentLaw::jobs()->abrufen();

        $this->reise(86400 * 8);
        $this->api(['*' => $antwort]);

        ConsentLaw::document('impressum')->abrufen();
        ConsentLaw::jobs()->abrufen();

        $this->pruefeOhneToken($this->logText(), self::GEHEIM, 'Log');
    }

    // AK2 – Fallback vorhanden und jung (Info-Pfad)
    #[DataProvider('stoerungen')]
    public function test_log_bei_aktuellem_fallback_enthaelt_keinen_token(mixed $antwort): void
    {
        $this->setzeToken(self::GEHEIM);
        $this->api(['*' => $this->dokumentPayload('# Impressum')]);
        ConsentLaw::document('impressum')->abrufen();

        $this->cacheAblaufenLassen();
        $this->api(['*' => $antwort]);

        ConsentLaw::document('impressum')->abrufen();

        $this->pruefeOhneToken($this->logText(), self::GEHEIM, 'Log');
    }

    // AK2 – Fallback deaktiviert (eigener Log-Pfad)
    #[DataProvider('stoerungen')]
    public function test_log_ohne_fallback_enthaelt_keinen_token(mixed $antwort): void
    {
        $this->setzeToken(self::GEHEIM);
        $this->konfig('fallback.enabled', false);
        $this->api(['*' => $antwort]);

        ConsentLaw::document('impressum')->abrufen();
        ConsentLaw::jobs()->abrufen();

        $this->assertGreaterThan(0, $this->anzahlLogEintraege());
        $this->pruefeOhneToken($this->logText(), self::GEHEIM, 'Log');
    }

    // AK2 – Randfall: kurze Tokens stehen nie im Klartext im Log
    #[DataProvider('kurzeTokensMitStoerungen')]
    public function test_log_enthaelt_kurze_tokens_nicht_im_klartext(string $token, mixed $antwort): void
    {
        $this->setzeToken($token);
        $this->api(['*' => $antwort]);

        ConsentLaw::document('impressum')->abrufen();
        ConsentLaw::jobs()->abrufen();

        $this->assertGreaterThan(0, $this->anzahlLogEintraege());
        $this->pruefeOhneToken($this->logText(), $token, 'Log');
    }

    // ── Exceptions, die nach oben durchschlagen könnten ──────────────────────

    // AK2 – der Facade-Aufruf wirft nichts, das den Token enthält
    #[DataProvider('stoerungen')]
    public function test_abrufen_wirft_keine_exception_mit_token(mixed $antwort): void
    {
        $this->setzeToken(self::GEHEIM);
        $this->api(['*' => $antwort]);

        try {
            ConsentLaw::document('impressum')->abrufen();
            ConsentLaw::jobs()->abrufen();
        } catch (Throwable $e) {
            $this->pruefeOhneToken($this->meldungen($e), self::GEHEIM, 'Exception');
        }

        $this->addToAssertionCount(1);
    }

    // ── consentlaw:load ──────────────────────────────────────────────────────

    // AK2
    #[DataProvider('stoerungen')]
    public function test_reload_ausgabe_und_log_enthalten_keinen_token(mixed $antwort): void
    {
        $this->setzeToken(self::GEHEIM);
        $this->api(['*' => $antwort]);

        [, $ausgabe] = $this->fuehreAus(ConsentLawLoadCommand::class);

        $this->pruefeOhneToken($ausgabe, self::GEHEIM, 'Ausgabe von consentlaw:load');
        $this->pruefeOhneToken($this->logText(), self::GEHEIM, 'Log');
    }

    // AK2 – Randfall: kurzer Token
    #[DataProvider('kurzeTokensMitStoerungen')]
    public function test_reload_ausgabe_enthaelt_kurze_tokens_nicht(string $token, mixed $antwort): void
    {
        $this->setzeToken($token);
        $this->api(['*' => $antwort]);

        [, $ausgabe] = $this->fuehreAus(ConsentLawLoadCommand::class);

        $this->pruefeOhneToken($ausgabe, $token, 'Ausgabe von consentlaw:load');
        $this->pruefeOhneToken($this->logText(), $token, 'Log');
    }
}
