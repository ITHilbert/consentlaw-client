# Prüfung: robustheit — 2026-10-16

Branch `feature/robustheit` (3 Commits: Spec/Plan `4d91bed`, rote Tests `0b11ca7`, Umsetzung `a2bd679`).
Der Umsetzungs-Commit `a2bd679` ändert keine Datei unter `tests/`; die Tests sind also unverändert seit dem Rot-Commit.

| AK | Ergebnis | Nachweis |
|---|---|---|
| AK1a | bestanden | `KernablaufTest` (3 Tests) grün: zweiter Abruf ohne HTTP-Aufruf, auch bei ausgefallener API, auch für Jobs. Code: `ContentResolver::resolve()` Schritt 1. |
| AK1b | bestanden | `KernablaufTest` grün: Inhalt geliefert, 3 Abrufe = 1 HTTP-Aufruf, Fallback-Datei mit Payload auf der Platte (Dateiinhalt geprüft), auch für Jobs. |
| AK1c | bestanden | `KernablaufTest` mit Timeout/500/503 grün: Fallback geliefert, `isStale = true`. Ohne Fallback: `null`/leere Liste, keine Exception. Echter Lauf gegen `http://10.255.255.1` (echter HttpTransport, Standard-Retries 1): Status `stoerung`, Log „kein Fallback vorhanden“, keine Exception. |
| AK1d | bestanden | `KernablaufTest`/`FehlerstatusTest` grün: 404 trotz Fallback-Datei → `null`, `content() === ''`, `exists() === false`, leere Jobliste. Echter Lauf gegen `https://www.consentlaw.eu/api/impressum/ungueltig` → HTTP 404 → Status `nicht_veroeffentlicht`. |
| AK2 | bestanden | `FehlerstatusTest` (429/401/403, Dokumente und Jobs, mit und ohne Fallback) grün. Code: `HttpTransport` wirft nur bei 404 `ConsentLawResponseException`, jeder andere Fehlerstatus `ConsentLawUnavailableException`; ungültiges JSON bei 200 läuft ebenfalls in den Störungspfad. |
| AK3 | bestanden | `AusfallWartezeitTest` (16 Testfälle) grün: Standard 300 s in der Config, innerhalb der Wartezeit kein HTTP-Aufruf (mit Fallback → stale, ohne → `null`), nach 301 s erneuter Aufruf, einstellbar (60 s), alle Fehlerstatus ≠ 404 sperren, gilt bei `cache.enabled=false`, je Inhalt getrennt, auch für Jobs. Echter Lauf gegen nicht erreichbaren Host: 1. Aufruf 2,21 s, 2. Aufruf 0,000 s ohne HTTP. |
| AK4 | bestanden | `MarkdownRendererTest` + `MarkdownAusgabeTest` grün; Komponente `document`, Fertig-Seite `pages/document`, Komponente `jobs` und JSON-LD werden mit echter Blade-Engine gerendert und geprüft. „Eine Stelle“: `src/Support/MarkdownRenderer.php`; `grep Str::markdown src` ohne Treffer. Eigene Angriffsproben (siehe Sicherheit) ohne ausführbares Ergebnis. |
| AK5 | bestanden | `FormatAusApiTest` grün: `format: markdown` mit HTML-artigem Inhalt bleibt Markdown, `format: html` mit Markdown-artigem Inhalt bleibt HTML, auch über die echte Verdrahtung. |
| AK6 | bestanden | `StandardUrlTest` grün (Config-Standard und tatsächliche Anfrage-URL für Dokumente und Jobs; gesetzte `CONSENTLAW_URL` hat Vorrang). Real: `curl https://consentlaw.eu/api/impressum/x` → 301 auf www; `https://www.consentlaw.eu/...` → direkt 404 ohne Umleitung. |
| AK7 | bestanden | `WarmCommandTest` (9 Tests) grün: 404 → Hinweis „nicht veröffentlicht“ und Exit 0; Störung/429 ohne Fallback → „Störung“ und Exit 1; Störung mit Fallback → Exit 0 mit Fallback-Hinweis; ohne Token → Exit 1 und kein HTTP-Aufruf. |

