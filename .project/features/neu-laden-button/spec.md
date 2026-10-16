# Spec: Button „Neu laden“ (Release 1.2.0)

## Ziel
Wer Texte in consentLaw ändert, kann sie auf einer Entwicklungs- oder Test-Homepage per Klick sofort neu laden — ohne SSH und ohne auf den stündlichen Abruf zu warten.

## Akzeptanzkriterien
- **AK1 — Standard aus:** Gegeben `CONSENTLAW_REFRESH_BUTTON` ist nicht gesetzt oder `false`, wenn eine Dokument-Komponente (`<x-consentlaw::impressum />` usw. bzw. `<x-consentlaw::document />`) oder die Jobs-Komponente gerendert wird, dann erscheint kein Button, und die Neu-laden-Route ist nicht registriert (Aufruf → 404/405).
- **AK2 — Nie auf Production:** Gegeben `CONSENTLAW_REFRESH_BUTTON=true` und `APP_ENV=production`, dann gilt AK1 (kein Button, keine Route).
- **AK3 — Button sichtbar:** Gegeben `CONSENTLAW_REFRESH_BUTTON=true` und eine andere Umgebung als production, wenn eine Dokument- oder die Jobs-Komponente gerendert wird, dann erscheint ein Formular-Button „Neu laden“ (POST, mit CSRF-Token), der den Typ der Komponente übermittelt — auch wenn kein Inhalt verfügbar ist (Slot-Fall).
- **AK4 — Neu laden:** Gegeben AK3, wenn der Button abgeschickt wird, dann wird genau dieser Typ unter Umgehung von Cache und Sperrzeit frisch von consentLaw geholt (Cache und Fallback-Datei aktualisiert) und der Nutzer auf die vorherige Seite zurückgeleitet; die Seite zeigt danach den neuen Stand.
- **AK5 — Nur gültige Typen:** Gegeben ein POST mit unbekanntem Typ, dann antwortet die Route mit 404 (bzw. 422) ohne API-Aufruf.
- **AK6 — Missbrauchsschutz:** Gegeben mehr als 6 Neu-laden-Anfragen pro Minute von einer IP, dann antwortet die Route mit 429; ohne gültiges CSRF-Token wird nicht neu geladen.

## Randfälle
- Neu laden schlägt fehl (Störung) → Rückleitung wie AK4, es wird der bisherige Stand bzw. Fallback angezeigt; keine Fehlerseite.
- Rückleitung nur auf die eigene Seite (kein offener Redirect über Parameter).

## Nicht-Ziele
- Button auf Production (bewusst ausgeschlossen, siehe AK2; dort `consentlaw:warm` bzw. stündlicher Abruf).
- Automatisches Anstoßen durch consentLaw (Webhook).
- Gestaltung über eine schlichte, per Klasse überschreibbare Darstellung hinaus.
