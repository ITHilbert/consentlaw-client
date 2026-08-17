<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Console;

use Illuminate\Console\Command;
use ITHilbert\ConsentLaw\ConsentLawClient;

/**
 * Wärmt Cache und Fallback vor.
 *
 * Sinnvoll direkt nach einem Deploy: storage/ ist dann leer, und der erste
 * Besucher würde sonst auf den API-Abruf warten. Ausserdem liegt danach
 * sofort ein Fallback-Stand bereit, falls die API später ausfällt.
 */
final class ConsentLawWarmCommand extends Command
{
    protected $signature = 'consentlaw:warm {--locale= : Sprache, z.B. de oder en}';

    protected $description = 'Lädt alle consentLaw-Inhalte vor und legt den Fallback-Stand an';

    public function handle(ConsentLawClient $client): int
    {
        if (! $client->isConfigured()) {
            $this->components->error('Kein CONSENTLAW_TOKEN gesetzt – nichts zu tun.');

            return self::FAILURE;
        }

        $locale = $this->option('locale');
        $client = $locale !== null ? $client->locale($locale) : $client;
        $failed = 0;

        foreach (array_keys(ConsentLawClient::DOCUMENT_TYPES) as $type) {
            $document = $client->document($type)->refresh();

            if ($document === null || $document->isEmpty()) {
                $this->components->warn("{$type}: kein Inhalt");
                $failed++;

                continue;
            }

            $this->components->info(sprintf(
                '%s: %d Zeichen%s',
                $type,
                mb_strlen($document->content),
                $document->isStale ? ' (aus Fallback – API nicht erreichbar!)' : '',
            ));
        }

        $jobs = $client->jobs()->refresh();
        $this->components->info("jobs: {$jobs->count()} Stellenangebote");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