Randfälle:
- Beschädigte Fallback-Datei → `KernablaufTest::test_beschaedigte_fallback_datei_wird_wie_kein_fallback_behandelt` grün (`FileFallbackStore::get()` liefert `null`).
- `cache.enabled=false` → AK3 gilt (Test grün, Sperrvermerk liegt unabhängig vom Inhalts-Cache im Cache-Store).
- `format: html` geht nicht durch den Renderer → `MarkdownAusgabeTest::test_html_format_wird_nicht_als_markdown_gerendert` grün.

Nicht-Ziele: Es wurde weder eine `locale`-Logik noch ein Scheduler-Eintrag noch HTML-Sanitizing für `format: html` gebaut.

Nicht prüfbar: Browser-Konsole und `laravel.log` in einem echten Kundenprojekt. `it-hilbert.com` bindet das Package als Vendor-Kopie `dev-main` ein, nicht den Branch. Das Rendering wurde stattdessen mit echter Blade-Engine in den Tests und das Log-Verhalten über einen echten Transportlauf geprüft.

## Sicherheit
- **XSS (AK4):** Eigene Proben gegen `MarkdownRenderer::render()`, die über die Tests hinausgehen. Referenz-Link mit `javascript:`, Bild mit `javascript:`/`data:`, HTML-Entity-kodiertes `&#106;avascript:`, `javascript&#58;`, `vbscript:`, `file:`, `<svg onload>` als Block, `<br onclick>`, HTML-Kommentar, `<style>`, `<form>`, CDATA, DOCTYPE, PHP-Tag, Title-Attribut mit Anführungszeichen. Ergebnis: kein `href`/`src` mit unsicherem Schema, kein rohes Tag, kein Event-Handler-Attribut. `<br onclick=…>` wird komplett entfernt, `<br>` in Code-Spans bleibt Text, `<br>` in Tabellenzellen wird zum Umbruch.
  - Hinweis: Die Autolink-Form `<javascript:alert(1)>` ergibt `<a>javascript:alert(1)</a>`, also ein Anker **ohne** `href`. Die Zeichenkette steht damit als Text in der Ausgabe, ist aber nicht klickbar. Ein Bild mit unsicherem Ziel ergibt `<img src="" …>`. Das ist harmlos, `src=""` löst aber in manchen Browsern eine Anfrage an die aktuelle Seite aus.
- **JSON-LD:** `json_encode(… JSON_HEX_TAG)` maskiert `<`/`>`. Ein `</script>` im Titel oder in der Beschreibung kann den Block nicht verlassen.
- **Ungefilterter Pfad ohne `format` (Hinweis, kein AK-Verstoß):** `Document::resolveFormat()` erkennt das Format am Inhalt, wenn die API kein `format` liefert. Inhalt mit `<div>`/`<p>` ohne Markdown-Überschrift gilt dann als `html` und wird **ungefiltert** mit `{!! !!}` ausgegeben. Laut `consentLaw/app/Http/Resources/RechtstextResource.php` liefert die API `format` inzwischen immer, und Jobs liefern `description_format: markdown` fest. Die Erkennung ist damit praktisch tot, öffnet aber den Renderer-Umweg wieder, falls ein Endpunkt das Feld verliert. Vorschlag: Fallback ohne Angabe auf `markdown` setzen (sicherer Pfad) und die Inhaltserkennung ausbauen. Der Docblock dort („Nicht alle consentLaw-Endpunkte liefern ein `format`-Feld“) ist veraltet.
- Mandantentrennung: Die Cache- und Sperr-Keys hängen am Endpoint-Key (Typ und Locale). Sperrvermerk und Fallback betreffen nur den eigenen Token der Installation, eine Vermischung über Tokens gibt es nicht.
- Mass-Assignment und Autorisierung: Im Package nicht einschlägig, es gibt keine Models und keine Eingaben von Endnutzern.

