# Prüfung: neu-laden-rueckmeldung — 2026-10-20

## Review 1 (AK1–AK11)

Geprüft: Branch `feature/neu-laden-rueckmeldung` gegen `main` (7 Commits, `git diff main...HEAD`).
Eigene Läufe: volle Suite, `tests/NeuLadenRueckmeldungTest.php` (testdox), zusätzliche Reviewer-Proben
(Wegwerf-Test im Session-Scratchpad, nicht im Repo), `pint --test src`.

| AK | Ergebnis | Nachweis |
|---|---|---|
| AK1 Erfolg | bestanden | Test simuliert Browser (Seite holen, Formular inkl. `_token`/`zurueck` ohne Referer absenden, Redirect folgen): 302 auf `http://localhost/impressum` bzw. `/jobs`, Meldung „Neu geladen – Stand: 16.10.2026 12:10“ nach `reise(600)` = Zeitpunkt des Abrufs, nicht des alten Stands; Zeitzonentest UTC 10:00 → „12:00“ (Europe/Berlin). Code: `stand` = `fetchedAt` des frischen Abrufs (`ContentResolver` setzt `now()` nach `forget()`). |
| AK2 Störung mit Fallback | bestanden | Dokument und Jobs, je Timeout und 503: Meldung nennt Stand des Fallbacks (16.10.2026 12:00), alter Inhalt bleibt sichtbar, Meldung oberhalb. Code: `Abrufstatus::Fallback` + `stand` aus `FallbackEntry::fetchedAt`. |
| AK3 Störung ohne Fallback | bestanden | 4 Datensätze (Dokument/Jobs × Timeout/5xx) grün, Text exakt laut Spec. `Abrufstatus::Stoerung` und sonstige `ConsentLawException` im Controller → `stoerung_ohne_stand`. |
| AK4 Nicht veröffentlicht | bestanden | 404 für Dokument und Jobs; zusätzlich 404 bei vorhandenem gespeicherten Stand → weiterhin „nicht veröffentlicht“ (Resolver liefert bei 404 bewusst keinen Fallback). |
| AK5 Kein Token | bestanden | Leerer Token → Meldung „kein Token konfiguriert (CONSENTLAW_TOKEN).“, 0 API-Aufrufe. Token-Wert `GEHEIM-TOKEN-4711` erscheint bei Timeout/5xx/404 nirgends im HTML, ebenso keine `cURL`/`timed out`/`/api/`-Texte. Session enthält nur feste Kennungen (`art`, `typ`, `locale`, ISO-Stand). |
| AK6 Einmalig | bestanden | Erfolgs- und Fehlermeldung: nach erneutem Seitenaufruf keine Meldung (Session-Flash). |
| AK7 Nur am betroffenen Inhalt | bestanden | Seite mit Jobs, Impressum (Standard), Impressum `en`, Datenschutz: jeweils genau eine Meldung, positioniert bei der ausgelösten Komponente (Typ + Sprache). Abgleich in `NeuLadenRueckmeldung::fuer()` über `typ` und normalisierte `locale`. |
| AK8 Darstellung | bestanden | `<style>`-Block im Partial mit `.consentlaw-refresh-meldung`, `--ok` (grün) und `--fehler` (rot), je eine Klasse Spezifität. Escaping **dynamisch geprüft** (Reviewer-Probe): gebundene Rückmeldung mit Text `<script>alert(1)</script>` → Ausgabe `&lt;script&gt;alert(1)&lt;/script&gt;`. Einschränkung: keine visuelle Prüfung im Browser (siehe unten). |
| AK9 Ohne Session | bestanden | `web`-Gruppe leer (weder StartSession noch CSRF): 302 auf `/impressum`, Abruf findet statt (2 API-Aufrufe), keine Meldung, kein Fehler, nichts in der Session. `ablegen()`/`fuer()` prüfen `hasSession()`. |
| AK10 Schalter aus | bestanden | Meldung liegt in der Session, Schalter danach aus → weder Button, Meldung noch Styles (`consentlaw-refresh` fehlt im HTML). Meldungsausgabe steht innerhalb `@if(NeuLadenFreigabe::aktiv())`. |
| AK11 Seite ohne Token | bestanden | `local` und `production`: `/impressum`, `/jobs`, `/gemischt` → 200, Button sichtbar, kein Inhalt, 0 API-Aufrufe; `<x-consentlaw::document>`/`<x-consentlaw::jobs>` zeigen den Slot; Log enthält `WARNING … CONSENTLAW_TOKEN fehlt`; Ende-zu-Ende auf `/jobs` ohne Token → Meldung aus AK5. Nicht-Ziel eingehalten: `ConsentLawClient::document()` wirft weiter `MissingTokenException`. |

