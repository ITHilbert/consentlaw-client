<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Console;

use Illuminate\Console\Command;
use ITHilbert\ConsentLaw\ConsentLawClient;

final class ConsentLawFlushCommand extends Command
{
    protected $signature = 'consentlaw:flush';

    protected $description = 'Leert Cache und Fallback-Dateien des consentLaw-Clients';

    public function handle(ConsentLawClient $client): int
    {
        $client->flush();

        $this->components->info('consentLaw: Cache und Fallback geleert.');

        return self::SUCCESS;
    }
}
