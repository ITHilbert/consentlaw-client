<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Data;

/**
 * Ausgang eines Abrufs – der Grund, warum ein Inhalt (nicht) geliefert wurde.
 */
enum Abrufstatus: string
{
    /** Inhalt ist aktuell: frischer Cache-Treffer oder erfolgreicher API-Abruf. */
    case Aktuell = 'aktuell';

    /** API gestört, der letzte bekannte Stand aus dem Fallback wurde geliefert. */
    case Fallback = 'fallback';

    /** Der Server antwortete mit 404: Inhalt nicht veröffentlicht (oder Token ungültig). */
    case NichtVeroeffentlicht = 'nicht_veroeffentlicht';

    /** API gestört (Timeout, Verbindungsfehler, 5xx, anderer Fehlerstatus), kein Fallback verfügbar. */
    case Stoerung = 'stoerung';
}
