<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Benachrichtigung;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Facades\Log;
use ITHilbert\Alerts\Contracts\AlertReporter;
use ITHilbert\Alerts\Domain\Meldung;
use ITHilbert\ConsentLaw\Events\NotstandVeraltet;
use ITHilbert\ConsentLaw\Events\RechtstextNichtLieferbar;
use ITHilbert\ConsentLaw\Events\TokenVermutlichUngueltig;
use ITHilbert\ConsentLaw\Support\TokenMaskierung;
use Throwable;

/**
 * Meldet jedes Störungs-Event über den Port des Basis-Packages `ithilbert/alerts`.
 *
 * Ein fester Schlüssel je Fall (`consentlaw.<fall>`); Drosselung und Versand liegen bei alerts.
 * Scheitert die Meldung, wird nur eine Warnung geloggt (Token maskiert): Seitenaufruf und
 * `consentlaw:load` verhalten sich wie ohne Meldung.
 */
final readonly class MeldeStoerungAnAlerts
{
    private const SEKUNDEN_JE_TAG = 86400;

    public function __construct(
        private AlertReporter $reporter,
        private ConfigRepository $config,
    ) {}

    public function handle(RechtstextNichtLieferbar|TokenVermutlichUngueltig|NotstandVeraltet $event): void
    {
        $token = (string) $this->config->get('consentlaw.token', '');
        $fall = Stoerungsfall::aus($event);

        try {
            $this->reporter->melde($this->baueMeldung($fall, $event, $token));
        } catch (Throwable $e) {
            Log::warning('[consentlaw] Störungsmeldung an alerts fehlgeschlagen.', [
                'fall' => $fall->value,
                'error' => TokenMaskierung::bereinige($e->getMessage(), $token),
            ]);
        }
    }

    private function baueMeldung(
        Stoerungsfall $fall,
        RechtstextNichtLieferbar|TokenVermutlichUngueltig|NotstandVeraltet $event,
        string $token,
    ): Meldung {
        $details = ['Website' => (string) $this->config->get('app.url', '')];
        $fehlertext = null;

        if (! $event instanceof TokenVermutlichUngueltig) {
            $details['Typ'] = $event->typ;

            if ($event->locale !== null) {
                $details['Sprache'] = $event->locale;
            }

            $fehlertext = TokenMaskierung::bereinige($event->fehler, $token);
        }

        if ($event instanceof NotstandVeraltet) {
            $details['Alter'] = $this->alterAlsText($event->alterInSekunden);
        }

        $details['Was ist passiert'] = $fall->beschreibung();
        $details['Was ist zu tun'] = implode("\n", array_map(
            static fn (string $massnahme): string => "- {$massnahme}",
            $fall->massnahmen(),
        ));

        return new Meldung(
            schluessel: 'consentlaw.'.$fall->value,
            titel: $fall->bezeichnung(),
            fehlertext: $fehlertext,
            details: $details,
        );
    }

    /** Alter in ganzen Tagen („10 Tage“); unter einem Tag in Stunden. */
    private function alterAlsText(int $sekunden): string
    {
        $tage = intdiv($sekunden, self::SEKUNDEN_JE_TAG);

        if ($tage === 0) {
            $stunden = intdiv($sekunden, 3600);

            return $stunden === 1 ? '1 Stunde' : "{$stunden} Stunden";
        }

        return $tage === 1 ? '1 Tag' : "{$tage} Tage";
    }
}
