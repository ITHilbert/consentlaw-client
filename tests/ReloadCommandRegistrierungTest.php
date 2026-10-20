<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Tests;

use Illuminate\Console\Application as ConsoleApplication;
use Illuminate\Contracts\Events\Dispatcher;
use Symfony\Component\Console\Exception\CommandNotFoundException;

/**
 * Registrierung der Artisan-Commands über den echten Provider.
 *
 * AK1 – `consentlaw:reload` ist registriert.
 * AK2 – `consentlaw:warm` ist unbekannt (kein Alias).
 */
final class ReloadCommandRegistrierungTest extends AppTestCase
{
    private function konsole(): ConsoleApplication
    {
        $konsole = new ConsoleApplication($this->app, $this->app->make(Dispatcher::class), 'test');
        $konsole->setAutoExit(false);

        return $konsole;
    }

    // AK1
    public function test_reload_ist_registriert_und_kennt_die_option_locale(): void
    {
        $befehl = $this->konsole()->find('consentlaw:reload');

        $this->assertSame('consentlaw:reload', $befehl->getName());
        $this->assertTrue($befehl->getDefinition()->hasOption('locale'));
    }

    // AK2
    public function test_warm_ist_nicht_registriert(): void
    {
        $this->assertArrayNotHasKey('consentlaw:warm', $this->konsole()->all());
    }

    // AK2
    public function test_warm_aufruf_wirft_command_not_found_und_ist_auch_kein_alias(): void
    {
        $konsole = $this->konsole();

        foreach ($konsole->all() as $befehl) {
            $this->assertNotContains('consentlaw:warm', $befehl->getAliases());
        }

        $this->expectException(CommandNotFoundException::class);
        $konsole->find('consentlaw:warm');
    }
}
