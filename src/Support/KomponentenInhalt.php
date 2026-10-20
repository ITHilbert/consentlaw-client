<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Support;

use Illuminate\Support\Collection;
use ITHilbert\ConsentLaw\ConsentLawClient;
use ITHilbert\ConsentLaw\Data\Document;
use ITHilbert\ConsentLaw\Data\Job;
use ITHilbert\ConsentLaw\Exceptions\MissingTokenException;
use Psr\Log\LoggerInterface;

/**
 * Inhalte für Blade-Komponenten und Fertig-Seiten: ohne Token verhalten sie sich wie
 * „kein Inhalt verfügbar“ (kein API-Aufruf, Warnung im Log) statt mit 500 abzubrechen.
 * Das Abfangen des fehlenden Tokens steht nur hier.
 *
 * Die PHP-API (`ConsentLawClient::document()/jobs()`) wirft weiterhin.
 */
final class KomponentenInhalt
{
    public function __construct(
        private readonly ConsentLawClient $client,
        private readonly LoggerInterface $logger,
    ) {}

    public function dokument(string $type, ?string $locale): ?Document
    {
        try {
            return $this->client($locale)->document($type)->get();
        } catch (MissingTokenException) {
            $this->warnen();

            return null;
        }
    }

    /**
     * @return Collection<int, Job>
     */
    public function jobs(?string $locale, bool $auchAbgelaufene): Collection
    {
        try {
            $endpoint = $this->client($locale)->jobs();

            return $auchAbgelaufene ? $endpoint->all() : $endpoint->current();
        } catch (MissingTokenException) {
            $this->warnen();

            return new Collection;
        }
    }

    private function client(?string $locale): ConsentLawClient
    {
        return $locale ? $this->client->locale($locale) : $this->client;
    }

    private function warnen(): void
    {
        $this->logger->warning('[consentlaw] CONSENTLAW_TOKEN fehlt – Komponente bleibt leer.');
    }
}
