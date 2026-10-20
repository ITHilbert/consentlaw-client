# Spec: Button „Neu laden“ (Release 1.2.0)

## Ziel
Wer Texte in consentLaw ändert, kann sie auf einer Entwicklungs- oder Test-Homepage per Klick sofort neu laden — ohne SSH und ohne auf den stündlichen Abruf zu warten.

## Akzeptanzkriterien
- **AK1 — Standard aus:** Gegeben `CONSENTLAW_REFRESH_BUTTON` ist nicht gesetzt oder `false`, wenn eine Dokument-Komponente (`<x-consentlaw::impressum />` usw. bzw. `<x-consentlaw::document />`) oder die Jobs-Komponente gerendert wird, dann erscheint kein Button, und die Neu-laden-Route ist nicht registriert (Aufruf → 404/405).
- **AK2 — Nie auf Production:** Gegeben `CONSENTLAW_REFRESH_BUTTON=true` und `APP_ENV=production`, dann gilt AK1 (kein Button, keine Route).
- **AK3 — Button sichtbar:** Gegeben `CONSENTLAW_REFRESH_BUTTON=true` und eine andere Umgebung als production, wenn eine Dokument- oder die Jobs-Komponente gerendert wird, dann erscheint ein Formular-Button „Neu laden“ (POST, mit CSRF-Token), der den Typ der Komponente übermittelt — auch wenn kein Inhalt verfügbar ist (Slot-Fall).
- **AK4 — Neu laden:** Gegeben AK3, wenn der Button abgeschickt wird, dann wird genau dieser Typ unter Umgehung von Cache und Sperrzeit frisch von consentLaw geholt (Cache und Fallback-Datei aktualisiert) und der Nutzer auf die vorherige Seite zurückgeleitet; die Seite zeigt danach den neuen Stand. Hat die Komponente ein `locale`-Attribut, übermittelt der Button diese Sprache (verstecktes Feld) und es wird genau diese Sprachvariante neu geladen *(ergänzt nach Review)*. Eine ungültige Sprachangabe (nicht `^[a-z]{2}([_-][A-Za-z]{2})?$`) → 422 ohne API-Aufruf.
  - **AK4a — Rückleitung ohne Referer** *(ergänzt 2026-10-20, Fehler: Homepage mit `<meta name="referrer" content="no-referrer">` landete auf `/`)*: Der Button übermittelt die Seite, auf der er gerendert wurde, als verstecktes Feld `zurueck` (Pfad + Query der aktuellen Anfrage). Die Rückleitung geht auf diesen Pfad – auch wenn der Browser keinen Referer sendet. Akzeptiert wird nur ein lokaler Pfad: beginnt mit genau einem `/`, kein `//` oder `/\`, kein Schema, kein Host, keine Steuerzeichen. Fehlt `zurueck` oder ist es ungültig, gilt der bisherige Weg (Referer vom eigenen Host, sonst `/`).
- **AK5 — Nur gültige Typen:** Gegeben ein POST mit unbekanntem Typ, dann antwortet die Route mit 404 (bzw. 422) ohne API-Aufruf.
- **AK7 — Freigabe auch in der Aktion:** Gegeben die Route existiert (z. B. durch einen in anderer Umgebung erzeugten Route-Cache), aber die Freigabe ist nicht aktiv (Schalter aus oder production), wenn sie aufgerufen wird, dann antwortet sie mit 404 ohne API-Aufruf *(ergänzt nach Review)*.
- **AK6 — Missbrauchsschutz:** Gegeben mehr als 6 Neu-laden-Anfragen pro Minute von einer IP, dann antwortet die Route mit 429; ohne gültiges CSRF-Token wird nicht neu geladen.

## Randfälle
- Neu laden schlägt fehl (Störung) → Rückleitung wie AK4, es wird der bisherige Stand bzw. Fallback angezeigt; keine Fehlerseite.
- Rückleitung nur auf die eigene Seite (kein offener Redirect): `zurueck` wird nur als lokaler Pfad akzeptiert (AK4a), ein fremder Referer wird verworfen.
- Fallback per `.env` abgeschaltet und Neu-laden scheitert → die Seite kann bis zum nächsten erfolgreichen Abruf ohne Inhalt sein (bekannte Grenze; Standard: Fallback an).

## Nicht-Ziele
- Button auf Production (bewusst ausgeschlossen, siehe AK2; dort `consentlaw:warm` bzw. stündlicher Abruf).
- Automatisches Anstoßen durch consentLaw (Webhook).
- Gestaltung über eine schlichte, per Klasse überschreibbare Darstellung hinaus.
