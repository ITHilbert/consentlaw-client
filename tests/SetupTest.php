<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Tests;

use ITHilbert\ConsentLaw\Support\ContentResolver;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Absicherung der Testumgebung (kein AK).
 *
 * Im Workspace-vendor liegt eine Kopie dieses Packages. Würde sie vor dem
 * Quellcode dieses Ordners geladen, liefen alle Tests gegen veralteten Code.
 */
final class SetupTest extends TestCase
{
    // Setup – kein AK
    public function test_tests_laufen_gegen_den_quellcode_dieses_packages(): void
    {
        $datei = (new ReflectionClass(ContentResolver::class))->getFileName();

        $this->assertSame(
            realpath(__DIR__.'/../src/Support/ContentResolver.php'),
            realpath((string) $datei),
            'ContentResolver wird nicht aus diesem Package geladen (vendor-Kopie hat Vorrang).',
        );
    }
}
