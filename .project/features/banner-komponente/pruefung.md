# Prüfung: banner-komponente — 2026-10-26

Branch `feature/banner-komponente`, geprüft gegen `main` (`git diff main...HEAD`, 7 Commits eb5b38f..f828cb9).
Eigene Nachweise: Feature-Tests und ganze Suite (`php vendor/bin/phpunit`), eigene Render-Proben über `AppTestCase` (Skripte im Session-Scratchpad, nicht im Repo), Klick-Prüfung in einer eigenen Headless-Chrome-Instanz (CDP, `--remote-debugging-port=0`, eigenes Profil im Scratchpad) mit Gegenprobe.

| AK / Aussage (Zitat) | Ergebnis | Nachweis |
|---|---|---|
| AK1 „dann enthält die Ausgabe genau ein `<script>`“ | bestanden | Render-Probe: genau ein `<script …></script>`; Test `test_gibt_genau_ein_script_mit_src_und_defer_aus` grün |
| AK1 „mit `src="{base_url}/banner.js?token={token}"`“ | bestanden | Ausgabe `<script src="https://www.consentlaw.eu/banner.js?token=test-token" defer>`; mit `base_url=https://banner.example` → `src="https://banner.example/banner.js?token=abc123"` (Test `test_base_url_kommt_aus_der_konfiguration`) |
| AK1 „und dem Attribut `defer`“ | bestanden | `defer` im Starttag (Render-Probe und Test) |
| AK1 „`base_url` steht ohne abschließenden `/` (auch wenn `CONSENTLAW_URL` mit `/` endet)“ | bestanden | `base_url=https://www.consentlaw.eu/` → `…consentlaw.eu/banner.js?…`; Test mit `/` und `//` grün (`rtrim(…, '/')` in `BannerFreigabe::scriptUrl()`) |
| AK1 „der Token ist URL-kodiert“ | bestanden | Token `ab c&d"<x>` → `token=ab%20c%26d%22%3Cx%3E` (`rawurlencode`) |
| AK1 „und HTML-maskiert“ | bestanden | Ausgabe über `{{ }}`; nachgewiesen mit präparierter `base_url` `…"><script>alert(1)</script>` → `&quot;&gt;&lt;script&gt;…` im `src`. Beim Token selbst bleibt nach der URL-Kodierung nichts zu maskieren; Test `test_token_ist_url_kodiert_und_html_maskiert` grün |
| AK2 „Gegeben `CONSENTLAW_TOKEN` ist leer oder nicht gesetzt … dann ist die Ausgabe leer“ | bestanden | Render-Probe Token `null` und `''` → Ausgabe `[]`; Tests (DataProvider null/leer) mit Gegenprobe grün |
| AK2 „(kein Script, kein Fehler, keine Exception)“ | bestanden | Kein Script; Render ohne Exception; Log (TestHandler) nach allen Proben leer |
| AK3 „Gegeben `CONSENTLAW_BANNER_ENABLED=false` … dann ist die Ausgabe leer, auch bei gesetztem Token“ | bestanden | Echte Umgebungsvariable `false`/`FALSE`/`(false)` → Config `false`, Ausgabe leer bei Token `test-token`; Test `test_umgebungsvariable_false_schaltet_den_banner_in_der_konfiguration_aus` grün |
| AK3 „Ist die Variable nicht gesetzt, ist der Banner eingeschaltet.“ | bestanden | Ohne Variable: Config `true`, Script wird ausgegeben (Render-Probe „Standard“, Test `test_ohne_umgebungsvariable_ist_der_banner_eingeschaltet`). Zusätzlich: eine früher veröffentlichte Config ohne `banner`-Schlüssel → über `mergeConfigFrom` bzw. Default `true` in `get()` eingeschaltet |
| AK3 „Der Wert steht in der Konfiguration unter `consentlaw.banner.enabled`.“ | bestanden | `src/Config/consentlaw.php`: `'banner' => ['enabled' => env('CONSENTLAW_BANNER_ENABLED', true)]`; Config-Abfrage in den Proben |
| AK4 „dann ruft der Client die consentLaw-API nicht auf (kein HTTP-Request“ | bestanden | Render-Proben (aktiv, ohne Token, aus, Link): 0 HTTP-Aufrufe; Test `test_kein_api_aufruf_cache_oder_fallback_zugriff` mit Gegenprobe (Impressum löst HTTP aus) grün |
| AK4 „kein Cache-“ | bestanden | Test mit zählendem Cache-Store: 0 Zugriffe für AK1/AK2/AK3, Gegenprobe > 0; Code: `BannerFreigabe` liest nur `Config` |
| AK4 „kein Fallback-Zugriff)“ | bestanden | Render-Proben: 0 Fallback-Dateien; Test mit Gegenprobe grün |
| AK5 „dann entsteht ein Link mit dem Text „Cookie-Einstellungen““ | bestanden | Ausgabe `<a href="#" onclick="…">Cookie-Einstellungen</a>` |
| AK5 „der beim Klick `window.CookieScript.open()` aufruft, sofern `window.CookieScript` existiert“ | bestanden | Chrome-Klick mit Stub `window.CookieScript.open` → Zähler `geoeffnet: 1`. `consentLaw/public/banner.js` Z. 251 setzt `window.CookieScript = { open: openBanner }` (Name passt) |
| AK5 „und die Seite weder neu lädt“ | bestanden | Chrome: Marke `window.__marke` nach dem Klick erhalten, keine weitere Navigation |
| AK5 „noch zum Seitenanfang springt“ | bestanden | Chrome: `scrollY` 4800 vor und nach dem Klick, `location.href` ohne `#`. Gegenprobe (`href="#"` ohne `preventDefault`): `scrollY` 0, `#` angehängt, `navigatedWithinDocument` — die Messung greift |
| AK5 „Mit Slot ersetzt der Slot den Text“ | bestanden | `<x-consentlaw::cookie-einstellungen>Cookies <b>verwalten</b></…>` → `<a …>Cookies <b>verwalten</b></a>`, kein Standardtext; Test grün |
| AK5 „übergebene Attribute (z. B. `class`) landen am Link“ | bestanden | `class="a b" id="c"` am `<a>`; dynamisches `:class` mit `"`-Einschleusung wird zu `&quot;` maskiert |
| AK6 „Gegeben kein Token (AK2) … dann ist die Ausgabe leer“ | bestanden | Render-Probe Token `null` → `[]`; Tests null/leer mit Gegenprobe grün |
| AK6 „oder `CONSENTLAW_BANNER_ENABLED=false` (AK3) … dann ist die Ausgabe leer“ | bestanden | Render-Probe `enabled=false` mit Slot → `[]`; Tests (auch mit Slot) grün |
| AK7 „Die README beschreibt beide Komponenten“ | bestanden | README Abschnitt „Consent-Banner (optional)“ mit `<x-consentlaw::banner />` und `<x-consentlaw::cookie-einstellungen />` (mit/ohne Slot, `class`) |
| AK7 „den Schalter `CONSENTLAW_BANNER_ENABLED`“ | bestanden | Absatz + `dotenv`-Beispiel + Zeile in der Variablentabelle (Standard `true`, Config `consentlaw.banner.enabled`) |
| AK7 „und dass `banner.js` von consentLaw geladen wird“ | bestanden | „Die Komponente lädt `banner.js` von consentLaw (`{CONSENTLAW_URL}/banner.js?token=…`, mit `defer`)“ |
| Randfall 1 „`CONSENTLAW_URL` mit abschließendem `/` → kein doppelter `/` vor `banner.js`“ | bestanden | siehe AK1; auch `//` am Ende ergibt einen `/` |
| Randfall 2 „Komponente zweimal im Layout → zweimal ausgegeben.“ | bestanden | Render-Probe mit zwei Komponenten → zwei identische `<script>` |
| Randfall 2 „Verhindern ist Sache des Layouts, nicht der Komponente.“ | bestanden | Komponente hat keinen Zustand/keine Sperre (Code gelesen) |
| Randfall 3 „`window.CookieScript` fehlt, der Klick tut nichts“ | bestanden | Chrome ohne `CookieScript`: kein Sprung, kein Neuladen, `href` unverändert |
| Randfall 3 „und erzeugt keinen JavaScript-Fehler“ | bestanden | Chrome: keine `exceptionThrown`, keine Konsolen-Fehler. Gegenprobe ohne Absicherung (`window.CookieScript.open()` direkt) erzeugt „Uncaught“ — die Messung greift |
| Nicht-Ziel „`banner.js` oder die Banner-Einstellungen über die eigene Domain ausliefern (Proxy)“ | eingehalten | Keine Route, kein Controller im Diff; `src` zeigt auf `base_url` |
| Nicht-Ziel „Preconnect/Preload auf consentlaw.eu“ | eingehalten | Kein `<link rel=preconnect/preload>` im Diff |
| Nicht-Ziel „Script-Manager, Google-Tags, Consent-Mode“ | eingehalten | Nicht im Diff |
| Nicht-Ziel „Änderungen an `banner.js` in consentLaw“ | eingehalten | Diff betrifft nur das Client-Repo |
| Spielraum „Keine neuen Composer- oder npm-Pakete.“ | eingehalten | `composer.json`/`composer.lock` unverändert, kein npm |
| Spielraum „Die Bedingung steht aber nur an einer Stelle.“ | eingehalten | `src/Support/BannerFreigabe::aktiv()`, beide Views nutzen sie; `scriptUrl()` ruft `aktiv()` |

