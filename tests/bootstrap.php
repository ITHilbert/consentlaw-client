<?php

declare(strict_types=1);

// Abhängigkeiten aus dem Workspace-Root (Carbon, PHPUnit, illuminate/*).
require __DIR__.'/../../../vendor/autoload.php';

// Package-Klassen selbst laden, solange kein eigenes composer install lief.
spl_autoload_register(static function (string $class): void {
    foreach (['ITHilbert\\ConsentLaw\\Tests\\' => __DIR__.'/', 'ITHilbert\\ConsentLaw\\' => __DIR__.'/../src/'] as $prefix => $base) {
        if (str_starts_with($class, $prefix)) {
            $file = $base.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

            if (is_file($file)) {
                require $file;
            }

            return;
        }
    }
});
