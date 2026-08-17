<?php

declare(strict_types=1);

return [

    /*
    |---------------------------------------------------------------------------
    | Verbindung
    |---------------------------------------------------------------------------
    | Der Token wird pro Domain in consentLaw erzeugt (Dashboard → Domain →
    | „Integration & Setup"). Er identifiziert die Domain und gibt ausschliesslich
    | Leserechte auf die für diese Domain veröffentlichten Inhalte.
    */
    'base_url' => env('CONSENTLAW_URL', 'https://consentlaw.eu'),
    'token' => env('CONSENTLAW_TOKEN'),

    /*
    |---------------------------------------------------------------------------
    | HTTP
    |---------------------------------------------------------------------------
    | Timeouts bewusst knapp: Ist consentLaw langsam, darf die Kundenseite nicht
    | mitleiden – nach einem Fehlschlag greift der Fallback (siehe unten).
    */
    'http' => [
        'timeout' => env('CONSENTLAW_TIMEOUT', 5),
        'connect_timeout' => env('CONSENTLAW_CONNECT_TIMEOUT', 3),
        'retries' => env('CONSENTLAW_RETRIES', 1),
        'retry_delay' => 200, // Millisekunden
    ],

    /*
    |---------------------------------------------------------------------------
    | Cache
    |---------------------------------------------------------------------------
    | ttl (Sekunden) steuert, wie lange ein Inhalt als frisch gilt. Solange er
    | frisch ist, wird KEIN HTTP-Request ausgeführt. 0 = Cache aus (jeder
    | Seitenaufruf fragt die API – nur für Debugging sinnvoll).
    */
    'cache' => [
        'enabled' => env('CONSENTLAW_CACHE_ENABLED', true),
        'store' => env('CONSENTLAW_CACHE_STORE'), // null = Standard-Store
        'ttl' => env('CONSENTLAW_CACHE_TTL', 3600),
        'prefix' => 'consentlaw',
    ],

    /*
    |---------------------------------------------------------------------------
    | Fallback (Ausfallsicherheit)
    |---------------------------------------------------------------------------
    | Jeder erfolgreiche Abruf wird zusätzlich als Datei abgelegt. Ist die API
    | nicht erreichbar, wird dieser Stand ausgeliefert – unabhängig vom Alter.
    |
    | Grund: Ein Impressum oder eine Datenschutzerklärung darf NIE von der Seite
    | verschwinden, weil ein API-Aufruf fehlschlägt (Abmahnrisiko).
    |
    | 'max_age' nur für Warnungen im Log – der Inhalt wird trotzdem ausgeliefert.
    */
    'fallback' => [
        'enabled' => env('CONSENTLAW_FALLBACK_ENABLED', true),
        'disk' => env('CONSENTLAW_FALLBACK_DISK', 'local'),
        'path' => 'consentlaw',
        'warn_after' => env('CONSENTLAW_FALLBACK_WARN_AFTER', 86400 * 7),
    ],

    /*
    |---------------------------------------------------------------------------
    | Routen (optional, standardmässig AUS)
    |---------------------------------------------------------------------------
    | Standardweg ist das Einbinden per Blade-Komponente im eigenen Layout:
    |
    |     <x-consentlaw::impressum />
    |
    | Wer schnell starten will, kann fertige Seiten registrieren lassen. Die
    | Pfade sind frei wählbar, damit sie nicht mit bestehenden Seiten oder
    | anderssprachigen URLs kollidieren.
    */
    'routes' => [
        'enabled' => env('CONSENTLAW_ROUTES_ENABLED', false),
        'middleware' => ['web'],
        'prefix' => '',
        'layout' => null, // z.B. 'layouts.app'; null = eigenes Minimal-Layout
        'paths' => [
            'impressum' => 'impressum',
            'datenschutz' => 'datenschutz',
            'agb' => 'agb',
            'cookie-richtlinie' => 'cookie-richtlinie',
            'avv' => 'avv',
            'jobs' => 'jobs',
        ],
    ],

    /*
    |---------------------------------------------------------------------------
    | Sprache
    |---------------------------------------------------------------------------
    | null = aktuelle App-Locale verwenden. consentLaw ist mehrsprachig; der
    | Wert wird als ?locale= an die API übergeben.
    */
    'locale' => env('CONSENTLAW_LOCALE'),

];
