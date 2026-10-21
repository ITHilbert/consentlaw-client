<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Events;

/**
 * Störungsfall „Token vermutlich ungültig“: Bei `consentlaw:load` haben ALLE Typen
 * (Dokumente und Jobs) mit 404 („nicht veröffentlicht“) geantwortet. Einzelne 404 sind
 * normal (Kunde ohne AGB) und lösen dieses Event nicht aus.
 */
final readonly class TokenVermutlichUngueltig {}