### Randfälle
| Randfall | Ergebnis | Nachweis |
|---|---|---|
| Rate-Limit → 429, keine Meldung | bestanden | 6 Aufrufe 302 mit Meldung, 7. → 429, danach keine Meldung. |
| Keine Exception-Texte/URLs/Token in der Meldung | bestanden | Nur feste `__()`-Texte in `fuer()`; Tests prüfen Abwesenheit von Token, `cURL`, `/api/`. Unbekannte `art` in der Session → keine Meldung (Reviewer-Probe). |
| `consentlaw:reload` und Button nutzen dieselbe Stelle | bestanden | Beide rufen `DocumentEndpoint::neuLaden()` / `JobsEndpoint::neuLaden()` (forget + abrufen). Siehe aber Hinweis 1 zu `refresh()`. |

### Nicht-Ziele
Kein JavaScript/AJAX gebaut; kein dauerhafter Stand ohne Neu laden (Stale-Hinweis unverändert); PHP-API wirft ohne Token weiter; keine Störungsmail ohne Token; Texte über `__()`. Eingehalten.

## Sicherheit
- **Autorisierung/Freigabe:** unverändert — Controller prüft `NeuLadenFreigabe::aktiv()` auch in der Aktion, Typ gegen Whitelist (`DOCUMENT_TYPES` + `jobs`), Locale per Regex (`422` sonst), Throttle 6/min, CSRF über `web`.
- **Rückleitung:** unverändert nur auf eigenen Host (`istLokalerPfad`, Referer-Host-Vergleich).
- **Session-Inhalt:** nur feste Kennungen, validierte Locale, Typ aus Whitelist, ISO-Zeitstempel. Kein Token, keine Exception-Texte.
- **Ausgabe-Escaping:** `{{ }}`, dynamisch mit `<script>`-Text nachgewiesen.
- **Mass-Assignment/Mandantentrennung:** nicht betroffen (kein Model, keine DB).
- **Token im Log:** Warnung ohne Token „CONSENTLAW_TOKEN fehlt – Komponente bleibt leer.“ enthält keinen Wert (es gibt keinen).

## Regressionen
- Volle Suite: `../../vendor/bin/phpunit` → **OK (665 tests, 3101 assertions)**.
- Bestehende Testdateien unverändert (Diff enthält nur die neue `tests/NeuLadenRueckmeldungTest.php`).
- `pint --test src` → passed.
- `consentlaw:reload`: nur Umstellung auf `neuLaden()`, Verhalten identisch (forget + abrufen); bestehende Command-Tests grün.
- **Nicht ausgeführt:** PHPStan (im Workspace `packages_dev` nicht installiert, `vendor/bin/phpstan` fehlt).
- **Nicht ausgeführt:** echter Lauf in einer Host-App mit Browser-Konsole. Sowohl `it-hilbert.com` als auch `packages_dev` binden das Package als Kopie von `dev-main` in `vendor/` ein, nicht den Feature-Branch; ein Umbiegen von `vendor/` ist dem Reviewer nicht erlaubt. Die Tests laufen allerdings über den echten Router mit `web`-Middleware (Session, CSRF, Throttle) und echte Blade-Komponenten — damit ist der Ablauf serverseitig belegt; Darstellung im Browser und Browser-Logs sind **nicht geprüft**.

