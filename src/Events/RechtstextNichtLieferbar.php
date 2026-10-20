<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Events;

/**
 * Störungsfall „kein Inhalt lieferbar“: Der Abruf eines Typs ist gescheitert (Timeout,
 * 5xx, 429 …, kein 404), und es gibt keinen Fallback-Stand – oder der Fallback ist
 * abgeschaltet. Die Seite zeigt für diesen Typ nichts an (Abmahnrisiko).
 *
 * Ausgelöst vom ContentResolver – nur, wenn wirklich abgerufen wurde (nicht während der
 * Sperrzeit nach einer Störung).
 */
final readonly class RechtstextNichtLieferbar
{
    public function __construct(
        /** Inhaltstyp, z. B. 'impressum' oder 'jobs'. */
        public string $typ,
        /** Angefragte Sprache; null = Standard der API bzw. App. */
        public ?string $locale,
        /** Fehlermeldung des Abrufs (Token bereits maskiert). */
        public string $fehler,
    ) {}
}
