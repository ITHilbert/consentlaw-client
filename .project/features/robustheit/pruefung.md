# Prüfung: robustheit (Runde 2) — 2026-10-16

Branch `feature/robustheit`, 6 Commits ab `main`. Neu seit Runde 1: Spec-Ergänzung `0fd0739` (AK8–AK10), rote Tests `301a8de`, Umsetzung `4e94a2e`.

- **Tests unverändert:** `git diff --stat 301a8de HEAD -- tests` ist leer. Der Umsetzungs-Commit fasst keinen Test an.
- **Rot-Nachweis selbst geführt:** Den Stand `301a8de` (src und tests) habe ich in den Scratchpad exportiert und die Suite dort laufen lassen. Ergebnis: **15 Fehlschläge**, alle in den neuen AK8–AK10-Tests (`NichtVeroeffentlichtCacheTest` 7, `WarmTokenHinweisTest` 2, `FormatOhneAngabeTest` 5, `DocumentFormatTest` 1). Alle übrigen Tests waren schon dort grün.
- **Geänderter Bestandstest:** Der Commit `301a8de` ändert `DocumentFormatTest::test_echtes_html_wird_erkannt`. Der Test erwartete bisher `html` und erwartet jetzt `markdown`. Das ist die gewollte Verhaltensänderung aus AK10 und keine Aufweichung.

| AK | Ergebnis | Nachweis |
|---|---|---|
| AK1a–d | bestanden | `KernablaufTest` grün (volle Suite). Der Code-Pfad ist gegenüber Runde 1 nur um den 404-Vermerk erweitert. Die Nachweise aus Runde 1 gelten weiter. |
| AK2 | bestanden | `FehlerstatusTest` grün. In `HttpTransport` ist weiterhin nur 404 eine `ConsentLawResponseException`, jeder andere Fehlerstatus eine `ConsentLawUnavailableException`. |
| AK3 | bestanden | `AusfallWartezeitTest` grün. Der Sperrvermerk wird in Schritt 2 weiterhin unabhängig von `cache.enabled` geprüft. |
| AK4 | bestanden | `MarkdownRendererTest` und `MarkdownAusgabeTest` grün. `MarkdownRenderer` und die Views sind seit `a2bd679` unverändert. Eigene Proben erneut gelaufen, ohne ausführbares Ergebnis: `<script>`, `<img onerror>`, `<iframe>`, `<details ontoggle>`, `[a](javascript:…)` auch mit gemischter Groß-/Kleinschreibung und Entity-Kodierung, Bild mit `javascript:`, roher `<a href="javascript:…">`. `<br>`, `<br/>` und `<br />` werden zu `<br />`. `grep Str::markdown src` findet nichts. |
| AK5 | bestanden | `FormatAusApiTest` und `FormatOhneAngabeTest::test_explizites_format_html_*` grün. Ein deklariertes `format` gilt unverändert. |
| AK6 | bestanden | `StandardUrlTest` grün. Echter Lauf (siehe AK8): Die Anfragen gingen an den Host `www.consentlaw.eu`. |
| AK7 | bestanden | `WarmCommandTest` grün, dazu `WarmTokenHinweisTest`: einzelne 404 → Exit 0 ohne Token-Hinweis (alle Dokumente 404 + Jobs ok, alle Dokumente ok + Jobs 404, nur Impressum ok). |
| AK8 | bestanden | `NichtVeroeffentlichtCacheTest` (20 Tests) grün. Abgedeckt sind: kein HTTP-Aufruf innerhalb der TTL (Dokumente und Jobs), Status bleibt `NichtVeroeffentlicht`, Trennung je Inhalt, neue Abfrage nach Ablauf der TTL, erneutes Cachen eines neuen 404, kein Retry bei `http.retries=1` (Dokumente und Jobs), Retry bei 503 bleibt, kein Cachen bei `cache.enabled=false` oder TTL 0, AK1d mit Fallback-Datei, `forget()`, `refresh()` und `warm` heben den Vermerk auf. **Echter Lauf** gegen `https://www.consentlaw.eu` mit echtem `HttpFactory`, ungültigem Token und Standard-Retries (`retries=1`, `retry_delay=200`): 5 Abrufe von `agb()->get()` ergaben **1 HTTP-Request** in 0,34 s, alle `null`, Status `NichtVeroeffentlicht`. In Runde 1 waren es 10 Requests. Code: `ContentResolver` Schritt 1 (Key `prefix:404:key`, nur bei `cacheEnabled && ttl > 0`), `HttpTransport::retry(... when: kein 404)`. |
| AK9 | bestanden | `WarmTokenHinweisTest` (8 Tests) grün. Alle 6 Typen 404 → Exit 1, „Token“ in der Ausgabe, jeder Typ einzeln als „nicht veröffentlicht“ gemeldet. Störung bei allen Typen → Exit 1 **ohne** Token-Hinweis. **Echter Lauf** `consentlaw:warm` gegen `www.consentlaw.eu` mit ungültigem Token: Exit 1, 6 HTTP-Requests, Ausgabe „Alle Inhalte sind ‚nicht veröffentlicht‘ – bitte den Token (CONSENTLAW_TOKEN) prüfen …“. |
| AK10 | bestanden | `FormatOhneAngabeTest` (8 Tests) und `DocumentFormatTest` grün. Ohne `format` gilt `markdown`, auch für `<div><script>…</div>` und `<img onerror>`. In allen fünf Dokument-Komponenten kommt weder `<script` noch `alert(1)` noch `onerror` an. Die Inhaltserkennung ist aus `Document::resolveFormat()` ausgebaut, der veraltete Docblock ist korrigiert. Selbst geprüft: Ein `format`, das kein String ist (`["x"]`), ergibt ebenfalls `markdown`. |

