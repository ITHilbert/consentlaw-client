<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Console;

use Illuminate\Console\Command;
use ITHilbert\ConsentLaw\ConsentLawClient;
use ITHilbert\ConsentLaw\Data\Abrufstatus;

/**
 * Wärmt Cache und Fallback vor.
 *
 * Sinnvoll direkt nach einem Deploy: storage/ ist dann leer, und der erste
 * Besucher würde sonst auf den API-Abruf warten. Ausserdem liegt danach
 * sofort ein Fallback-Stand bereit, falls die API später ausfällt.
 *
 * Exit-Code: FAILURE nur bei echten Problemen – ohne Token oder wenn ein Inhalt
 * wegen einer Störung weder von der API noch aus dem Fallback geliefert werden
 * konnte. „Nicht veröffentlicht“ (404, z. B. Kunde ohne AGB) ist nur ein Hinweis.
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
            $endpoint = $client->document($type);
            $endpoint->forget();
            $abruf = $endpoint->abrufen();

            switch ($abruf->status) {
                case Abrufstatus::NichtVeroeffentlicht:
                    // Hinweis, kein Fehler: z. B. ein Kunde ohne AGB.
                    $this->components->warn("{$type}: nicht veröffentlicht");

                    break;

                case Abrufstatus::Stoerung:
                    $this->components->error("{$type}: Störung – weder von der API noch aus dem Fallback lieferbar");
                    $failed++;

                    break;

                default:
                    $document = $abruf->inhalt;

                    if ($document === null || $document->isEmpty()) {
                        $this->components->warn("{$type}: kein Inhalt");

                        break;
                    }

                    $this->components->info(sprintf(
                        '%s: %d Zeichen%s',
                        $type,
                        mb_strlen($document->content),
                        $document->isStale ? ' (aus Fallback – API nicht erreichbar!)' : '',
                    ));
            }
        }

        $jobsEndpoint = $client->jobs();
        $jobsEndpoint->forget();
        $jobs = $jobsEndpoint->abrufen();

        switch ($jobs->status) {
            case Abrufstatus::NichtVeroeffentlicht:
                $this->components->warn('jobs: nicht veröffentlicht');

                break;

            case Abrufstatus::Stoerung:
                $this->components->error('jobs: Störung – weder von der API noch aus dem Fallback lieferbar');
                $failed++;

                break;

            default:
                $this->components->info(sprintf(
                    'jobs: %d Stellenangebote%s',
                    $jobs->inhalt->count(),
                    $jobs->status === Abrufstatus::Fallback ? ' (aus Fallback – API nicht erreichbar!)' : '',
                ));
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
