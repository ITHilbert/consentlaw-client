# Prüfung: load-command — 2026-10-21

Branch `feature/load-command` (Commits 7c529a4, 52a5763, adc2e93, bd81995) gegen `main`.

| AK | Ergebnis | Nachweis |
|---|---|---|
| AK1 — Laden | bestanden | `../../vendor/bin/phpunit` grün (733 Tests, 3382 Assertions), darin `LoadCommandTest`, `LoadTokenHinweisTest`, `LoadCommandRegistrierungTest` (echter Provider, Option `--locale`), `TokenSchutzTest`, `StoerungsEventsTest`, `StoerungsAlertsTest`, `NichtVeroeffentlichtCacheTest`. **Echter Lauf** in einer minimalen Laravel-12-App im Scratchpad (Workspace-vendor, Package-`src` per Prepend-Autoloader, Token per Prozess-ENV aus it-hilbert.com, keine `.env` kopiert, `AlertReporter` durch einen protokollierenden Fake ersetzt). Gegen die echte consentLaw-API verglichen mit `consentlaw:reload` aus `main` (`git archive main`, gleiche Harness). Fünf Szenarien, Konsolenausgabe und Exit-Code jeweils **byte-identisch**: gültiger Token → 5 Dokumente + 6 Stellenangebote, Exit 0, 6 Fallback-Dateien; `--locale=en` → Exit 0, `.en`-Fallback-Dateien; ohne Token → „Kein CONSENTLAW_TOKEN gesetzt“, Exit 1; ungültiger Token → 6 × „nicht veröffentlicht“ + Token-Hinweis, Exit 1, genau eine Meldung `consentlaw.token-vermutlich-ungueltig`; Störung (`http://127.0.0.1:1`) ohne Fallback → 6 Störungen, Exit 1, 6 Meldungen `consentlaw.nicht-lieferbar`. Die Meldungen unterscheiden sich von `main` nur im Commandnamen. Folge gut → Störung → gut: Lauf 2 „aus Fallback – API nicht erreichbar!“, Exit 0; Lauf 3 lädt sofort frisch, obwohl die Wartezeit (300 s) noch läuft, d. h. der Sperrvermerk wird verworfen. `laravel.log`: nur INFO „Fallback genutzt“; der Token kommt weder in der Ausgabe noch im Log noch in den Meldungen vor. Command-Diff: nur Klassenname und Signatur, `handle()` und `$description` unverändert (Similarity 96 %). |
| AK2 — Ein Name | bestanden | `LoadCommandNameTest` grün (Stoerungsfall, ganz `src/`, README). Eigener Check: `grep -rni reload` bzw. `warm` über das Package (ohne `.git`, ohne `.project/features/`) findet `consentlaw:reload`/`consentlaw:warm` nirgends in `src/` oder README. In den Tests kommen die alten Namen nur noch zusammengesetzt in `LoadCommandNameTest::alteNamen()` vor; dazu stehen einige Testmethodennamen `test_reload_…` (das ist nicht der Commandname). Meldungstexte im echten Lauf nennen „php artisan consentlaw:load“. Changelog „Unveröffentlicht“ (README Z. 442–485): kein Eintrag zur Umbenennung; der frühere Breaking-Eintrag `warm → reload` wurde entfernt. `php artisan list consentlaw` zeigt nur `flush` und `load`; `consentlaw:reload` → „is not defined“, Exit 1. |

## Nicht-Ziele
- Verhalten unverändert (siehe AK1, byte-identische Ausgaben).
- `NeuLadenController` und `refresh()` sind im Diff nicht angefasst; in der README ändert sich nur die Aufzählung `forget()`, `refresh()`, `consentlaw:load` …
- Abgeschlossene Feature-Dokumente unter `.project/features/` sind unverändert. Geändert sind nur `index.md`, `load-command/` und `.project/notizen/optimierungen.md`.

## Sicherheit
- Es gibt keine neue Angriffsfläche (reine Umbenennung, keine Route, keine Eingabe).
- Token-Schutz: `TokenSchutzTest` (load-Ausgabe, kurze Tokens) ist grün. Im echten Lauf kommt der Token in Ausgabe, `laravel.log` und Meldungen 0-mal vor.

## Regressionen
- Volle Suite auf dem Branch: 733/733 grün. Dieselbe Suite auf `main` (Kopie im Scratchpad): 732/732. Die Differenz +1 ergibt sich so: die drei Tests aus `ReloadCommandRegistrierungTest` (davon zwei zu `warm`) fallen weg, dazu kommen 1 Test in `LoadCommandRegistrierungTest` und 3 in `LoadCommandNameTest`. Die übrigen Tests sind nur umbenannt bzw. auf die neue Klasse umgestellt, ihre Assertions sind gleich.
- Pint (`--test`) meldet für `src/` und die geänderten Tests nichts. Er beanstandet nur `tests/NeuLadenRueckmeldungTest.php`; die Datei ist im Branch nicht geändert, das Problem besteht also schon vorher.
- PHPStan ist im Workspace-vendor nicht vorhanden und wurde deshalb nicht geprüft.

## Hinweise (außerhalb der Spec, keine Freigabe-Blocker)
1. **Konsument it-hilbert.com bricht beim nächsten `composer update`.** Das Projekt bindet `dev-main` ein. Betroffen sind:
   - `routes/console.php:23` mit `Schedule::command('consentlaw:reload')->hourly()`: Nach dem Merge läuft der stündliche Reload ins Leere („not defined“).
   - `tests/Feature/RechtstexteAusConsentLawTest.php:312–331`: prüft auf `consentlaw:reload` und wird rot.
   - README, `ToDo.md` und `decisions/0001-…` nennen ebenfalls den alten Namen.
   Die Umstellung gehört in ein Folge-Feature im Homepage-Projekt.
2. `packages_dev/resources/views/partials/consentlaw-test.blade.php` (Z. 204, 224) nennt noch `consentlaw:warm`. Das war schon vor diesem Feature veraltet.
3. README „Upgrade auf 1.0“ (Z. 533) nennt jetzt `consentlaw:load` statt `consentlaw:warm`. Damit ist der Text historisch nicht mehr korrekt, er entspricht aber dem Wortlaut von AK2 („README nennt nur `consentlaw:load`“). Da das Package keinen Tag hat (`git tag` ist leer), ist das vertretbar.
4. `.project/features/index.md` beschreibt das Feature als „`consentlaw:reload` → `consentlaw:load`“. Das liegt außerhalb von AK2 und ist nur ein Hinweis.

## Fazit: freigegeben
