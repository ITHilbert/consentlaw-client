<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Events\Dispatcher;
use ITHilbert\ConsentLaw\ConsentLawClient;
use ITHilbert\ConsentLaw\Data\Abrufstatus;
use ITHilbert\ConsentLaw\Events\TokenVermutlichUngueltig;

/**
 * Verwirft Cache und Sperrvermerk und lädt alle Inhalte frisch von der API.
 *
 * Sinnvoll direkt nach einem Deploy: storage/ ist dann leer, und der erste
 * Besucher würde sonst auf den API-Abruf warten. Ausserdem liegt danach
 * sofort ein Fallback-Stand bereit, falls die API später ausfällt.
 *
 * Exit-Code: FAILURE nur bei echten Problemen – ohne Token oder wenn ein Inhalt
 * wegen einer Störung weder von der API noch aus dem Fallback geliefert werden
 * konnte. „Nicht veröffentlicht“ (404, z. B. Kunde ohne AGB) ist nur ein Hinweis –
 * außer, ALLE Typen (Dokumente und Jobs) antworten mit 404: Dann ist fast sicher der
 * Token ungültig, und der Befehl endet mit FAILURE samt Token-Hinweis und meldet das
 * Event TokenVermutlichUngueltig. Störungen je Typ meldet der ContentResolver selbst.
 */
final class ConsentLawReloadCommand extends Command
{
    protected $signature = 'consentlaw:reload {--locale= : Sprache, z.B. de oder en}';

    protected $description = 'Verwirft Cache und Sperrvermerk und lädt alle consentLaw-Inhalte frisch; legt den Fallback-Stand an';

    public function handle(ConsentLawClient $client, Dispatcher $events): int
    {
        if (! $client->isConfigured()) {
            $this->components->error('Kein CONSENTLAW_TOKEN gesetzt – nichts zu tun.');

            return self::FAILURE;
        }

        $locale = $this->option('locale');
        $client = $locale !== null ? $client->locale($locale) : $client;
        $failed = 0;
        $nichtVeroeffentlicht = 0;
        $typen = count(ConsentLawClient::DOCUMENT_TYPES) + 1; // Dokumente + Jobs

        foreach (array_keys(ConsentLawClient::DOCUMENT_TYPES) as $type) {
            $abruf = $client->document($type)->neuLaden();

            switch ($abruf->status) {
                case Abrufstatus::NichtVeroeffentlicht:
                    // Hinweis, kein Fehler: z. B. ein Kunde ohne AGB.
                    $this->components->warn("{$type}: nicht veröffentlicht");
                    $nichtVeroeffentlicht++;

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

        $jobs = $client->jobs()->neuLaden();

        switch ($jobs->status) {
            case Abrufstatus::NichtVeroeffentlicht:
                $this->components->warn('jobs: nicht veröffentlicht');
                $nichtVeroeffentlicht++;

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

        if ($nichtVeroeffentlicht === $typen) {
            $this->components->error('Alle Inhalte sind „nicht veröffentlicht“ – bitte den Token (CONSENTLAW_TOKEN) prüfen, er ist vermutlich ungültig.');
            $events->dispatch(new TokenVermutlichUngueltig);

            return self::FAILURE;
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
