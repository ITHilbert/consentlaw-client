# Prüfung: neu-laden-button — 2026-10-16 (Runde 2)

Branch `feature/neu-laden-button`, 6 Commits ab `main` (Spec/Plan, rote Tests, Umsetzung AK1–AK6, Spec-Ergänzung AK4-locale/AK7 nach Review, Tests dazu, Umsetzung).
Geprüft gegen den aktuellen Stand von `spec.md` (AK4 um `locale` ergänzt, AK7 neu, Randfall „Fallback aus“ als bekannte Grenze aufgenommen).
Tests seit dem Test-Commit `e03ecc1` unverändert (`git diff e03ecc1..HEAD --stat` enthält keine Datei unter `tests/`).
Eigene Zusatzprüfungen liefen als Wegwerf-Test im Scratchpad (nicht im Repo) gegen die Test-App des Packages (echter Router, Session, CSRF, Throttle).

| AK | Ergebnis | Nachweis |
|---|---|---|
| AK1 — Standard aus | bestanden | Config-Standard `false`; ENV `false`/`true` geprüft; ohne Schalter kein Formular (document, Kurzformen, jobs, Slot-Fall), `Route::has` false, POST → 404/405, 0 API-Aufrufe. `phpunit --filter NeuLadenButtonTest` grün (126 Tests). Gegenüber Runde 1 unverändert. |
| AK2 — Nie auf Production | bestanden | `APP_ENV=production` + Schalter: kein Button, keine Route, 0 API-Aufrufe (Tests). Eigene Prüfung: Schalter als String `'true'`, Umgebung zur Laufzeit auf `production` → Button weg, POST → 404 ohne API-Aufruf. Hinweis aus Runde 1 gilt weiter: Vergleich exakt auf `production` (`Production`/`prod` gelten als Nicht-Production); spec-konform. |
| AK3 — Button sichtbar | bestanden | local/testing/staging: genau ein `<form method="post">`, `action = route('consentlaw.neu-laden', typ)`, `_token`, Button „Neu laden“, Klasse `consentlaw-refresh`; alle 6 Typen inkl. Slot-Fall. Gegenüber Runde 1 unverändert. |
| AK4 — Neu laden | bestanden | Standardfall wie Runde 1 (ein API-Aufruf nur für den Typ, Cache + Fallback-Datei aktualisiert, Sperrzeit/404-Vermerk umgangen, 302 auf Referer, Folge-Render zeigt neuen Stand). **Locale (neu):** Tests für Kurzform/document/jobs/Slot-Fall prüfen verstecktes Feld `locale`; ohne Attribut kein Feld. Eigene Klick-Simulation (Felder aus dem gerenderten Formular übernommen, `<x-consentlaw::datenschutz locale="en" />`, Umgebung `staging`): POST → 302 auf `http://localhost/en/imprint?x=1`, genau ein Aufruf `…/datenschutz/test-token?locale=en`, danach EN zeigt „NEU-EN“, DE weiter „ALT-DE“, Fallback-Datei `datenschutz.en.*.json` enthält „NEU-EN“. Ungültige Sprachangaben (`../x`, `EN`, `de;drop`, Zeilenumbruch, Array …) → 422, 0 API-Aufrufe, Cache unberührt (Tests + eigene Prüfung, auch per Query-String `?locale=../x` → 422). Die Lücke aus Runde 1 ist geschlossen. |
| AK5 — Nur gültige Typen | bestanden | `unbekannt`, `privacy`, `wp-login.php`, `impressum-jobs`, `..` → 404, 0 API-Aufrufe, Vorbedingung „Route registriert“ geprüft. Unverändert. |
| AK6 — Missbrauchsschutz | bestanden | 7. Anfrage/Minute/IP → 429 (Tests). Eigene Prüfung: Limit greift auch bei wechselnder `locale` (`de,en,fr,it,es,nl,pl` → 6× 302, dann 429); ohne Token mit `locale` → 419. CSRF echt (Tests laufen in `local`). |
| AK7 — Freigabe auch in der Aktion | bestanden | Controller prüft `NeuLadenFreigabe::aktiv()` als ersten Schritt (`abort_unless(..., 404)`). Tests: Route registriert, danach Schalter zur Laufzeit aus bzw. Umgebung auf `production` → 404, 0 API-Aufrufe, Cache unberührt, alle Typen. Eigene Prüfung: ohne Freigabe + ungültige `locale` → 404 (Freigabe vor Validierung), 0 API-Aufrufe; Partial blendet den Button auch bei vorhandener Route aus. Ein echter `route:cache` aus anderer Umgebung wurde nicht nachgestellt (Test-App ohne Artisan-Routing-Cache); die Simulation prüft dieselbe Bedingung (Route existiert, Freigabe fehlt). Die Route nutzt eine Controller-Klasse, ist also cachebar. |

