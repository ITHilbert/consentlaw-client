<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Exceptions;

/** In der Konfiguration ist kein CONSENTLAW_TOKEN gesetzt. */
final class MissingTokenException extends ConsentLawException
{
    public static function make(): self
    {
        return new self(
            'Kein consentLaw-Token konfiguriert. Bitte CONSENTLAW_TOKEN in der .env setzen '
            .'(Token findest du in consentLaw unter Domain → Integration & Setup).'
        );
    }
}