## Sicherheit
- **Ausgabe-Escaping:** `src` über `{{ }}` maskiert (Nachweis mit präparierter `base_url`), Token zusätzlich `rawurlencode`. Attribute über `$attributes` (ComponentAttributeBag) maskiert; Einschleusung über dynamisches `:class` ergibt `&quot;`. Der Slot wird roh ausgegeben — das ist Blade-Standard und Inhalt des Layouts, kein Fremdinput.
- **Mandantentrennung/Autorisierung/Validierung/Mass-Assignment:** nicht betroffen (keine Route, kein Model, keine Eingabe).
- **Token in der Seite:** Der Domain-Token steht im HTML. Das ist von consentLaw so vorgesehen (Snippet im Dashboard) und laut Config-Kommentar ein reiner Lese-Token; kein neues Risiko.
- **Hinweis (kein Mangel):** Übergibt das Layout selbst `href` oder `onclick`, entstehen doppelte Attribute; der Browser nimmt das erste (das der Komponente). Das Verhalten nach AK5 bleibt also erhalten, das übergebene Attribut wirkt nicht. Die README nennt das nicht.
- **Hinweis (kein Mangel):** Inline-`onclick` wirkt nicht unter einer CSP ohne `unsafe-inline` — in der README dokumentiert.

## Regressionen
- Ganze Suite: `php vendor/bin/phpunit` → OK (816 Tests, 3630 Assertions). Feature-Tests: 26/26 grün (Banner 12, Link 10, README 4).
- Tests seit dem Test-Commit d5a406c unverändert (`git diff d5a406c HEAD -- tests` leer).
- Bestehende Dateien nur ergänzt: `src/Config/consentlaw.php` (neuer Block `banner`), README. Kein bestehender Code geändert. Eine bereits veröffentlichte Config ohne `banner` funktioniert (Probe: eingeschaltet).
- Pint/PHPStan nicht selbst ausgeführt (kein Pint/PHPStan in `vendor/bin` des Pakets); der Plan meldet für Pint nur die Altlast `tests/NeuLadenRueckmeldungTest.php`.

## Beobachtungen außerhalb der AKs (keine Mängel)
1. `CONSENTLAW_BANNER_ENABLED` mit Werten, die `env()` nicht umwandelt: `off` → Banner **an**, `0` und leer → aus. Spec regelt nur `false` und „nicht gesetzt“; der Plan nennt den leeren Wert als bewusste Annahme. `off` ist nicht erwähnt.
2. Token nur aus Leerzeichen (`"   "`) gilt als gesetzt → `src=…?token=%20%20%20`. AK2 spricht von „leer oder nicht gesetzt“; nicht verletzt, aber `banner.js` lädt dann mit ungültigem Token.
3. Plan nennt consentLaw-Task #99 (`window.CookieScript` → `window.ConsentLaw`). Bis zum Umzug hängt der Link an dem alten Namen; nach der Übergangszeit wäre AK5 ohne Folgeänderung gebrochen.

## Fazit: freigegeben
