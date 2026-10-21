<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Tests;

use Illuminate\Console\Application as ConsoleApplication;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Registrierung der Artisan-Commands über den echten Provider.
 *
 * AK1 – `consentlaw:load` ist registriert und kennt `--locale`.
 */
final class LoadCommandRegistrierungTest extends AppTestCase
{
    private function konsole(): ConsoleApplication
    {
        $konsole = new ConsoleApplication($this->app, $this->app->make(Dispatcher::class), 'test');
        $konsole->setAutoExit(false);

        return $konsole;
    }

    // AK1
    public function test_load_ist_registriert_und_kennt_die_option_locale(): void
    {
        $befehl = $this->konsole()->find('consentlaw:load');

        $this->assertSame('consentlaw:load', $befehl->getName());
        $this->assertTrue($befehl->getDefinition()->hasOption('locale'));
    }
}