## Hinweise (nicht blockierend)
1. **Doppelter Ablauf „verwerfen + abrufen“:** `DocumentEndpoint::refresh()` und `JobsEndpoint::refresh()` bestehen weiter mit eigener Logik `forget()` + `get()/all()` neben dem neuen `neuLaden()`. Der Randfall (Button und Command an einer Stelle) ist erfüllt, die öffentliche API hat aber zwei Wege. Vorschlag: `refresh()` an `neuLaden()->inhalt` delegieren (Execution-Veto Doppelaufruf, Boy-Scout).
2. **Robustheit `formatiere()`:** Steht in der Session ein nicht parsebarer `stand` (z. B. nach Format-Änderung in einer späteren Version, Session überdauert Deploy), wirft `CarbonImmutable::parse()` und die ganze Seite bricht mit `ViewException` ab (Reviewer-Probe: `stand = 'kaputt'` → ViewException statt 200). Nur vom Package selbst geschriebene Daten, daher gering — ein `try/catch` mit „keine Meldung“ wäre defensiver.
3. **Fertig-Seiten ohne Token:** `ConsentLawPageController` (optionale Routen `consentlaw.routes.enabled`) ruft `document()`/`jobs()` direkt auf und antwortet ohne Token weiter mit 500. AK11 nennt nur die Komponenten, daher kein AK-Verstoß; möglicherweise eine Spec-Lücke — dem User vorlegen.
4. **Stil:** `KomponentenInhalt` ist ein statischer Helfer mit `app()`-Service-Locator, aus Blade per FQCN aufgerufen. Funktioniert, ist aber schwer austauschbar; eine injizierbare Klasse (wie `NeuLadenRueckmeldung`) wäre konsistenter.

## Fazit: freigegeben
Alle AKs 1–11 und die Randfälle sind durch eigene Läufe belegt; Sicherheit unauffällig; keine Regression in der vollen Suite. Offen und ehrlich nicht geprüft: PHPStan, visuelle Darstellung und Browser-Konsole in einer echten Host-App. Hinweise 1–4 sollten vom Moderator bewertet werden (1 und 3 dem User vorlegen).

---

# Review 2 — 2026-10-20

Geprüft: Branch `feature/neu-laden-rueckmeldung` gegen `main` (14 Commits, Stand `3065f09`), Schwerpunkt die Änderungen seit Review 1 (`407ff16..HEAD`: AK12, AK13, `refresh()`-Delegation, `KomponentenInhalt` injizierbar).
Eigene Läufe: volle Suite, `tests/NeuLadenRueckmeldungTest.php` (testdox, 48 Tests), `pint --test src`, Reviewer-Proben (Wegwerf-Test `Review2ProbeTest.php` im Session-Scratchpad, nicht im Repo; Package-Seiten unter Präfix `seiten`, Umgebung `production`).

