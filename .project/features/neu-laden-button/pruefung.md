# Prüfung: neu-laden-button — 2026-10-16

Branch `feature/neu-laden-button` (3 Commits ab `main`: Spec/Plan, rote Tests, Umsetzung).
Geprüft gegen `spec.md`. Tests seit dem Test-Commit `313210a` unverändert (`git diff 313210a..HEAD -- tests` leer).
Eigene Zusatzprüfungen liefen als Wegwerf-Test im Scratchpad (nicht im Repo) gegen die Test-App des Packages.

| AK | Ergebnis | Nachweis |
|---|---|---|
| AK1 — Standard aus | bestanden | Config-Standard `false` (`src/Config/consentlaw.php`), ENV `false`/`true` per `putenv` geprüft; ohne Schalter kein Formular in document/Kurzform/jobs/Slot-Fall (DOM-Prüfung, nicht nur String); `Route::has` false, echter POST durch Router → 404/405, 0 API-Aufrufe. `phpunit --filter NeuLadenButtonTest` grün. |
| AK2 — Nie auf Production | bestanden | `APP_ENV=production` + Schalter: kein Button (alle Typen, jobs, Slot), Route nicht registriert, POST → 404/405, 0 API-Aufrufe. Freigabe-Logik an einer Stelle (`src/Support/NeuLadenFreigabe.php`), genutzt von Route-Registrierung und Partial. Eigene Prüfung: `production` mit Schalter als String `'true'` → keine Route. Hinweis: Vergleich ist exakt (`environment('production')`); `APP_ENV=Production` oder `prod` gelten als Nicht-Production → Button aktiv (eigene Prüfung). Spec nennt nur `production`, daher kein Verstoß. |
| AK3 — Button sichtbar | bestanden | Umgebungen local/testing/staging: genau ein `<form method="post">` mit `action = route('consentlaw.neu-laden', typ)`, `_token` = Sitzungs-Token, `<button>` „Neu laden“, Klasse `consentlaw-refresh`; je Kurzform eigener Typ; jobs → `jobs`; Slot-Fall (404 und Störung ohne Fallback) für alle 6 Typen; leere Jobliste. |
| AK4 — Neu laden | **nicht bestanden** (Teilfall) | Standardfall bestanden: genau ein zusätzlicher API-Aufruf nur für den angeforderten Typ, Cache und Fallback-Datei aktualisiert, Sperrzeit umgangen (auch ohne vorhandenen Inhalt), 404-Vermerk umgangen, 302 auf Referer, Folge-Render zeigt neuen Stand ohne weiteren Aufruf. **Lücke:** Komponenten mit `locale`-Attribut (`<x-consentlaw::impressum locale="en" />`, ebenso jobs) zeigen den Button (AK3), der Klick lädt aber nur die Standardsprache neu. Eigene Prüfung: vorher „ALT-EN“ gecacht → POST 302, API-Aufruf für Standard-Locale, Seite zeigt danach weiterhin **ALT-EN**. Der Button ist dort sichtbar, aber wirkungslos — Widerspruch zu „die Seite zeigt danach den neuen Stand“. README dokumentiert das als Einschränkung; die Spec kennt diese Ausnahme nicht. |
| AK5 — Nur gültige Typen | bestanden | `unbekannt`, `privacy`, `wp-login.php`, `impressum-jobs`, `..` → 404, 0 API-Aufrufe, Cache unberührt (Vorbedingung „Route registriert“ im Test geprüft, 404 also nicht trivial). Whitelist `ConsentLawClient::DOCUMENT_TYPES` + `jobs` im Controller. |
| AK6 — Missbrauchsschutz | bestanden | Limiter `consentlaw-neu-laden` 6/min je IP: 7. Anfrage → 429 ohne API-Aufruf, andere IP → 302, nach 61 s wieder 302. CSRF echt geprüft (Tests laufen in `local`, wo `ValidateCsrfToken` nicht übersprungen wird): ohne Token, falsches Token, ohne Sitzung → 419, 0 API-Aufrufe. GET → 405. |

### Randfälle
| Randfall | Ergebnis | Nachweis |
|---|---|---|
| Störung beim Neu laden | bestanden mit Einschränkung | Störung, 503, 429 der API, fehlender Token (eigene Prüfung) → 302, keine Fehlerseite, bisheriger Stand aus Fallback sichtbar. **Einschränkung:** Bei `CONSENTLAW_FALLBACK_ENABLED=false` löscht `refresh()` zuerst den Cache; schlägt der Abruf fehl, ist der bisherige Stand weg und die Seite leer (eigene Prüfung: vorher „ALT-X“, nach Neu laden mit Störung „LEER“). Spec verlangt „bisheriger Stand bzw. Fallback“. Fallback ist standardmäßig an, daher nur bei abweichender Config. |
| Rückleitung nur auf eigene Seite | bestanden | Fremde Domain, Userinfo-Trick, Subdomain-Trick, protokollrelativ → Ziel bleibt `localhost`; Parameter `redirect/return/next/url/back/_previous` ignoriert; eigener Query bleibt. Eigene Prüfung: `http://localhost//boese.example/x`, Backslash-Pfad, `javascript://localhost/…`, fremder Port → Location immer `http://localhost/…` (Basis aus eigenem Host wird stets vorangestellt). |

