<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use ITHilbert\ConsentLaw\ConsentLawClient;
use ITHilbert\ConsentLaw\Data\Document;
use ITHilbert\ConsentLaw\Data\Job;
use ITHilbert\ConsentLaw\Exceptions\MissingTokenException;

/**
 * Inhalte für die Blade-Komponenten: ohne Token verhalten sie sich wie „kein Inhalt verfügbar“
 * (kein API-Aufruf, Warnung im Log) statt die Seite mit 500 abzubrechen.
 *
 * Die PHP-API (`ConsentLawClient::document()/jobs()`) wirft weiterhin.
 */
final class KomponentenInhalt
{
    public static function dokument(string $type, ?string $locale): ?Document
    {
        try {
            return self::client($locale)->document($type)->get();
        } catch (MissingTokenException) {
            self::warnen();

            return null;
        }
    }

    /**
     * @return Collection<int, Job>
     */
    public static function jobs(?string $locale, bool $auchAbgelaufene): Collection
    {
        try {
            $endpoint = self::client($locale)->jobs();

            return $auchAbgelaufene ? $endpoint->all() : $endpoint->current();
        } catch (MissingTokenException) {
            self::warnen();

            return new Collection;
        }
    }

    private static function client(?string $locale): ConsentLawClient
    {
        $client = app(ConsentLawClient::class);

        return $locale ? $client->locale($locale) : $client;
    }

    private static function warnen(): void
    {
        Log::warning('[consentlaw] CONSENTLAW_TOKEN fehlt – Komponente bleibt leer.');
    }
}
