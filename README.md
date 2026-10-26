# consentLaw Client

Laravel-Client für die [consentLaw](https://www.consentlaw.eu)-API. Holt Rechtstexte
(Impressum, Datenschutz, AGB, Cookie-Richtlinie, AVV) und Stellenangebote in ein
beliebiges Laravel-Projekt – mit Cache und ausfallsicherem Fallback.

## Installation

Störungsmeldungen laufen über das Basis-Package `ithilbert/alerts` (auf Packagist, das Paket
verlangt `^1.0`). Ein eigener `repositories`-Eintrag ist nicht nötig. Installieren:

```bash
composer require ithilbert/consentlaw-client
```

`ithilbert/alerts` kommt dadurch als Abhängigkeit mit. Kanäle und Empfänger der
Störungsmeldungen stellt man in alerts über `ALERTS_*` ein (siehe dessen README).

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

Eigenes Markup für Jobs: siehe Abschnitt „Eigenes Markup“ weiter unten.

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

### Consent-Banner (optional)

Der Consent-Banner von consentLaw ist eine Zeile im Layout, im `<head>`:

```blade
<head>
    {{-- ... --}}
    <x-consentlaw::banner />
</head>
```

Die Komponente lädt `banner.js` von consentLaw (`{CONSENTLAW_URL}/banner.js?token=…`, mit
`defer`) und verwendet dafür den vorhandenen `CONSENTLAW_TOKEN`. Ohne Token bleibt die
Ausgabe leer; es findet auch keine API-Abfrage statt.

Ein Link, der den Banner erneut öffnet, z. B. im Footer:

```blade
<footer>
    <x-consentlaw::cookie-einstellungen class="text-light" />

    {{-- mit eigenem Text --}}
    <x-consentlaw::cookie-einstellungen class="text-light">
        Cookie-Einstellungen ändern
    </x-consentlaw::cookie-einstellungen>
</footer>
```

Ist der Banner nicht aktiv, bleibt die Ausgabe des Links ebenfalls leer.

Mit dem Schalter `CONSENTLAW_BANNER_ENABLED` schaltet man beide Komponenten ab, z. B.
solange noch ein anderes Consent-Tool läuft. Der Standard ist eingeschaltet; der Wert
steht in der Konfiguration unter `consentlaw.banner.enabled`.

```dotenv
CONSENTLAW_BANNER_ENABLED=false
```

**Hinweis:** Der Link nutzt einen Inline-Klick-Handler. Bei einer Content-Security-Policy
ohne `unsafe-inline` wirkt er nicht.

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
`forget()`, `refresh()`, `consentlaw:load` und `consentlaw:flush` verwerfen den Vermerk.

### Wartezeit nach einer Störung

Nach einer Störung wird die API für `CONSENTLAW_FALLBACK_RETRY_AFTER` Sekunden
(Standard `300`) **nicht erneut gefragt**, sondern direkt der Fallback ausgeliefert –
auch wenn keiner existiert (dann direkt nichts). Ohne diese Wartezeit würde nach jeder
abgelaufenen Cache-TTL jeder Seitenaufruf erneut auf den API-Timeout warten, und die
Homepage wäre bei einem Ausfall von consentLaw langsam. Nach Ablauf wird die API wieder
gefragt. Die Wartezeit gilt je Inhalt, liegt im Cache-Store und ist von
`CONSENTLAW_CACHE_TTL`/`cache.enabled` unabhängig; `0` schaltet sie ab.
`consentlaw:load` und `consentlaw:flush` heben sie auf.

Fallback-Dateien liegen auf der konfigurierten Disk unter `consentlaw/` (beim
Standard-Disk `local` ist das `storage/app/private/consentlaw/`) und enthalten den
Token-Hash im Namen – nach einem Token-Wechsel werden keine fremden Inhalte
wiederverwendet.

### Nach dem Deploy neu laden

Der `storage/`-Ordner ist üblicherweise nicht im Git. Damit direkt nach einem Deploy ein
Fallback-Stand bereitliegt:

```bash
php artisan consentlaw:load
php artisan consentlaw:flush   # Cache + Fallback verwerfen
```

`consentlaw:load` meldet einen nicht veröffentlichten Typ (404, z. B. ein Kunde ohne
AGB) nur als Hinweis und endet mit Exit-Code 0. Exit-Code 1 gibt es nur ohne Token oder
wenn ein Typ wegen einer **Störung** weder von der API noch aus dem Fallback geliefert
werden konnte – so scheitern Deploy-Skripte nur bei echten Problemen.

Antworten **alle** Typen (5 Dokumente und Jobs) mit 404, ist fast sicher der Token
ungültig: Dann endet der Befehl mit Exit-Code 1 und dem Hinweis, den Token zu prüfen.

## Störungsmeldung über alerts

Fehlende oder veraltete Rechtstexte sind abmahnfähig – ein Log liest niemand. Das Package
meldet deshalb drei Störungsfälle als **Event** (Namespace `ITHilbert\ConsentLaw\Events`):

| Event | Wann | Properties |
|---|---|---|
| `RechtstextNichtLieferbar` | API gestört (kein 404) und kein Fallback vorhanden bzw. Fallback abgeschaltet – der Inhalt fehlt auf der Seite | `typ`, `locale`, `fehler` |
| `NotstandVeraltet` | API gestört, ausgeliefert wird ein Fallback, der älter ist als `CONSENTLAW_FALLBACK_WARN_AFTER` (Standard 7 Tage). Der Inhalt bleibt sichtbar | `typ`, `locale`, `alterInSekunden`, `fehler` |
| `TokenVermutlichUngueltig` | `consentlaw:load`: **alle** Typen antworten mit 404 (Exit-Code und Ausgabe wie bisher) | – |

`RechtstextNichtLieferbar` und `NotstandVeraltet` entstehen beim Seitenaufruf, beim
„Neu laden“-Button und bei `consentlaw:load` – aber nur, wenn wirklich abgerufen wurde:
Während der Wartezeit nach einer Störung (`CONSENTLAW_FALLBACK_RETRY_AFTER`) wird nicht
abgerufen und deshalb auch nichts gemeldet. Ein einzelnes 404 („nicht veröffentlicht“,
z. B. ein Kunde ohne AGB) ist keine Störung. `fehler` enthält den Token nur maskiert.

Die Meldung übernimmt das Basis-Package `ithilbert/alerts`. Der Listener `MeldeStoerungAnAlerts`
ruft dafür den Port `AlertReporter` auf, genau einmal je Event. Der Listener ist immer aktiv.
Eine eigene Mail-Konfiguration gibt es im Package nicht mehr.

| Event | Schlüssel in alerts | Titel der Meldung |
|---|---|---|
| `RechtstextNichtLieferbar` | `consentlaw.nicht-lieferbar` | Rechtstext nicht lieferbar |
| `NotstandVeraltet` | `consentlaw.notstand-veraltet` | Veralteter Notstand |
| `TokenVermutlichUngueltig` | `consentlaw.token-vermutlich-ungueltig` | Token vermutlich ungültig |

- **Schlüssel:** Je Fall ein fester Schlüssel, unabhängig vom Typ. Die Drosselung von alerts
  gilt deshalb pro Fall. Weitere Typen mit demselben Fall erzeugen keine eigene Meldung,
  sondern zählen in der Sammelmeldung.
- **Inhalt:** Website (`APP_URL`), Typ und, falls gesetzt, Sprache. Bei `NotstandVeraltet`
  das Alter in Tagen (ab einem Tag; darunter in Stunden). Dazu kommen „Was ist passiert“
  und „Was ist zu tun“. Der Fehlertext (Token maskiert) steht bei `RechtstextNichtLieferbar`
  und `NotstandVeraltet`. `TokenVermutlichUngueltig` hat weder Typ, Sprache noch Fehlertext.
- **Freigabe:** `php artisan alerts:reset consentlaw.nicht-lieferbar` hebt die Sperre dieses
  Falls auf. Das nächste Auftreten wird sofort gemeldet. `php artisan alerts:reset --all`
  hebt alle Sperren auf, auch die anderer Packages.
- **Kanäle, Empfänger, Drosselung:** Stellt man in alerts über `ALERTS_*` ein, z. B.
  `ALERTS_MAIL_TO`, `ALERTS_MAILER`, `ALERTS_CHANNELS` und `ALERTS_SPERRE_STUNDEN`. Die
  vollständige Liste steht in der README von `ithilbert/alerts`.
- **Fallstrick:** Ein Kanal ohne vollständige Konfiguration wird von alerts still übersprungen.
  Ohne `ALERTS_MAIL_TO` geht also keine Mail raus, und es erscheint keine Fehlermeldung.
- **Fehlschlag:** Wirft der Port eine Exception, wird das als Warnung geloggt
  (`[consentlaw] Störungsmeldung an alerts fehlgeschlagen.`, Token maskiert). Seitenaufruf,
  Fallback und Exit-Code von `consentlaw:load` bleiben unverändert.
- **Abschalten:** Meldungen lassen sich in alerts abschalten, z. B. mit `ALERTS_ENABLED=false`.
  Das wirkt auf alle Meldungen des Hosts. Die Events werden dabei weiter ausgelöst.

Die Events bleiben unverändert und lassen sich zusätzlich abonnieren:

```php
Event::listen(\ITHilbert\ConsentLaw\Events\RechtstextNichtLieferbar::class, function ($event) {
    // $event->typ, $event->locale, $event->fehler
});
```

## Konfiguration

| ENV | Standard | Bedeutung |
|---|---|---|
| `CONSENTLAW_URL` | `https://www.consentlaw.eu` | Basis-URL der Instanz |
| `CONSENTLAW_TOKEN` | – | Domain-Token (**Pflicht**) |
| `CONSENTLAW_CACHE_TTL` | `3600` | Frische in Sekunden, `0` = Cache aus |
| `CONSENTLAW_CACHE_STORE` | Standard-Store | z.B. `redis` |
| `CONSENTLAW_TIMEOUT` | `5` | HTTP-Timeout in Sekunden |
| `CONSENTLAW_FALLBACK_DISK` | `local` | Disk für die Fallback-Dateien |
| `CONSENTLAW_FALLBACK_WARN_AFTER` | `604800` | Log-Warnung und Störungsmeldung `NotstandVeraltet` ab diesem Alter (Sekunden) |
| `CONSENTLAW_FALLBACK_RETRY_AFTER` | `300` | Wartezeit nach einer Störung, in der die API nicht erneut gefragt wird (Sekunden, `0` = aus) |
| `CONSENTLAW_ROUTES_ENABLED` | `false` | Fertige Seiten registrieren |
| `APP_TEST` | `false` | Zentraler Hilfen-Schalter: „Neu laden“-Button (wirkt in jeder Umgebung, auch Production) |
| `CONSENTLAW_LOCALE` | App-Locale | Sprache der Inhalte |
| `CONSENTLAW_BANNER_ENABLED` | `true` | Consent-Banner und Link „Cookie-Einstellungen“ (siehe „Consent-Banner“) |

Kanäle, Empfänger und Drosselung der Störungsmeldungen stehen in den `ALERTS_*`-Variablen
von `ithilbert/alerts`.

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

- **Standard aus:** Die Route (`POST consentlaw/neu-laden/{typ}`, Name `consentlaw.neu-laden`)
  und das Limit sind immer registriert; ohne den Schalter antwortet die Aktion mit 404, ohne
  die API aufzurufen, und es erscheint kein Button.
- **Rückleitung:** Der Button sendet die aktuelle Adresse (Pfad und Query) im versteckten Feld
  `zurueck` mit; die Rückleitung funktioniert damit auch mit `Referrer-Policy: no-referrer`.
  Akzeptiert wird nur ein lokaler Pfad; ein ungültiger Wert verhindert das Neu laden nicht,
  die Rückleitung nutzt dann den Referer des eigenen Hosts bzw. die Startseite.
- **Alternative ohne Button:** `php artisan consentlaw:load`.
- **CSRF-Hinweis:** Homepages, deren `web`-Gruppe weder `StartSession` noch `VerifyCsrfToken`
  enthält, haben für diese Route keinen CSRF-Schutz. Solange `APP_TEST` an ist, kann dann eine
  fremde Seite das Neu laden auslösen (begrenzt auf 6 Anfragen pro Minute und IP). Den Schalter
  deshalb nur kurz eingeschaltet lassen.
- **Limit:** höchstens 6 Anfragen pro Minute und IP (sonst 429); der Aufruf braucht ein
  gültiges CSRF-Token (Middleware-Gruppe `web`, siehe CSRF-Hinweis). Die Rückleitung geht nur auf die eigene
  Domain. Hinter einem Reverse Proxy ohne konfigurierte Trusted Proxies teilen sich alle
  Besucher eine IP und damit das Limit.
- **Rückmeldung:** Hat die Seite eine Session (Gruppe `web`), zeigt die Komponente nach dem Neu laden
  einmalig eine Meldung (`role="status"`, Klassen `consentlaw-refresh-meldung` und `--ok` bzw. `--fehler`):
  Erfolg mit Stand, Störung mit gespeichertem Stand, Störung ohne Stand, „nicht veröffentlicht oder Token
  ungültig“ oder „kein Token konfiguriert“. Die Meldung erscheint nur bei der Komponente (Typ und Sprache),
  deren Button ausgelöst wurde, und enthält nie Token, URLs oder Exception-Texte. Ohne Session funktioniert
  das Neu laden wie bisher, nur ohne Meldung.
- **Ohne Token:** Fehlt `CONSENTLAW_TOKEN`, rendern die Komponenten (`document`, `impressum` …, `jobs`)
  ohne API-Aufruf wie bei „kein Inhalt verfügbar“ (leer bzw. Slot) und schreiben eine Warnung ins Log;
  die Seite antwortet mit 200. Die PHP-API (`ConsentLaw::document()`, `jobs()`) wirft weiterhin
  `MissingTokenException`. Die optionalen Package-Seiten verhalten sich ebenso: Dokument-Seite 404
  (wie fehlender Inhalt), Jobs-Seite 200 mit leerer Liste.
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

## Eigenes Markup

Wer Stellenangebote im eigenen Layout ausgibt (z. B. als Aufklapp-Menü) und nicht
`<x-consentlaw::jobs />` nutzt, setzt drei Bausteine zusammen:

```blade
@php
    // Daten: wirft ohne CONSENTLAW_TOKEN nicht (leere Liste + Warnung im Log)
    $jobs = app(\ITHilbert\ConsentLaw\Support\KomponentenInhalt::class)->jobs(null, false);
@endphp

{{-- "Neu laden"-Button (erscheint nur bei APP_TEST=true) --}}
@include('consentlaw::components.partials.neu-laden', ['typ' => 'jobs', 'locale' => null])

@foreach($jobs as $job)
    <details>
        <summary>{{ $job->title }}</summary>
        {!! $job->descriptionHtml() !!}
    </details>
@endforeach

{{-- JSON-LD für Google for Jobs (je Stelle ein <script>, sonst keine Ausgabe) --}}
<x-consentlaw::jobs-schema :jobs="$jobs" />
```

- **Daten:** `KomponentenInhalt::jobs()` statt `ConsentLaw::jobs()`: Letzteres wirft ohne Token eine
  `MissingTokenException`.
- **Beschreibung:** `$job->descriptionHtml()` liefert ausgabefertiges HTML (Markdown gerendert, HTML unverändert) – keine eigene Fallunterscheidung nötig.
- **JSON-LD:** `<x-consentlaw::jobs-schema>` baut das Schema.org-`JobPosting`; `<x-consentlaw::jobs />` nutzt dieselbe Komponente.

### Felder je Stelle für Google for Jobs

Zusätzlich zu den bisherigen Feldern liefert die API je Stelle (alle optional, fehlende oder
falsch typisierte Werte ergeben die Standardwerte):

| API-Feld | `Job`-Eigenschaft | Standard | JSON-LD |
|---|---|---|---|
| `hiring_organization` (`name`, `url`, `logo`) | `hiringOrganization` (`?HiringOrganization`) | `null` | `hiringOrganization` (`sameAs`/`logo` nur bei absoluter http(s)-URL) |
| `remote` (bool) | `remote` | `false` | `jobLocationType: TELECOMMUTE` |
| `applicant_countries` (ISO-3166-1 alpha-2) | `applicantCountries` | `[]` | `applicantLocationRequirements` |
| `work_location` (`remote` \| `vor_ort`) | `workLocation` (`?string`) | `null` | `remote` → `jobLocationType: TELECOMMUTE` und kein `jobLocation`; `vor_ort` → weder `jobLocationType` noch `applicantLocationRequirements`; ohne Angabe gilt `remote` |
| `address` (`street_address`, `postal_code`, `address_locality`, `address_country`) | `address` (`?JobAddress`) | `null` | `jobLocation` (Place + PostalAddress, leere Teile entfallen; ohne `address` der Freitext `location`) |
| `base_salary` (`currency`, `unit`, `value` oder `min_value`/`max_value`) | `baseSalary` (`?BaseSalary`) | `null` | `baseSalary` (MonetaryAmount mit QuantitativeValue; Einheit `HOUR`, `DAY`, `WEEK`, `MONTH` oder `YEAR`) |

Ein Arbeitgeber ohne Namen gilt als fehlend; Ländercodes werden großgeschrieben, ungültige und doppelte entfallen. Ein ungültiges Gehalt (unbekannte Einheit, Währung kein 3-Buchstaben-Code, kein oder negativer Betrag) und eine unbrauchbare Adresse gelten als fehlend. Das Beispiel oben gibt Remote- und Vor-Ort-Stellen ohne weitere Logik korrekt aus: `<x-consentlaw::jobs-schema>` übernimmt Ort, Remote-Angaben und Gehalt.

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
der Ausgabe von `consentlaw:load`. Die Maskierung passiert an einer Stelle
(`ITHilbert\ConsentLaw\Support\TokenMaskierung`, genutzt vom `HttpTransport` und von der
Störungsmeldung an alerts) und gilt auch für Meldungen fremder Bibliotheken (Guzzle/cURL nennen die vollständige URL):

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

### 1.0.1

- `ithilbert/alerts` jetzt `^1.0` statt `dev-main` (1.0.0 verlangte `dev-main`; Projekte konnten alerts dadurch nicht auf `^1.0` stellen). Kein `repositories`-Eintrag für alerts mehr nötig.

### 1.0.0

- **Neu:** Consent-Banner als Komponenten `<x-consentlaw::banner />` (lädt `banner.js` von consentLaw mit dem Token) und `<x-consentlaw::cookie-einstellungen />` (Link, der den Banner erneut öffnet) sowie der Schalter `CONSENTLAW_BANNER_ENABLED` (Config `consentlaw.banner.enabled`, Standard an). Eine veröffentlichte Config muss dafür nicht geändert werden.
- **Neu:** Arbeitsort, Adresse und Gehalt je Stelle: `Job::$workLocation`, `$address` (`JobAddress`), `$baseSalary` (`BaseSalary`); das JSON-LD enthält `jobLocation` als PostalAddress, bei `remote` nur TELECOMMUTE, und `baseSalary`.
- **Neu:** Jobs für Google for Jobs: `Job::$hiringOrganization`, `$remote`, `$applicantCountries` und die Komponente `<x-consentlaw::jobs-schema>` (siehe „Eigenes Markup“). Das JSON-LD wird in PHP gebaut; zuvor zerstörte die `@context`-Blade-Direktive es.
- **Fix:** Die Rückleitung des „Neu laden“-Buttons funktioniert auch ohne Referer (z. B.
  `Referrer-Policy: no-referrer`): Das Formular sendet die aktuelle Adresse im Feld `zurueck`,
  ausgewertet wird nur ein lokaler Pfad.
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
- Route und Limiter `consentlaw-neu-laden` sind jetzt immer registriert; die Freigabe prüft nur
  noch die Aktion (ohne Schalter 404 ohne API-Aufruf) und das Partial (Button). Damit ist es
  egal, ob die Routen gecacht sind (`route:cache`). Bei gecachter Config nach einer Änderung an
  `APP_TEST` `php artisan config:clear` ausführen.
- Neu: Störungsmeldung (siehe „Störungsmeldung über alerts“). Events `RechtstextNichtLieferbar`,
  `NotstandVeraltet` und `TokenVermutlichUngueltig`; ein mitgelieferter Listener schickt dazu
  eine Mail (höchstens eine je Fall und 24 h). Neue ENV-Variablen `CONSENTLAW_ALERT_MAIL`,
  `CONSENTLAW_ALERT_MAILER`, `CONSENTLAW_ALERT_LISTENER`. Ohne `CONSENTLAW_ALERT_MAIL` geht keine
  Mail raus. Bei veröffentlichter Config den Block `alerts` ergänzen (sonst gelten die
  Standardwerte); bei veröffentlichten Views kommt `mail/stoerung.blade.php` neu hinzu.
- `ContentResolver::resolve()` erwartet zusätzlich `typ` und `locale` (für die Störungsmeldung),
  der Konstruktor zusätzlich den Event-Dispatcher. Betrifft nur, wer den Resolver direkt nutzt.
- **Breaking:** Die Störungsmeldung läuft über das Basis-Package `ithilbert/alerts` (Port
  `AlertReporter`). Die eigene Mail-Infrastruktur des Packages entfällt. Umstellung:
  - `.env`: `CONSENTLAW_ALERT_MAIL` → `ALERTS_MAIL_TO`, `CONSENTLAW_ALERT_MAILER` → `ALERTS_MAILER`.
    Die Werte bleiben gleich.
  - `CONSENTLAW_ALERT_LISTENER` entfällt ersatzlos. Der Listener ist immer aktiv. Meldungen
    lassen sich in alerts abschalten, z. B. mit `ALERTS_ENABLED=false`.
  - Die Schlüssel der Meldungen lauten `consentlaw.nicht-lieferbar`, `consentlaw.notstand-veraltet`
    und `consentlaw.token-vermutlich-ungueltig`. Freigabe: `php artisan alerts:reset <schlüssel>`.
  - `composer update ithilbert/consentlaw-client ithilbert/alerts` (alerts kommt von Packagist, ab consentlaw-client 1.0.1 als `^1.0`).
  - Bei veröffentlichter Config (`config/consentlaw.php`) den Block `alerts` löschen.
  - Die veröffentlichte Vorlage `resources/views/vendor/consentlaw/mail/stoerung.blade.php` wird
    nicht mehr genutzt und kann gelöscht werden.
  - Eine eigene 24-h-Sperre gibt es nicht mehr. Die Drosselung macht alerts
    (`ALERTS_SPERRE_STUNDEN`, Standard 24).

## Upgrade auf 1.2

- Neu: „Neu laden“-Button für Entwicklung und Test (siehe „Neu laden per Button
  (Entwicklung)“). Er ist standardmäßig **aus** und auf Production nie aktiv – bestehende
  Installationen ändern sich nicht.
- Neuer Config-Schlüssel `refresh_button` (ENV `CONSENTLAW_REFRESH_BUTTON`, Standard
  `false`). Bei veröffentlichter Config (`vendor:publish`) den Schlüssel ergänzen.
- Bei veröffentlichten Views (`consentlaw-views`) die neue Teilansicht
  `components/partials/neu-laden.blade.php` übernehmen und in eigenen Kopien von
  `components/document.blade.php` und `components/jobs.blade.php` am Ende einbinden:
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
- `consentlaw:load` endet bei „nicht veröffentlicht“ mit Exit-Code 0, außer alle Typen
  sind 404 (Token prüfen).

## Lizenz

MIT – IT-Hilbert GmbH
