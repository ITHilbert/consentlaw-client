<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Benachrichtigung;

use ITHilbert\ConsentLaw\Events\NotstandVeraltet;
use ITHilbert\ConsentLaw\Events\RechtstextNichtLieferbar;
use ITHilbert\ConsentLaw\Events\TokenVermutlichUngueltig;

/**
 * Die drei Störungsfälle: Klartext, Handlungsempfehlung und Schlüssel der Meldung
 * an alerts (ein fester Schlüssel je Fall, unabhängig vom Typ).
 */
enum Stoerungsfall: string
{
    case NichtLieferbar = 'nicht-lieferbar';
    case TokenVermutlichUngueltig = 'token-vermutlich-ungueltig';
    case NotstandVeraltet = 'notstand-veraltet';

    public static function aus(RechtstextNichtLieferbar|TokenVermutlichUngueltig|NotstandVeraltet $event): self
    {
        return match (true) {
            $event instanceof RechtstextNichtLieferbar => self::NichtLieferbar,
            $event instanceof TokenVermutlichUngueltig => self::TokenVermutlichUngueltig,
            $event instanceof NotstandVeraltet => self::NotstandVeraltet,
        };
    }

    /** Kurzer Name des Falls (Titel der Meldung). */
    public function bezeichnung(): string
    {
        return match ($this) {
            self::NichtLieferbar => 'Rechtstext nicht lieferbar',
            self::TokenVermutlichUngueltig => 'Token vermutlich ungültig',
            self::NotstandVeraltet => 'Veralteter Notstand',
        };
    }

    /** Was passiert ist – in Klartext. */
    public function beschreibung(): string
    {
        return match ($this) {
            self::NichtLieferbar => 'consentLaw war nicht erreichbar, und es liegt kein gespeicherter Stand (Fallback) vor – '
                .'der Inhalt fehlt auf der Website.',
            self::TokenVermutlichUngueltig => 'Beim Neuladen (consentlaw:reload) haben alle Inhalte mit „nicht veröffentlicht“ '
                .'geantwortet. Fast sicher ist der Token ungültig – die Website zeigt keine Rechtstexte.',
            self::NotstandVeraltet => 'consentLaw war nicht erreichbar. Ausgeliefert wird der zuletzt gespeicherte Stand '
                .'(Fallback), der älter ist als erlaubt (fallback.warn_after) und inhaltlich überholt sein kann.',
        };
    }

    /**
     * Was zu tun ist.
     *
     * @return list<string>
     */
    public function massnahmen(): array
    {
        return match ($this) {
            self::NichtLieferbar => [
                'Status von consentLaw prüfen: Ist der Dienst erreichbar? Hinweise gibt der Fehlertext.',
                'Erreichbarkeit vom Server aus prüfen (Firewall, DNS, ausgehende Verbindungen).',
                'Ist der Fallback abgeschaltet (CONSENTLAW_FALLBACK_ENABLED=false), ihn wieder einschalten.',
                'Nach der Behebung „php artisan consentlaw:reload“ ausführen – dann liegt auch wieder ein Fallback-Stand bereit.',
            ],
            self::TokenVermutlichUngueltig => [
                'CONSENTLAW_TOKEN in der .env mit dem Token in consentLaw vergleichen (Domain → Integration & Setup).',
                'In consentLaw prüfen, ob die Inhalte für diese Domain veröffentlicht sind.',
                'Danach bei gecachter Config „php artisan config:clear“ und anschließend „php artisan consentlaw:reload“ ausführen.',
            ],
            self::NotstandVeraltet => [
                'Status von consentLaw prüfen: Ist der Dienst erreichbar? Hinweise gibt der Fehlertext.',
                'Erreichbarkeit vom Server aus prüfen (Firewall, DNS, ausgehende Verbindungen).',
                'Nach der Behebung „php artisan consentlaw:reload“ ausführen, damit wieder der aktuelle Stand ausgeliefert wird.',
            ],
        };
    }
}