## Regressionen
- Volle Suite `../../vendor/bin/phpunit`: **OK (100 Tests, 282 Assertions)**. Die bestehenden Tests von `main` (`DocumentFormatTest`, `FallbackEntryTest`, `JobTest`) sind enthalten und grün.
- `php -l` auf alle geänderten PHP-Dateien: ohne Fehler. `composer validate`: gültig. `league/commonmark` ist jetzt eine direkte Abhängigkeit (vorher nur indirekt über Laravel), das ist korrekt.
- Pint (`--test src tests`): `src` sauber. **`tests/AppTestCase.php` verstößt** gegen `ordered_imports`, `fully_qualified_strict_types`, `not_operator_with_successor_space` und `unary_operator_spaces`. Das ist nur Stil in einer Testdatei, die Korrektur gehört dem test-writer. PHPStan ist im Workspace nicht installiert und deshalb nicht geprüft.
- Öffentliche API: `get()`, `all()`, `refresh()`, `forget()` unverändert. Neu ist `abrufen()`. `ContentResolver::resolve()` liefert jetzt `Abruf` statt `array|null`; Aufrufer sind nur die beiden Endpoints (grep). Für Kundenprojekte, die den Resolver direkt nutzen, ist das ein Bruch. Er sollte in der Versionierung bzw. im Changelog erwähnt werden.
- Veröffentlichte Configs ohne `retry_after` funktionieren dank `?? 300` im ServiceProvider.

## Befunde außerhalb der AKs (Spec-Lücke, kein Regress: Verhalten wie auf `main`)
1. **„Nicht veröffentlicht“ wird nicht gecacht, und 404 wird wiederholt.** Echte Messung mit Standard-Config (`retries=1`): 5 Seitenaufrufe auf einen nicht veröffentlichten Typ ergeben **10 HTTP-Requests**. Bei einem Kunden ohne AGB, dessen Seite `<x-consentlaw::agb />` einbindet, fragt also jeder Seitenaufruf zweimal die API, mit 200 ms Retry-Pause. Das widerspricht dem Spec-Ziel „die Seite wird nicht langsam“ und kann das neue Rate-Limit von consentLaw (429) selbst auslösen. Vorschlag: 404 kurz cachen (negativer Cache) und 404 vom Retry ausnehmen (`retry(..., when: fn ($e) => … status !== 404)`).
2. **`consentlaw:warm` mit ungültigem Token endet mit SUCCESS.** consentLaw antwortet bei ungültigem Token mit 404 (real geprüft), also melden alle Typen „nicht veröffentlicht“ und der Exit-Code ist 0. Das ist spec-konform (AK7 nennt nur „ohne Token“), ein Tippfehler im Token fällt beim Deploy aber nicht mehr auf. Vorschlag: FAILURE, wenn **alle** Typen 404 liefern, oder ein eigener Status für „Token ungültig“ aus der API.
3. Kein Thundering-Herd-Schutz: Bei der ersten Störung warten alle parallel laufenden Requests bis zum Setzen des Sperrvermerks auf den Timeout. Das ist für den Umfang akzeptabel und nur ein Hinweis.

## Fazit: freigegeben
Alle AK1–AK7 sowie die Randfälle wurden selbst nachgewiesen, durch Tests und, wo möglich, durch einen echten HTTP-Lauf. Die Nicht-Ziele wurden eingehalten, die Suite ist grün. Vor dem Merge sollte der Moderator klären:
- den Pint-Verstoß in `tests/AppTestCase.php` (an den test-writer),
- die Befunde 1 und 2 als neue AKs oder Folge-Feature,
- den Ausbau der Formaterkennung in `Document::resolveFormat()`.
