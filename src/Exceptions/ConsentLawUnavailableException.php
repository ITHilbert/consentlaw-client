<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Exceptions;

/**
 * consentLaw war nicht erreichbar (Timeout, DNS, Verbindungsabbruch).
 *
 * Wird intern gefangen: Der Client liefert stattdessen den Fallback-Stand aus.
 */
final class ConsentLawUnavailableException extends ConsentLawException {}
