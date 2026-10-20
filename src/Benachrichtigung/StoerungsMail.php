<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Benachrichtigung;

use Carbon\CarbonImmutable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Mail zu einem Störungsfall. Inhalt aus der View `consentlaw::mail.stoerung`
 * (überschreibbar per `vendor:publish --tag=consentlaw-views`).
 *
 * Bewusst nicht ShouldQueue: Die Meldung muss auch ohne laufenden Queue-Worker
 * rausgehen – gerade wenn auf dem Server etwas nicht stimmt.
 *
 * Der Fehlertext kommt bereits maskiert an (siehe MeldeStoerungPerMail).
 */
final class StoerungsMail extends Mailable
{
    private const SEKUNDEN_JE_TAG = 86400;

    public function __construct(
        private readonly Stoerungsfall $fall,
        private readonly string $appUrl,
        private readonly CarbonImmutable $zeitpunkt,
        private readonly ?string $typ = null,
        private readonly ?string $sprache = null,
        private readonly ?string $fehler = null,
        private readonly ?int $alterInSekunden = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "consentLaw-Störung: {$this->fall->bezeichnung()} – {$this->appUrl}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'consentlaw::mail.stoerung',
            with: [
                'fall' => $this->fall,
                'appUrl' => $this->appUrl,
                'zeitpunkt' => $this->zeitpunkt->format('d.m.Y, H:i:s T'),
                'typ' => $this->typ,
                'sprache' => $this->sprache,
                'fehler' => $this->fehler,
                'alter' => $this->alterAlsText(),
            ],
        );
    }

    /** Alter des Fallback-Stands in ganzen Tagen („10 Tage“); unter einem Tag in Stunden. */
    private function alterAlsText(): ?string
    {
        if ($this->alterInSekunden === null) {
            return null;
        }

        $tage = intdiv($this->alterInSekunden, self::SEKUNDEN_JE_TAG);

        if ($tage === 0) {
            $stunden = intdiv($this->alterInSekunden, 3600);

            return $stunden === 1 ? '1 Stunde' : "{$stunden} Stunden";
        }

        return $tage === 1 ? '1 Tag' : "{$tage} Tage";
    }
}
