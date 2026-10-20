<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Data;

use Carbon\CarbonImmutable;

/**
 * Ergebnis eines Endpoint-Abrufs: der Inhalt und der Grund, warum er (nicht) da ist.
 *
 * Für Aufrufer, die „nicht veröffentlicht“ von „Störung“ unterscheiden müssen
 * (z. B. `consentlaw:reload`). Wer nur den Inhalt braucht, nutzt weiter get()/all().
 *
 * @template T
 */
final readonly class Abrufergebnis
{
    /**
     * @param  T|null  $inhalt  Document bzw. Collection<Job>; null nur bei NichtVeroeffentlicht/Stoerung (Dokumente)
     */
    public function __construct(
        public Abrufstatus $status,
        public mixed $inhalt,
        /** Zeitpunkt des gelieferten Stands (Abruf bzw. Fallback-Stand); null, wenn nichts geliefert wurde. */
        public ?CarbonImmutable $stand = null,
    ) {}
}
