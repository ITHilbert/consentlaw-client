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
    'base_url' => env('CONSENTLAW_URL', 'https://www.consentlaw.eu'),
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
    | 'warn_after' nur für Warnungen im Log – der Inhalt wird trotzdem ausgeliefert.
    |
    | 'retry_after': Nach einer Störung (Timeout, 5xx, 429 …) wird die API für
    | diese Anzahl Sekunden nicht erneut gefragt, sondern direkt der Fallback
    | geliefert. Sonst würde nach jeder abgelaufenen Cache-TTL jeder
    | Seitenaufruf erneut auf den API-Timeout warten. Gilt unabhängig von
    | 'cache.enabled'. 0 = keine Sperre.
    */
    'fallback' => [
        'enabled' => env('CONSENTLAW_FALLBACK_ENABLED', true),
        'disk' => env('CONSENTLAW_FALLBACK_DISK', 'local'),
        'path' => 'consentlaw',
        'warn_after' => env('CONSENTLAW_FALLBACK_WARN_AFTER', 86400 * 7),
        'retry_after' => env('CONSENTLAW_FALLBACK_RETRY_AFTER', 300),
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
    | „Neu laden“-Button (zentraler Hilfen-Schalter APP_TEST, standardmässig AUS)
    |---------------------------------------------------------------------------
    | Zeigt über den Komponenten einen Button, der den Inhalt sofort frisch von
    | consentLaw holt (Cache und Sperrzeit werden umgangen). Der Schalter ist
    | `APP_TEST` und wirkt in jeder Umgebung, auch auf production. Die Route ist
    | auf 6 Anfragen pro Minute und IP begrenzt. Nach einer Änderung von APP_TEST
    | bei gecachter Config `php artisan config:clear` ausführen.
    */
    'refresh_button' => env('APP_TEST', false),

    /*
    |---------------------------------------------------------------------------
    | Consent-Banner
    |---------------------------------------------------------------------------
    | `<x-consentlaw::banner />` lädt banner.js von consentLaw (base_url, mit dem
    | Token oben). `enabled` = false schaltet Banner und den Link
    | `<x-consentlaw::cookie-einstellungen />` ab, z. B. in Entwicklungsumgebungen.
    | Ohne Token bleibt die Ausgabe in jedem Fall leer.
    */
    'banner' => [
        'enabled' => env('CONSENTLAW_BANNER_ENABLED', true),
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
