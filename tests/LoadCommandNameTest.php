<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Tests;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * AK2 – Der Command heißt überall nur `consentlaw:load`.
 *
 * Die alten Namen werden hier zusammengesetzt, damit diese Datei sie nicht selbst enthält.
 */
final class LoadCommandNameTest extends AppTestCase
{
    /** @return list<string> */
    private function alteNamen(): array
    {
        return ['consentlaw:'.'reload', 'consentlaw:'.'warm'];
    }

    // AK2
    public function test_stoerungsmeldungen_nennen_den_command_consentlaw_load(): void
    {
        $quelle = (string) file_get_contents(__DIR__.'/../src/Benachrichtigung/Stoerungsfall.php');

        $this->assertStringContainsString('php artisan consentlaw:load', $quelle);
        foreach ($this->alteNamen() as $alt) {
            $this->assertStringNotContainsString($alt, $quelle);
        }
    }

    // AK2
    public function test_src_enthaelt_keinen_alten_commandnamen(): void
    {
        $treffer = [];
        $dateien = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(__DIR__.'/../src', FilesystemIterator::SKIP_DOTS)
        );

        foreach ($dateien as $datei) {
            if (! $datei->isFile()) {
                continue;
            }
            $inhalt = (string) file_get_contents($datei->getPathname());
            foreach ($this->alteNamen() as $alt) {
                if (str_contains($inhalt, $alt)) {
                    $treffer[] = $datei->getPathname().' → '.$alt;
                }
            }
        }

        $this->assertSame([], $treffer, 'Alter Commandname in src/ gefunden.');
    }

    // AK2
    public function test_readme_nennt_nur_consentlaw_load(): void
    {
        $readme = (string) file_get_contents(__DIR__.'/../README.md');

        $this->assertStringContainsString('php artisan consentlaw:load', $readme);
        foreach ($this->alteNamen() as $alt) {
            $this->assertStringNotContainsString($alt, $readme);
        }
    }
}