| AK | Ergebnis | Nachweis |
|---|---|---|
| AK1 Erfolg | bestanden | Tests „Nach erfolgreichem neu laden …“ (Dokument, Jobs) und Zeitzonentest grün. Code-Pfad seit Review 1 unverändert (`neuLaden()` → `aus()` → Flash). |
| AK2 Störung mit Fallback | bestanden | 4 Datensätze grün (Dokument/Jobs × Timeout/5xx). Gültiger ISO-Stand wird weiter mit Datum ausgegeben (Probe: `2026-10-16T10:00:00+00:00` → „… Stand: 16.10.2026 10:00“). |
| AK3 Störung ohne Fallback | bestanden | 4 Datensätze grün. |
| AK4 Nicht veröffentlicht | bestanden | 404 Dokument/Jobs und „404 hat Vorrang vor gespeichertem Stand“ grün. |
| AK5 Kein Token | bestanden | Test ohne Token + 3 Datensätze „Token-Wert steht in keiner Meldung“ grün; Probe: Flash `kein_token` auf der Package-Jobs-Seite → feste Meldung, kein Wert. |
| AK6 Einmalig | bestanden | Beide Tests (Erfolg, Fehler) grün. |
| AK7 Nur am betroffenen Inhalt | bestanden | 3 Tests auf `/gemischt` grün (Typ + Sprache). |
| AK8 Darstellung | bestanden (ohne Sichtprüfung) | Style-Test und Escaping-Test grün; Partial seit Review 1 unverändert. Farbliche Unterscheidbarkeit nur über CSS-Werte belegt, nicht im Browser angesehen. |
| AK9 Ohne Session | bestanden | Test grün (`hasSession()`-Prüfung in `ablegen()`/`fuer()` unverändert). |
| AK10 Schalter aus | bestanden | Test grün. |
| AK11 Seite ohne Token | bestanden | 5 Tests grün (local/production, Slot-Fall, Log-Warnung, Ende-zu-Ende, PHP-API wirft weiter). Nach Umbau auf injizierte Klasse unverändertes Verhalten. |
| AK12 Fertige Seiten ohne Token | bestanden | Tests local/production grün: `/seiten/impressum` → 404, `/seiten/jobs` → 200, 0 API-Aufrufe, `WARNING … CONSENTLAW_TOKEN fehlt`. **Gegenprobe** (damit die 404 nicht bloß „Route fehlt“ ist): mit Token `/seiten/impressum` → 200 mit Inhalt, `/seiten/jobs` → 200 mit Stelle; ohne Token `/seiten/impressum` → `NotFoundHttpException` aus `abort_if` + Log-Warnung, `/seiten/datenschutz` → 404, Jobs-Seite zeigt „Zurzeit sind keine Stellen ausgeschrieben.“ und den Button, insgesamt 0 API-Aufrufe. |
| AK13 Unlesbarer Stand | bestanden | 10 Datensätze grün (Erfolg und Störung mit Fallback × `'gestern Abend'`, `''`, `null`, Array, Zahl) → 200, Texte exakt laut Spec. Reviewer-Probe weiterer Werte bei `erfolg`: `'2026-13-45T99:99:99+00:00'`, `'0'`, `'ein Text 2026'`, `true`, `1.5` → jeweils 200 und „Neu geladen.“. Grenzfall: `'tomorrow'` ist für Carbon lesbar und erscheint als Datum (17.10.2026 00:00) — entspricht dem Wortlaut „lässt sich als Datum lesen“. |

### Randfälle
| Randfall | Ergebnis | Nachweis |
|---|---|---|
| Rate-Limit → 429, keine Meldung | bestanden | Test grün. |
| Keine Exception-Texte/URLs/Token | bestanden | Nur feste `__()`-Texte in `fuer()`; Tests zum Token-Wert grün. |
| `consentlaw:reload`, Button **und `refresh()`** an einer Stelle | bestanden | `DocumentEndpoint::refresh()` und `JobsEndpoint::refresh()` sind jetzt `return $this->neuLaden()->inhalt;`. Probe: `get()`=Doc-1, `refresh()`=Doc-2 (neuer API-Aufruf), danach `get()`=Doc-2 aus dem Cache; Jobs analog, Rückgabetyp `Collection`; `refresh()` bei 503 liefert weiter den Fallback (stale). Verhalten identisch zum alten `forget()` + `get()/all()`. Hinweis 1 aus Review 1 erledigt. |
| Token-Abfangen (AK11, AK12) an einer gemeinsamen Stelle | bestanden | `grep MissingTokenException src`: Abfangen nur in `Support/KomponentenInhalt` (genutzt von `document`-/`jobs`-Komponente und `ConsentLawPageController`, per Konstruktor-Injektion). Der zweite `catch` in `NeuLadenController` dient AK5 (Meldung „kein Token“), nicht dem Rendern — keine Doppelung im Sinne des Randfalls. |

