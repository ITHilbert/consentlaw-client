<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Contracts;

use ITHilbert\ConsentLaw\Data\FallbackEntry;

/**
 * Port für den Langzeitspeicher des letzten erfolgreichen Abrufs.
 *
 * Standard-Implementierung schreibt Dateien nach storage/app/consentlaw/.
 * Wer stattdessen eine Datenbank-Tabelle nutzen will, implementiert diesen
 * Port und bindet ihn im eigenen ServiceProvider.
 */
interface FallbackStore
{
    public function put(string $key, array $payload): void;

    public function get(string $key): ?FallbackEntry;

    public function forget(string $key): void;

    public function flush(): void;
}
