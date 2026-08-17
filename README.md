# consentLaw Client

Laravel-Client für die [consentLaw](https://consentlaw.eu)-API. Holt Rechtstexte
(Impressum, Datenschutz, AGB, Cookie-Richtlinie, AVV) und Stellenangebote in ein
beliebiges Laravel-Projekt – mit Cache und ausfallsicherem Fallback.

## Installation

```bash
composer require ithilbert/consentlaw-client
```

In der `.env` den Token der Domain eintragen (zu finden in consentLaw unter
**Domain → Integration & Setup**):

```dotenv
CONSENTLAW_URL=https://consentlaw.eu
CONSENTLAW_TOKEN=dein-domain-token
```

Optional die Konfiguration veröffentlichen:

```bash
php artisan vendor:publish --tag=consentlaw-config
```

## Nutzung

### Blade-Komponenten (empfohlen)

Die Inhalte landen in **deinem** Layout, unter **deiner** URL:

```blade
{{-- Rechtstexte --}}
<x-consentlaw::impressum />
<x-consentlaw::datenschutz />
<x-consentlaw::agb />
<x-consentlaw::cookie-richtlinie />
<x-consentlaw::avv />

{{-- oder generisch --}}
<x-consentlaw::document type="impressum" />

{{-- Stellenangebote (inkl. Schema.org JobPosting für Google for Jobs) --}}
<x-consentlaw::jobs />
```

Ist kein Inhalt verfügbar, rendert die Komponente den Slot – so bleibt das
Layout intakt:

```blade
<x-consentlaw::jobs>
    <p>Zurzeit sind keine Stellen ausgeschrieben.</p>
</x-consentlaw::jobs>
```

Andere Sprache:

```blade
<x-consentlaw::impressum locale="en" />
```

### Programmatisch

```php
use ITHilbert\ConsentLaw\Facades\ConsentLaw;

ConsentLaw::impressum()->content();          // string (HTML/Markdown-Quelle)
ConsentLaw::impressum()->get();              // ?Document (mit Format, Datum, isStale)
ConsentLaw::datenschutz()->exists();         // bool

ConsentLaw::jobs()->all();                   // Collection<Job>
ConsentLaw::jobs()->current();               // ohne abgelaufene Fristen
ConsentLaw::jobs()->find($id);               // ?Job

ConsentLaw::locale('en')->agb()->content();  // andere Sprache
ConsentLaw::isConfigured();                  // Token gesetzt?
```

Eigenes Markup für Jobs:

```blade
@foreach(ConsentLaw::jobs()->current() as $job)
    <h2>{{ $job->title }}</h2>
    <p>{{ $job->employmentTypeLabel }} · {{ $job->location }}</p>
    {!! $job->isMarkdown() ? Str::markdown($job->description) : $job->description !!}
@endforeach
```

### Fertige Seiten (optional)

Standardmäßig registriert das Package **keine** Routen, um nicht mit bestehenden
Seiten zu kollidieren. Wer schnell starten will:

```dotenv
CONSENTLAW_ROUTES_ENABLED=true
```

Danach sind `/impressum`, `/datenschutz`, `/agb`, `/cookie-richtlinie`, `/avv`
und `/jobs` erreichbar. Pfade, Middleware, Prefix und Layout sind in
`config/consentlaw.php` frei einstellbar:

```php
'routes' => [
    'enabled' => true,
    'layout'  => 'layouts.app',      // eigenes Layout einhängen
    'paths'   => ['impressum' => 'legal-notice'],
],
```

## Caching & Ausfallsicherheit

Ein Impressum darf nie von der Seite verschwinden, nur weil ein API-Aufruf
fehlschlägt. Der Ablauf jedes Abrufs:

| Schritt | Verhalten |
|---|---|
| 1. Cache frisch (TTL, Standard 1 h) | direkt ausliefern, **kein** HTTP-Request |
| 2. Cache alt/leer | API abrufen → Cache **und** Fallback-Datei aktualisieren |
| 3. API gestört (Timeout, DNS, 5xx) | letzten Stand aus der Fallback-Datei ausliefern (`isStale = true`) |
| 4. Server antwortet ablehnend (404) | `null` – der Token ist ungültig oder es gibt keinen Inhalt |
| 5. Kein Fallback vorhanden | `null`, die Komponente rendert den Slot |

Der Unterschied zwischen 3 und 4 ist beabsichtigt: Bei einer **Störung** ist ein
alter Inhalt besser als keiner. Bei einer **klaren Absage** des Servers wäre es
falsch, weiter alte Inhalte zu zeigen.

Fallback-Dateien liegen unter `storage/app/consentlaw/` und enthalten den
Token-Hash im Namen – nach einem Token-Wechsel werden keine fremden Inhalte
wiederverwendet.

### Nach dem Deploy vorwärmen

`storage/` ist üblicherweise nicht im Git. Damit direkt nach einem Deploy ein
Fallback-Stand bereitliegt:

```bash
php artisan consentlaw:warm
php artisan consentlaw:flush   # Cache + Fallback verwerfen
```

## Konfiguration

| ENV | Standard | Bedeutung |
|---|---|---|
| `CONSENTLAW_URL` | `https://consentlaw.eu` | Basis-URL der Instanz |
| `CONSENTLAW_TOKEN` | – | Domain-Token (**Pflicht**) |
| `CONSENTLAW_CACHE_TTL` | `3600` | Frische in Sekunden, `0` = Cache aus |
| `CONSENTLAW_CACHE_STORE` | Standard-Store | z.B. `redis` |
| `CONSENTLAW_TIMEOUT` | `5` | HTTP-Timeout in Sekunden |
| `CONSENTLAW_FALLBACK_DISK` | `local` | Disk für die Fallback-Dateien |
| `CONSENTLAW_FALLBACK_WARN_AFTER` | `604800` | Log-Warnung ab diesem Alter (Sekunden) |
| `CONSENTLAW_ROUTES_ENABLED` | `false` | Fertige Seiten registrieren |
| `CONSENTLAW_LOCALE` | App-Locale | Sprache der Inhalte |

## Formaterkennung

consentLaw liefert Inhalte je Endpunkt als Markdown oder HTML. Wo die API kein
`format` mitschickt, wird es am Inhalt erkannt – Markdown wird serverseitig via
`Str::markdown()` gerendert. Inline-Tags wie `<br>` (etwa in Adresszeilen)
werden dabei korrekt nicht als HTML gewertet.

## Anpassbare Views

```bash
php artisan vendor:publish --tag=consentlaw-views
```

Danach liegen die Templates unter `resources/views/vendor/consentlaw/`.

## Eigener Fallback-Speicher

Wer statt Dateien eine Datenbank nutzen will, implementiert
`ITHilbert\ConsentLaw\Contracts\FallbackStore` und bindet ihn im eigenen
ServiceProvider:

```php
$this->app->singleton(FallbackStore::class, DatabaseFallbackStore::class);
```

## Lizenz

MIT – IT-Hilbert GmbH