### Nicht-Ziele
Unverändert eingehalten: kein JavaScript/AJAX; kein dauerhafter Stand; PHP-API wirft ohne Token weiter (Test grün); keine Störungsmail ohne Token; Texte über `__()`.

## Sicherheit (Review 2)
- Keine neue Eingabefläche: Seiten-Controller nimmt den Typ weiter nur aus den Route-Defaults; `KomponentenInhalt` fängt ausschließlich `MissingTokenException` (unbekannter Typ wirft weiter `InvalidArgumentException` wie bisher).
- Session-Inhalt robust gegen Typverwechslung: `formatiere()` akzeptiert nur nicht-leere Strings, sonstiges (Array, Bool, Zahl) → ohne Datum; Parse-Fehler gefangen. Unbekannte `art` → keine Meldung (Review 1).
- Ausgabe weiter über `{{ }}` escaped; Log-Warnung enthält keinen Token-Wert.
- Mass-Assignment/Mandantentrennung: nicht betroffen.

## Regressionen (Review 2)
- Volle Suite: `../../vendor/bin/phpunit` → **OK (679 tests, 3215 assertions)** (Review 1: 665; +14 = 4 AK12 + 10 AK13).
- `git diff main...HEAD -- tests`: nur `tests/NeuLadenRueckmeldungTest.php` neu, bestehende Testdateien unverändert; Tests seit Rot-Commit `ca0466d` unverändert (`git diff ca0466d..HEAD -- tests` leer).
- `pint --test src` → passed.
- `ConsentLawPageController`: Jobs-Seite weiter `current()` (ohne Abgelaufene), Dokument-Seite weiter `get()` + `abort_if` leer → 404; mit Token unverändert 200 (Gegenprobe).
- **Nicht ausgeführt:** PHPStan (`vendor/bin/phpstan` im Workspace nicht vorhanden).
- **Nicht ausgeführt:** echter Lauf im Browser einer Host-App inkl. Browser-Konsole; die Host-Apps binden `dev-main` aus `vendor/` ein. Der Moderator nennt in `plan.md` einen echten Lauf in einer App-Kopie — vom Reviewer nicht wiederholt.

## Hinweise (nicht blockierend)
1. **Doppelter Abruf auf der Package-Jobs-Seite:** `ConsentLawPageController` übergibt `jobs` an `consentlaw::pages.jobs`, die View nutzt die Variable aber nicht, sondern rendert `<x-consentlaw::jobs>` erneut. Folge ohne Token: **zwei** Log-Warnungen pro Aufruf (Probe); mit Token ein zusätzlicher (gecachter) Durchlauf. Vorbestehend (auf `main` ebenso ungenutztes `jobs()->current()`), durch AK12 aber sichtbar. Vorschlag: Variable im Controller entfernen oder die View nutzt sie.
2. **Warntext:** Auch für die Fertig-Seiten lautet die Warnung „… – Komponente bleibt leer.“ — kosmetisch, AK12 verlangt nur „Warnung wie AK11“.
3. **Review-1-Hinweis 4 (Stil):** `KomponentenInhalt` ist jetzt injizierbar (Konstruktor mit `ConsentLawClient`, `LoggerInterface`) und im Controller injiziert; in den anonymen Blade-Komponenten über `app(...)` aufgelöst — für anonyme Komponenten üblich, erledigt.

## Fazit Review 2: freigegeben
AK1–AK13 und alle Randfälle durch eigene Läufe belegt (Suite, Feature-Tests, Gegenprobe und Grenzwert-Proben); Hinweise 1–3 aus Review 1 erledigt, 4 erledigt. Sicherheit unauffällig, keine Regression. Ehrlich offen: PHPStan, visuelle Darstellung (AK8) und Browser-Konsole in einer echten Host-App.
