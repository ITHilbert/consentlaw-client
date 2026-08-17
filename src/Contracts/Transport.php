<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Contracts;

use ITHilbert\ConsentLaw\Exceptions\ConsentLawResponseException;
use ITHilbert\ConsentLaw\Exceptions\ConsentLawUnavailableException;

/**
 * Port für den HTTP-Zugriff auf die consentLaw-API.
 *
 * Eigener Port, damit Tests ohne HTTP laufen und der Transport (Guzzle, Curl,
 * Fake) austauschbar bleibt.
 */
interface Transport
{
    /**
     * Ruft einen Endpunkt ab und gibt die dekodierte Antwort zurück.
     *
     * @param  array<string, scalar|null>  $query
     * @return array<string, mixed>
     *
     * @throws ConsentLawUnavailableException
     * @throws ConsentLawResponseException
     */
    public function get(string $endpoint, array $query = []): array;
}
