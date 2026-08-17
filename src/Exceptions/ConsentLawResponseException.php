<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Exceptions;

/**
 * consentLaw hat geantwortet, aber mit einem Fehler (404 = Token ungültig,
 * 5xx = Serverfehler) oder mit unlesbarem Inhalt.
 */
final class ConsentLawResponseException extends ConsentLawException
{
    public function __construct(string $message, public readonly int $status = 0)
    {
        parent::__construct($message);
    }
}
