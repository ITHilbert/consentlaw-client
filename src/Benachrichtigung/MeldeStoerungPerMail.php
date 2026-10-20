<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Benachrichtigung;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Support\Facades\Log;
use ITHilbert\ConsentLaw\Events\NotstandVeraltet;
use ITHilbert\ConsentLaw\Events\RechtstextNichtLieferbar;
use ITHilbert\ConsentLaw\Events\TokenVermutlichUngueltig;
use ITHilbert\ConsentLaw\Support\TokenMaskierung;
use Throwable;

/**
 * Schickt zu jedem Störungs-Event eine Mail an `consentlaw.alerts.mail`.
 *
 * Übergangslösung bis zu einem eigenen Alert-Package: Das übernimmt später die Events,
 * dieser Listener wird dann per `consentlaw.alerts.mail_listener = false` abgeschaltet.
 *
 * - Ohne Empfänger passiert nichts.
 * - Höchstens eine Mail je Fall und 24 h (Sperre im Cache, unabhängig von Typ und Anzahl
 *   der Seitenaufrufe). Die Sperre gilt auch, wenn der Versand scheitert – sonst würde bei
 *   kaputtem SMTP jeder Seitenaufruf erneut auf den Mail-Timeout warten.
 * - Synchroner Versand (kein Queueing): Die Meldung darf nicht an einem fehlenden
 *   Queue-Worker hängen.
 * - Fehler beim Versand werden geloggt und nie weitergeworfen: Seitenaufruf und
 *   `consentlaw:reload` verhalten sich wie ohne Mail.
 */
final readonly class MeldeStoerungPerMail
{
    private const SPERRE_SEKUNDEN = 86400;

    public function __construct(
        private ConfigRepository $config,
        private MailFactory $mail,
        private CacheFactory $cache,
    ) {}

    public function handle(RechtstextNichtLieferbar|TokenVermutlichUngueltig|NotstandVeraltet $event): void
    {
        $empfaenger = $this->empfaenger();

        if ($empfaenger === []) {
            return;
        }

        $fall = Stoerungsfall::aus($event);
        $token = (string) $this->config->get('consentlaw.token', '');

        try {
            if (! $this->sperreSetzen($fall)) {
                return;
            }

            $this->mail->mailer($this->mailer())
                ->to($empfaenger)
                ->send($this->baueMail($fall, $event, $token));
        } catch (Throwable $e) {
            Log::error('[consentlaw] Störungsmeldung per Mail fehlgeschlagen.', [
                'fall' => $fall->value,
                'error' => TokenMaskierung::bereinige($e->getMessage(), $token),
            ]);
        }
    }

    /** @return list<string> */
    private function empfaenger(): array
    {
        $adressen = explode(',', (string) $this->config->get('consentlaw.alerts.mail', ''));

        return array_values(array_filter(
            array_map(trim(...), $adressen),
            static fn (string $adresse): bool => $adresse !== '',
        ));
    }

    /** Name des Mailers; null = Standard-Mailer der App. */
    private function mailer(): ?string
    {
        $mailer = $this->config->get('consentlaw.alerts.mailer');

        return is_string($mailer) && $mailer !== '' ? $mailer : null;
    }

    /** True, wenn für diesen Fall in den letzten 24 h noch keine Mail rausging (atomar per add). */
    private function sperreSetzen(Stoerungsfall $fall): bool
    {
        $prefix = (string) $this->config->get('consentlaw.cache.prefix', 'consentlaw');
        $store = $this->config->get('consentlaw.cache.store');

        return $this->cache
            ->store(is_string($store) && $store !== '' ? $store : null)
            ->add("{$prefix}:stoerungsmail:{$fall->value}", true, self::SPERRE_SEKUNDEN);
    }

    private function baueMail(
        Stoerungsfall $fall,
        RechtstextNichtLieferbar|TokenVermutlichUngueltig|NotstandVeraltet $event,
        string $token,
    ): StoerungsMail {
        $appUrl = (string) $this->config->get('app.url', '');
        $jetzt = CarbonImmutable::now();

        return match (true) {
            $event instanceof RechtstextNichtLieferbar => new StoerungsMail(
                fall: $fall,
                appUrl: $appUrl,
                zeitpunkt: $jetzt,
                typ: $event->typ,
                sprache: $event->locale,
                fehler: TokenMaskierung::bereinige($event->fehler, $token),
            ),
            $event instanceof NotstandVeraltet => new StoerungsMail(
                fall: $fall,
                appUrl: $appUrl,
                zeitpunkt: $jetzt,
                typ: $event->typ,
                sprache: $event->locale,
                fehler: TokenMaskierung::bereinige($event->fehler, $token),
                alterInSekunden: $event->alterInSekunden,
            ),
            $event instanceof TokenVermutlichUngueltig => new StoerungsMail(
                fall: $fall,
                appUrl: $appUrl,
                zeitpunkt: $jetzt,
            ),
        };
    }
}