### Randfälle
| Randfall | Ergebnis | Nachweis |
|---|---|---|
| Störung beim Neu laden | bestanden | Störung/503/429/fehlender Token → 302, keine Fehlerseite, bisheriger Stand bzw. Fallback (Tests). Eigene Prüfung mit `locale=en`: Störung → 302, EN-Seite zeigt weiter „ALT-EN“. |
| Rückleitung nur auf eigene Seite | bestanden | Fremde Domain, Userinfo-/Subdomain-Trick, protokollrelativ, Parameter `redirect/next/url/…` → Ziel bleibt eigener Host (Tests, Runde-1-Prüfungen weiter gültig, Code `rueckleitung()` unverändert). |
| Fallback aus + Neu laden scheitert | bestanden (als bekannte Grenze) | Spec führt das jetzt ausdrücklich als bekannte Grenze; README nennt es unter „Bekannte Grenze“. Verhalten wie in Runde 1 beobachtet (Seite leer bis zum nächsten Abruf). |

### Nicht-Ziele
Kein Webhook, keine Production-Variante, Darstellung schlicht mit überschreibbarer Klasse. Eingehalten.

### Nicht prüfbar
- Echter Lauf in einer Host-App (Browser, `storage/logs/laravel.log`, Browser-Konsole): `it-hilbert.com` bindet `^1.1` als normale Vendor-Kopie ein, keine App nutzt diesen Branch. Geprüft wurde ausschließlich über die Test-App des Packages.
- PHPStan: weder in `packages_dev/vendor/bin` noch im Package installiert/konfiguriert.

## Sicherheit
- **CSRF:** Gruppe `web`, nur POST; 419 ohne/mit falschem Token, auch mit `locale`.
- **Autorisierung:** Keine Nutzerprüfung — bewusst; Schutz über Schalter + Nicht-Production (jetzt doppelt: Route-Registrierung **und** Aktion, AK7) + Rate-Limit. Auf öffentlich erreichbaren Staging-Seiten kann jeder Besucher neu laden (max. 6/min/IP); vertretbar.
- **Validierung:** Typ per Whitelist (404); `locale` per Regex aus der Spec, nur Strings, sonst 422 — Arrays, Zeilenumbrüche, Pfad-Traversal abgewiesen, bevor ein API-Aufruf entsteht. Prüfreihenfolge: Freigabe → Typ → Locale.
- **Ausgabe-Escaping:** `locale` im versteckten Feld über `{{ }}`. Eigene Prüfung: `locale='"><script>x</script>'` wird als `&quot;&gt;&lt;script&gt;…` ausgegeben, kein Ausbruch.
- **Open Redirect:** keiner; Eingabeparameter werden für die Rückleitung nicht ausgewertet.
- **Mass-Assignment / Mandantentrennung:** nicht betroffen (keine Models, ein Token je Installation).
- Hinweise (nicht blockierend):
  - `locale` wird über `$request->input()` gelesen, also auch aus dem Query-String (`?locale=en` → wirkt). Gleiche Validierung, daher kein Risiko, nur breiter als das Formular.
  - Hinter einem Reverse Proxy ohne TrustProxies greift das IP-Limit global (aus Runde 1, weiterhin nicht im README erwähnt).

## Regressionen
- Volle Suite Branch: `../../vendor/bin/phpunit` → **OK (449 Tests, 1670 Assertions)**, davon `NeuLadenButtonTest` 126 Tests / 692 Assertions. 449 − 126 = 323 = Testanzahl von `main` (Runde 1 geprüft) — alle bestehenden Tests laufen weiter grün.
- Pint `--test` auf allen geänderten PHP-Dateien (Controller, Freigabe, Provider, Config, Tests): bestanden.
- Bestehendes Verhalten: Komponenten erhalten nur das angehängte `@include`, das ohne Freigabe nichts ausgibt; `document`/`jobs` reichen zusätzlich `$locale` durch (bereits vorhandene Prop). Upgrade-Hinweis für veröffentlichte Views im README — **aber:** Der dort genannte `@include`-Aufruf lautet `['typ' => $type]` ohne `'locale' => $locale`. Wer eigene View-Kopien nach README anpasst, verliert die Sprachübermittlung (AK4-locale) still. Doku-Mangel, kein Code-Fehler.

## Fazit: freigegeben

Alle AKs (AK1–AK7) und Randfälle sind nachgewiesen; die blockierende Lücke aus Runde 1 (AK4 bei `locale`) und die fehlende Freigabe-Prüfung in der Aktion (jetzt AK7) sind behoben.

Offene, nicht blockierende Punkte:
1. README „Upgrade auf 1.2“: `@include` ohne `'locale' => $locale` — Anleitung sollte `['typ' => $type, 'locale' => $locale]` (Jobs: `['typ' => 'jobs', 'locale' => $locale]`) nennen.
2. Komponente mit Sprache außerhalb des Musters (z. B. `locale="DE"` oder `zh-Hant`) zeigt den Button, der Klick endet in 422 (eigene Prüfung: `locale="DE"` → 422). Spec-konform, aber für Nutzer überraschend; ggf. im README erwähnen.
3. README: Hinweis auf TrustProxies für das IP-Limit.
4. PHPStan und Lauf in einer echten Host-App nicht geprüft (siehe „Nicht prüfbar“).
