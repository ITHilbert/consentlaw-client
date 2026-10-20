# Spec: „Neu laden“ über APP_TEST, auch auf Production

## Ziel
Ein zentraler Hilfen-Schalter statt eigener Schalter je Package: Der „Neu laden“-Button hängt an `APP_TEST`. Er ist auch auf Production nutzbar (Fehler im Rechtstext korrigiert → kurz `APP_TEST=true`, neu laden, wieder `false`). Er steht oben vor dem Inhalt und sieht auf jeder Homepage gleich aus.

Ersetzt AK1, AK2, AK3 und AK7 aus `neu-laden-button`; AK4–AK6 gelten unverändert weiter.

## Akzeptanzkriterien
- **AK1 — Standard aus:** Gegeben `APP_TEST` ist nicht gesetzt oder `false`, wenn eine Dokument-Komponente (`<x-consentlaw::impressum />` usw. bzw. `<x-consentlaw::document />`) oder die Jobs-Komponente gerendert wird, dann erscheint kein Button, und ein POST auf die Neu-laden-Route antwortet mit 404 ohne API-Aufruf — in jeder Umgebung. *(geändert nach Review 1: Die Route darf registriert sein, siehe AK10.)*
- **AK2 — Auch auf Production:** Gegeben `APP_TEST=true` und `APP_ENV=production`, dann erscheint der Button (AK3) und Neu laden funktioniert (AK4).
- **AK3 — Button oben:** Gegeben `APP_TEST=true`, wenn eine Dokument- oder die Jobs-Komponente gerendert wird, dann steht im HTML **vor** dem Inhalt (bzw. vor dem Slot, wenn kein Inhalt verfügbar ist) ein Formular-Button „Neu laden“ (POST, mit CSRF-Feld), der den Typ der Komponente übermittelt.
- **AK7 — Freigabe auch in der Aktion:** Gegeben die Route existiert (z. B. durch einen Route-Cache mit `APP_TEST=true`), aber `APP_TEST` ist jetzt aus, wenn sie aufgerufen wird, dann antwortet sie mit 404 ohne API-Aufruf.
- **AK8 — Ein Schalter:** Gegeben `CONSENTLAW_REFRESH_BUTTON=true` und `APP_TEST` nicht gesetzt, dann gilt AK1. Der Config-Schlüssel `consentlaw.refresh_button` bezieht seinen Standard aus `APP_TEST`; ein eigener ENV-Schalter des Packages existiert nicht mehr.
- **AK9 — Eigene Darstellung:** Gegeben AK3, dann bringt der Button seine Darstellung selbst mit (Styles für `.consentlaw-refresh` im Partial, unabhängig vom CSS der Homepage): dezent, klein, als Entwicklungswerkzeug erkennbar. Die Klasse `consentlaw-refresh` bleibt, damit eine Homepage überschreiben kann.
- **AK10 — Unabhängig vom Route-Cache** *(ergänzt nach Review 1)*: Route und Rate-Limiter werden immer registriert, unabhängig vom Schalter. Gegeben ein Route-Cache, der mit beliebigem Schalterstand gebaut wurde, wenn `APP_TEST` danach an ist, dann erscheint der Button und Neu laden funktioniert; wenn `APP_TEST` danach aus ist, dann antwortet ein POST mit 404 ohne API-Aufruf — nie mit 500.

## Randfälle
- **Config-Cache:** Läuft die Homepage mit `config:cache`/`optimize`, wirkt eine Änderung an `APP_TEST` erst nach `php artisan config:clear` (bzw. neuem Cache). Ein Route-Cache spielt keine Rolle (AK10). README-Hinweis.
- **Homepage ohne Session/CSRF** (z. B. it-hilbert.com: `StartSession`/`VerifyCsrfToken` in der `web`-Gruppe aus): Der CSRF-Schutz aus AK6 greift dort nicht. Solange `APP_TEST` an ist, kann auch eine fremde Seite das Neu laden auslösen. Folge: nur zusätzliche API-Abrufe, begrenzt auf 6/min/IP. README-Hinweis; Behebung ist Sache der Homepage.
- **Besucher auf Production:** Solange `APP_TEST` an ist, sehen alle Besucher den Button. Gewollt für ein kurzes Zeitfenster.
- `APP_TEST` schaltet in einer Homepage ggf. weitere Hilfen ein — gewollt (ein Schalter für alle Hilfen).

## Nicht-Ziele
- Neuer Artisan-Command: `consentlaw:warm` verwirft Cache und Sperrvermerk für alle Typen und lädt frisch — das ist der Weg ohne Button.
- Anzeige eines Stand-Zeitstempels am Button.
- Anpassungen in den Homepages (`.env.example`, `env('APP_TEST')` in Layouts) — eigener Schritt je Homepage.