Randfälle:
- Beschädigte Fallback-Datei und `cache.enabled=false` (AK3): Die Tests aus Runde 1 sind weiter grün.
- `format: html` geht nicht durch den Renderer: `MarkdownAusgabeTest` und `FormatOhneAngabeTest::test_explizites_format_html_aus_der_api_*` sind grün.

Nicht-Ziele: Es wurde keine `locale`-Logik, kein Scheduler-Eintrag und kein HTML-Sanitizing für `format: html` gebaut.

Nicht prüfbar: `laravel.log` und die Browser-Konsole in einem echten Kundenprojekt. `it-hilbert.com` bindet `ithilbert/consentlaw-client: dev-main` als Vendor-Kopie ein, nicht diesen Branch. Ersatzweise habe ich das Rendering mit der echten Blade-Engine in den Tests und das HTTP-Verhalten über echte Requests gegen `www.consentlaw.eu` geprüft (Testbasis `AppTestCase` mit echtem `HttpFactory`, Testdatei nur im Scratchpad).

## Sicherheit
- **XSS:** Der Markdown-Pfad ist dicht (siehe AK4). Für Dokumente gibt es ohne Formatangabe keinen ungefilterten Pfad mehr (AK10). Der Befund aus Runde 1 ist damit behoben.
- **Hinweis (kein AK-Verstoß):** Ungefiltert ausgegeben wird alles, was nicht exakt `markdown` oder `md` ist. Selbst geprüft:
  - `Document` mit `format: "HTML"` (Großschreibung) oder einem unbekannten Wert ergibt `isMarkdown() === false`, die Ausgabe läuft ungefiltert über `{!! !!}`.
  - `Job` mit `description_format: ""` ergibt ebenfalls `isMarkdown() === false`, die Beschreibung wird ungefiltert ausgegeben. Laut Spec filtert consentLaw Job-Beschreibungen ausdrücklich nicht.

  Die Nicht-Ziele decken nur `format: html` ab. Robuster wäre eine Allowlist: nur ein exaktes `html` gilt als HTML, alles andere läuft über den Markdown-Filter. Das betrifft sowohl `Document::isMarkdown()` als auch `Job::isMarkdown()`. Ich empfehle, das als AK nachzuziehen. Praktisch ausnutzbar ist es nur, wenn consentLaw selbst ein abweichendes Format liefert.
- **JSON-LD:** `json_encode(… JSON_HEX_TAG)` ist unverändert.
- **Cache-Keys:** Der 404-Vermerk (`prefix:404:key`) und der Sperrvermerk (`prefix:sperre:key`) liegen in eigenen Namensräumen und stoßen nicht mit dem Inhalts-Key zusammen. Der 404-Vermerk gilt je Inhalt und Locale (Test „nur für den betroffenen Inhalt“).
- Mandantentrennung, Autorisierung und Mass-Assignment sind im Package nicht einschlägig: Es gibt keine Models und keine Endnutzer-Eingaben, nur den Token der Installation.

## Regressionen
- Volle Suite `../../vendor/bin/phpunit`: **OK (136 Tests, 369 Assertions)**. In Runde 1 waren es 100 Tests.
- `pint --test src tests`: **passed**. Der Stilverstoß in `tests/AppTestCase.php` aus Runde 1 ist behoben.
- `php -l` auf alle geänderten PHP-Dateien: ohne Fehler.
- PHPStan ist im Workspace nicht installiert und wurde deshalb nicht geprüft.
- Bewusste Verhaltensänderungen gegenüber `main`:
  - Ein 404 wird jetzt für die Dauer der Cache-TTL gecacht. Wird ein Inhalt in consentLaw erst nachträglich veröffentlicht, erscheint er erst nach Ablauf der TTL, nach `refresh()`/`forget()` oder nach `consentlaw:warm`. Das ist durch AK8 gedeckt; im README sollte es für Kunden erwähnt sein.
  - Inhalt ohne `format`, der wie HTML aussieht, wird jetzt als Markdown gerendert und dabei gefiltert (AK10). Hat ein Endpunkt bisher HTML ohne `format` geliefert, verliert die Darstellung ihre Tags. Laut Runde 1 liefert die API `format` immer, ein Risiko besteht also nur bei alten Fallback-Dateien ohne `format`.
- Der Bruch in der öffentlichen API (`ContentResolver::resolve()` liefert `Abruf`) ist im README dokumentiert (Zeile 233 ff.). Für Release 1.0.0 ist das in Ordnung.
- Offener Hinweis aus Runde 1, weiter gültig: Es gibt keinen Thundering-Herd-Schutz bei der ersten Störung.

## Fazit: freigegeben
AK1–AK10 und die Randfälle habe ich selbst nachgewiesen:
- Rot-Nachweis für die neuen Tests,
- volle Suite grün,
- echte Requests gegen `www.consentlaw.eu` für AK8 und AK9.

Die Nicht-Ziele sind eingehalten. Offen als Empfehlung, kein Merge-Hindernis: eine Format-Allowlist (nur exakt `html` ungefiltert) für `Document` und `Job` als neues AK.
