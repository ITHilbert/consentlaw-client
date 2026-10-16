<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Exceptions;

/**
 * consentLaw hat geantwortet, aber ablehnend (404 = Token ungültig oder Inhalt
 * nicht veröffentlicht) oder mit unlesbarem Inhalt.
 *
 * Nur 404 ist „nicht veröffentlicht“; alle übrigen Fehlerstatus kommen als
 * ConsentLawUnavailableException und gelten als Störung.
 */
final class ConsentLawResponseException extends ConsentLawException
{
    public function __construct(string $message, public readonly int $status = 0)
    {
        parent::__construct($message);
    }
}