### Nicht-Ziele
Nicht gebaut: kein Webhook, keine Production-Variante, Darstellung schlicht mit überschreibbarer Klasse. Eingehalten.

### Nicht prüfbar
- Echter Lauf in einer Host-App (Route im Browser, `storage/logs/laravel.log`, Browser-Konsole): keine App bindet diesen Branch ein (`it-hilbert.com` nutzt `^1.1` aus `vendor`, `packages_dev` lädt `dev-main` per VCS). Geprüft wurde ausschließlich über die Test-App des Packages mit echtem Router, Session- und CSRF-Middleware.
- PHPStan: in `packages_dev/vendor/bin` nicht installiert.

## Sicherheit
- **CSRF:** Middleware-Gruppe `web`, nur POST; echte Prüfung belegt (419 ohne/mit falschem Token).
- **Autorisierung:** Keine Nutzerprüfung — bewusst, Schutz über Schalter + Nicht-Production + Rate-Limit. Jeder Besucher einer Test-/Staging-Seite kann neu laden (max. 6/min/IP). Auf öffentlich erreichbaren Staging-Seiten ist das ein kleiner Hebel für API-Last; vertretbar, aber im README nicht als Risiko benannt.
- **Defense in depth:** Der Controller prüft `NeuLadenFreigabe` nicht selbst, sondern verlässt sich auf die Route-Registrierung beim Boot. Wird ein Route-Cache (`route:cache`) in einer Nicht-Production-Umgebung erzeugt und mit ausgerollt, wäre die Route auf Production vorhanden, obwohl AK2 sie ausschließt. Empfehlung: zusätzlich `abort_unless($freigabe->aktiv(), 404)` im Controller.
- **Validierung:** Typ per Whitelist, kein Durchreichen an Pfade; ungültig → 404 ohne API-Aufruf.
- **Open Redirect:** keiner gefunden (siehe Randfälle); Eingabeparameter werden nicht ausgewertet.
- **Ausgabe-Escaping:** `action` und Button-Text über `{{ }}`; `typ` stammt aus dem Komponenten-Attribut, ungültige Typen werfen schon in `document()`.
- **Rate-Limit je IP:** hinter einem Reverse Proxy ohne TrustProxies teilen sich alle Besucher eine IP — dann greift das Limit global. Hinweis für die README.
- **Mass-Assignment / Mandantentrennung:** nicht betroffen (keine Models, Token je Installation).

## Regressionen
- Volle Suite Branch: `../../vendor/bin/phpunit` → **OK (414 Tests, 1472 Assertions)**. Davon neu: 91 (NeuLadenButtonTest, 494 Assertions).
- Volle Suite `main`: OK (323 Tests). Alle 323 bestehenden Tests laufen auf dem Branch weiter grün, obwohl `tests/AppTestCase.php` umgebaut wurde (Facades vor Boot, Session, `web`-Gruppe).
- Pint (`--test`) auf geänderten PHP-Dateien: bestanden.
- Bestehendes Verhalten: Komponenten erhalten nur ein angehängtes `@include`, das ohne Freigabe nichts ausgibt. Wer Views veröffentlicht hat, bekommt den Button erst nach manueller Übernahme (im README unter „Upgrade auf 1.2“ beschrieben).

## Fazit: nicht freigegeben

Begründung: AK4 ist für Komponenten mit `locale`-Attribut nachweislich nicht erfüllt — der Button erscheint dort (AK3), wirkt aber nicht. Die Umsetzung hat das als README-Einschränkung festgeschrieben, statt die Spec-Lücke zu melden. Entscheidung nötig (Moderator/User):
1. Spec ergänzen (Ausnahme für `locale`-Komponenten, dann Button dort ausblenden) **oder**
2. Locale mitgeben (Feld im Formular, gegen erlaubtes Format validiert) und im Controller `->locale($locale)` nutzen.

Weitere Befunde (nicht blockierend, aber zu entscheiden):
- Randfall Störung bei abgeschaltetem Fallback löscht den bisherigen Stand (Cache vor Abruf verworfen).
- Fehlende Freigabe-Prüfung im Controller (Route-Cache-Szenario, AK2).
- PHPStan und echter Host-App-Lauf nicht geprüft.
