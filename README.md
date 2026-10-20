# consentLaw Client

Laravel-Client für die [consentLaw](https://www.consentlaw.eu)-API. Holt Rechtstexte
(Impressum, Datenschutz, AGB, Cookie-Richtlinie, AVV) und Stellenangebote in ein
beliebiges Laravel-Projekt – mit Cache und ausfallsicherem Fallback.

## Installation

```bash
composer require ithilbert/consentlaw-client
```

In der `.env` den Token der Domain eintragen (zu finden in consentLaw unter
**Domain → Integration & Setup**):

```dotenv
CONSENTLAW_URL=https://www.consentlaw.eu
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
    {!! $job->isMarkdown() ? \ITHilbert\ConsentLaw\Support\MarkdownRenderer::render($job->description) : $job->description !!}
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
| 2. Wartezeit nach Störung aktiv | **kein** HTTP-Request, direkt Schritt 4 |
| 3. Cache alt/leer | API abrufen → Cache **und** Fallback-Datei aktualisieren |
| 4. API gestört (Timeout, DNS, 5xx, 429, 401, 403 …) | Wartezeit setzen, letzten Stand aus der Fallback-Datei ausliefern (`isStale = true`) |
| 5. Server antwortet mit **404** | `null` – der Token ist ungültig oder der Inhalt ist nicht veröffentlicht. Der Vermerk wird für die Cache-TTL gecacht (kein erneuter HTTP-Aufruf), 404 wird nicht wiederholt |
| 6. Kein Fallback vorhanden | `null`, die Komponente rendert den Slot |

Der Unterschied zwischen 4 und 5 ist beabsichtigt: Bei einer **Störung** ist ein
alter Inhalt besser als keiner. Nur eine **404** ist eine klare Aussage des Servers
(„nicht veröffentlicht“) – dann wäre es falsch, weiter alte Inhalte zu zeigen. Jeder
andere Fehlerstatus (z. B. 429 durch das Rate-Limit von consentLaw) gilt als Störung.

### „Nicht veröffentlicht“ wird gecacht

Ein 404 wird für die Dauer von `CONSENTLAW_CACHE_TTL` vermerkt: Ein Kunde ohne AGB löst
nicht bei jedem Seitenaufruf einen HTTP-Aufruf aus. Ein 404 wird außerdem nicht
wiederholt (`http.retries` gilt nur für Störungen: Verbindungsfehler, 5xx, andere
Fehlerstatus). Bei `cache.enabled=false` oder TTL `0` wird nicht gecacht.
`forget()`, `refresh()`, `consentlaw:warm` und `consentlaw:flush` verwerfen den Vermerk.

### Wartezeit nach einer Störung

Nach einer Störung wird die API für `CONSENTLAW_FALLBACK_RETRY_AFTER` Sekunden
(Standard `300`) **nicht erneut gefragt**, sondern direkt der Fallback ausgeliefert –
auch wenn keiner existiert (dann direkt nichts). Ohne diese Wartezeit würde nach jeder
abgelaufenen Cache-TTL jeder Seitenaufruf erneut auf den API-Timeout warten, und die
Homepage wäre bei einem Ausfall von consentLaw langsam. Nach Ablauf wird die API wieder
gefragt. Die Wartezeit gilt je Inhalt, liegt im Cache-Store und ist von
`CONSENTLAW_CACHE_TTL`/`cache.enabled` unabhängig; `0` schaltet sie ab.
`consentlaw:warm` und `consentlaw:flush` heben sie auf.

Fallback-Dateien liegen auf der konfigurierten Disk unter `consentlaw/` (beim
Standard-Disk `local` ist das `storage/app/private/consentlaw/`) und enthalten den
Token-Hash im Namen – nach einem Token-Wechsel werden keine fremden Inhalte
wiederverwendet.

### Nach dem Deploy vorwärmen

Der `storage/`-Ordner ist üblicherweise nicht im Git. Damit direkt nach einem Deploy ein
Fallback-Stand bereitliegt:

```bash
php artisan consentlaw:warm
php artisan consentlaw:flush   # Cache + Fallback verwerfen
```

`consentlaw:warm` meldet einen nicht veröffentlichten Typ (404, z. B. ein Kunde ohne
AGB) nur als Hinweis und endet mit Exit-Code 0. Exit-Code 1 gibt es nur ohne Token oder
wenn ein Typ wegen einer **Störung** weder von der API noch aus dem Fallback geliefert
werden konnte – so scheitern Deploy-Skripte nur bei echten Problemen.

Antworten **alle** Typen (5 Dokumente und Jobs) mit 404, ist fast sicher der Token
ungültig: Dann endet der Befehl mit Exit-Code 1 und dem Hinweis, den Token zu prüfen.

## Konfiguration

| ENV | Standard | Bedeutung |
|---|---|---|
| `CONSENTLAW_URL` | `https://www.consentlaw.eu` | Basis-URL der Instanz |
| `CONSENTLAW_TOKEN` | – | Domain-Token (**Pflicht**) |
| `CONSENTLAW_CACHE_TTL` | `3600` | Frische in Sekunden, `0` = Cache aus |
| `CONSENTLAW_CACHE_STORE` | Standard-Store | z.B. `redis` |
| `CONSENTLAW_TIMEOUT` | `5` | HTTP-Timeout in Sekunden |
| `CONSENTLAW_FALLBACK_DISK` | `local` | Disk für die Fallback-Dateien |
| `CONSENTLAW_FALLBACK_WARN_AFTER` | `604800` | Log-Warnung ab diesem Alter (Sekunden) |
| `CONSENTLAW_FALLBACK_RETRY_AFTER` | `300` | Wartezeit nach einer Störung, in der die API nicht erneut gefragt wird (Sekunden, `0` = aus) |
| `CONSENTLAW_ROUTES_ENABLED` | `false` | Fertige Seiten registrieren |
| `APP_TEST` | `false` | Zentraler Hilfen-Schalter: „Neu laden“-Button (wirkt in jeder Umgebung, auch Production) |
| `CONSENTLAW_LOCALE` | App-Locale | Sprache der Inhalte |

## Neu laden per Button

Wer Texte in consentLaw ändert, kann sie auf einer Homepage sofort neu laden – ohne SSH und
ohne auf den stündlichen Abruf zu warten. Der Schalter ist der zentrale Hilfen-Schalter
`APP_TEST` in der `.env`; er wirkt in **jeder** Umgebung, auch auf Production:

```dotenv
APP_TEST=true
```

Ablauf: `APP_TEST=true` setzen → Texte neu laden → `APP_TEST=false` zurücksetzen. Ist die
Config gecacht, nach jeder Änderung `php artisan config:clear` ausführen (bzw. danach neu
`config:cache`).

Mit gesetztem Schalter erscheint **über** jeder Dokument-Komponente (`<x-consentlaw::impressum />`,
`<x-consentlaw::document />` …) und über `<x-consentlaw::jobs />` ein Button „Neu laden“
(auch, wenn gerade kein Inhalt oder ein Slot-Fallback angezeigt wird). Ein Klick holt genau
diesen Typ frisch von consentLaw (Cache und Wartezeit nach einer Störung werden umgangen,
Fallback-Datei wird aktualisiert) und leitet auf die vorherige Seite zurück. Eine Störung
führt nicht zu einer Fehlerseite; die Seite zeigt dann den bisherigen Stand.

- **Standard aus:** Ohne den Schalter ist die Route (`POST consentlaw/neu-laden/{typ}`,
  Name `consentlaw.neu-laden`) nicht registriert.
- **Alternative ohne Button:** `php artisan consentlaw:warm`.
- **CSRF-Hinweis:** Homepages, deren `web`-Gruppe weder `StartSession` noch `VerifyCsrfToken`
  enthält, haben für diese Route keinen CSRF-Schutz. Solange `APP_TEST` an ist, kann dann eine
  fremde Seite das Neu laden auslösen (begrenzt auf 6 Anfragen pro Minute und IP). Den Schalter
  deshalb nur kurz eingeschaltet lassen.
- **Limit:** höchstens 6 Anfragen pro Minute und IP (sonst 429); der Aufruf braucht ein
  gültiges CSRF-Token (Middleware-Gruppe `web`, siehe CSRF-Hinweis). Die Rückleitung geht nur auf die eigene
  Domain. Hinter einem Reverse Proxy ohne konfigurierte Trusted Proxies teilen sich alle
  Besucher eine IP und damit das Limit.
- **Darstellung:** dezentes Entwicklungswerkzeug (kleiner Button mit gestricheltem Rand); das
  Partial bringt einen eigenen `<style>`-Block mit niedriger Spezifität mit, das Formular trägt
  die Klasse `consentlaw-refresh` und lässt sich im Seiten-CSS überschreiben. Eigenes Markup
  über die veröffentlichten Views (`partials/neu-laden.blade.php`).
- **Sprache:** Hat die Komponente ein `locale`-Attribut (`<x-consentlaw::impressum locale="en" />`),
  sendet der Button diese Sprache mit (verstecktes Feld `locale`; erlaubt sind Angaben wie
  `de`, `en`, `en_GB`, `pt-BR` – andere Schreibweisen wie `DE` führen beim Klick zu 422) und lädt genau diese
  Sprachvariante neu. Ohne `locale`-Attribut wird die konfigurierte Standardsprache
  (`CONSENTLAW_LOCALE`) neu geladen. Eine ungültige Sprachangabe (erlaubt: `de`, `en`,
  `de_DE`, `pt-BR` …) wird mit 422 abgewiesen, ohne dass die API aufgerufen wird.
- **Freigabe auch in der Aktion:** Die Aktion prüft die Freigabe (Schalter `APP_TEST` an) bei jedem Aufruf erneut und antwortet sonst mit 404 ohne API-Aufruf – auch
  wenn die Route z. B. aus einem Route-Cache stammt.
- **Bekannte Grenze:** Ist der Fallback per `.env` abgeschaltet und das Neu-Laden scheitert,
  kann die Seite bis zum nächsten erfolgreichen Abruf ohne Inhalt sein (Standard: Fallback an).

## Format der Inhalte

consentLaw liefert Inhalte je Endpunkt als Markdown oder HTML. Das `format`-Feld der
API-Antwort (`markdown` bzw. `html`) gilt immer. Schickt die API kein `format` mit,
gilt `markdown` – es gibt **keine Erkennung am Inhalt** mehr. So läuft auch ein
HTML-artiger Inhalt ohne Formatangabe durch den Markdown-Filter (siehe unten) und nie
ungefiltert auf die Seite.

**Allowlist:** Nur ein Format, das exakt `html` lautet, wird ungefiltert ausgegeben.
Jeder andere Wert (leer, `HTML`, `Html`, `text`, unbekannt, falscher Datentyp) gilt als
Markdown und läuft durch den Filter. Das gilt für Dokumente (`format`) ebenso wie für
Job-Beschreibungen (`description_format`). Die Normalisierung passiert an einer Stelle
(`ITHilbert\ConsentLaw\Data\Inhaltsformat::ausApi()`); die Properties `format` und
`descriptionFormat` liefern danach immer `markdown` oder `html`.

**Zeilenumbrüche:** consentLaw pflegt Texte zeilenweise (z. B. Adressblöcke). Deshalb
gilt bei Markdown ein einfacher Zeilenumbruch (`\n` oder `\r\n`) als Zeilenumbruch und
wird als `<br />` ausgegeben – nicht als Leerzeichen wie im CommonMark-Standard. Eine
Leerzeile trennt weiterhin Absätze (`<p>`); Listen, Überschriften, Tabellen und
Code-Blöcke bleiben unverändert. Zwei Leerzeichen oder ein Backslash am Zeilenende sowie
ein `<br>` vor dem Zeilenende ergeben ebenfalls genau **ein** `<br />`. Das gilt an allen
Ausgabestellen (Dokumente, Job-Beschreibungen, JSON-LD). Inhalte mit `format: html`
sind nicht betroffen.

## Sicherheit der Markdown-Ausgabe

Markdown aus consentLaw wird an **einer** Stelle gerendert
(`ITHilbert\ConsentLaw\Support\MarkdownRenderer::render()`), genutzt von allen
Komponenten, den Fertig-Seiten und dem JSON-LD der Jobs. Der Renderer filtert:

- Rohes HTML (`<script>`, `<iframe>`, `<img onerror=…>`, Event-Handler) wird **entfernt**,
  nicht escaped.
- Unsichere Link-Ziele (`javascript:`, `data:`, `vbscript:`) werden entfernt.
- `<br>`, `<br/>` und `<br />` bleiben als Zeilenumbruch erhalten; einfache Zeilenumbrüche
  werden ebenfalls zu `<br />` (siehe „Format der Inhalte“).
- GitHub-Flavored Markdown (Tabellen, Durchgestrichenes, Autolinks) wird unterstützt.

Wer Markdown in eigenem Markup ausgibt, nutzt denselben Renderer statt `Str::markdown()`.
Inhalte mit `format: html` werden **nicht** durch den Renderer geschickt – consentLaw
filtert HTML beim Speichern.

## Sicherheit des Tokens

Der Domain-Token steht im URL-Pfad der API (`api/impressum/<token>`) und ist ein
Geheimnis. Er erscheint **nie** im Klartext in Exception-Meldungen, Log-Einträgen oder
der Ausgabe von `consentlaw:warm`. Die Maskierung passiert an einer Stelle
(`ITHilbert\ConsentLaw\Support\TokenMaskierung`, genutzt vom `HttpTransport`) und gilt
auch für Meldungen fremder Bibliotheken (Guzzle/cURL nennen die vollständige URL):

- URLs erscheinen mit maskiertem Token, z. B. `…/api/impressum/abcd…` – höchstens die
  ersten 4 Zeichen bleiben sichtbar.
- Tokens mit bis zu 4 Zeichen werden vollständig maskiert (`***`).
- Die URL ohne Token bleibt zur Diagnose in der Meldung.

Wer eine Logzeile mit Token aus einer älteren Version findet: Token in consentLaw
rotieren (Aufgabe des Betreibers) und alte Logs bereinigen.

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

## Changelog

### Unveröffentlicht

- **Breaking:** Die ENV-Variable `CONSENTLAW_REFRESH_BUTTON` entfällt. Der „Neu laden“-Button
  folgt dem zentralen Hilfen-Schalter `APP_TEST` (Config `refresh_button`). Der Schalter wirkt
  jetzt auch auf Production; die bisherige Sperre für `production` entfällt. Bei veröffentlichter
  Config `'refresh_button' => env('APP_TEST', false)` übernehmen, in der `.env`
  `CONSENTLAW_REFRESH_BUTTON` durch `APP_TEST` ersetzen.
- Der Button steht jetzt **vor** dem Inhalt bzw. Slot (auch bei Störung ohne Fallback) und bringt
  ein eigenes, überschreibbares Stylesheet (`.consentlaw-refresh`) mit. Bei veröffentlichten Views
  das Partial `partials/neu-laden.blade.php` neu übernehmen und das `@include` in eigenen Kopien
  von `document` und `jobs` an den Anfang setzen.
- `NeuLadenFreigabe` hängt nicht mehr von `Application` ab.

## Upgrade auf 1.2

- Neu: „Neu laden“-Button (siehe „Neu laden per Button“). Er ist standardmäßig **aus** –
  bestehende Installationen ändern sich nicht.
- Neuer Config-Schlüssel `refresh_button` (ENV `APP_TEST`, Standard
  `false`). Bei veröffentlichter Config (`vendor:publish`) den Schlüssel ergänzen.
- Bei veröffentlichten Views (`consentlaw-views`) die neue Teilansicht
  `components/partials/neu-laden.blade.php` übernehmen und in eigenen Kopien von
  `components/document.blade.php` und `components/jobs.blade.php` am Anfang einbinden:
  `@include('consentlaw::components.partials.neu-laden', ['typ' => $type, 'locale' => $locale])` (Jobs: `['typ' => 'jobs', 'locale' => $locale]`). Ohne `locale` lädt der Button bei Komponenten mit Sprachangabe die falsche Variante.
- Die Route nutzt die Middleware-Gruppe `web` und den Alias `throttle`; beides bringt jede
  Laravel-App mit.

## Sicherheits-Update 1.1.1

Verlangt `league/commonmark` ab 2.10.2. Ältere Versionen haben einen DoS in der GFM-Tabellen-Erweiterung ([GHSA-3q6v-r5mr-hxv8](https://github.com/advisories/GHSA-3q6v-r5mr-hxv8)) und einen Bypass von `DisallowedRawHtml` ([GHSA-97jj-33gv-5xf9](https://github.com/advisories/GHSA-97jj-33gv-5xf9)). Nach dem Update im Kundenprojekt: `composer update ithilbert/consentlaw-client league/commonmark`.

## Upgrade auf 1.1

- **Verhaltensänderung:** Ein einfacher Zeilenumbruch im Markdown wird jetzt als
  `<br />` ausgegeben (vorher: Leerzeichen, Text lief in einer Zeile zusammen). Texte, die
  über mehrere Zeilen umbrochen im Markdown stehen (Fließtext mit fester
  Zeilenbreite), erscheinen dadurch mit Zeilenumbrüchen – Absätze gehören in consentLaw
  durch Leerzeilen getrennt. Betroffen sind Dokumente, Job-Beschreibungen und JSON-LD.
  Eigene, veröffentlichte Views müssen nicht angepasst werden.
- Der Token wird in Exception-Meldungen und Logs maskiert (siehe „Sicherheit des Tokens“).
  Code, der den Token aus Meldungen herausliest, funktioniert nicht mehr.

## Upgrade auf 1.0

Änderungen, die bestehenden Code betreffen können:

- `ContentResolver::resolve()` liefert jetzt `ITHilbert\ConsentLaw\Data\Abruf` (Status,
  Payload, Abrufzeitpunkt) statt `?array`. Nur relevant, wenn der Resolver direkt
  verwendet oder ersetzt wird; die Endpoints (`get()`, `all()` …) sind unverändert und
  bieten zusätzlich `abrufen()`.
- Die Standard-URL ist `https://www.consentlaw.eu` (vorher `https://consentlaw.eu`, das
  per 301 weiterleitete). Eine eigene `CONSENTLAW_URL` bleibt unberührt.
- Die Formaterkennung am Inhalt entfällt: Ohne `format` in der API-Antwort gilt `markdown`.
- Neuer Config-Schlüssel `fallback.retry_after` (ENV `CONSENTLAW_FALLBACK_RETRY_AFTER`,
  Standard `300`): Wartezeit nach einer Störung. Bei veröffentlichter Config
  (`vendor:publish`) den Schlüssel ergänzen.
- Nur ein 404 gilt als „nicht veröffentlicht“ (wird gecacht, nicht wiederholt); jeder
  andere Fehlerstatus gilt als Störung und liefert den Fallback.
- Markdown wird gefiltert gerendert (`MarkdownRenderer`), rohes HTML und
  `javascript:`-Links werden entfernt.
- `consentlaw:warm` endet bei „nicht veröffentlicht“ mit Exit-Code 0, außer alle Typen
  sind 404 (Token prüfen).

## Lizenz

MIT – IT-Hilbert GmbH
