<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Events;

/**
 * Störungsfall „Notstand veraltet“: Der Abruf eines Typs ist gescheitert, ausgeliefert wird
 * der Fallback-Stand – der aber älter ist als `consentlaw.fallback.warn_after`. Der Inhalt
 * bleibt sichtbar, kann aber inhaltlich überholt sein.
 *
 * Ausgelöst vom ContentResolver – nur, wenn wirklich abgerufen wurde (nicht während der
 * Sperrzeit nach einer Störung).
 */
final readonly class NotstandVeraltet
{
    public function __construct(
        /** Inhaltstyp, z. B. 'impressum' oder 'jobs'. */
        public string $typ,
        /** Angefragte Sprache; null = Standard der API bzw. App. */
        public ?string $locale,
        /** Alter des ausgelieferten Fallback-Stands in Sekunden. */
        public int $alterInSekunden,
        /** Fehlermeldung des Abrufs (Token bereits maskiert). */
        public string $fehler,
    ) {}
}
