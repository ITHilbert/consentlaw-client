# Spec: Consent-Banner als Komponente

## Ziel
Eine Homepage bindet den Consent-Banner von consentLaw mit einer Zeile im Layout ein, ohne den Token oder die Script-Adresse selbst zusammenzubauen. Ein Link „Cookie-Einstellungen“ öffnet den Banner erneut. Das Script selbst bleibt bei consentLaw und wird von dort geladen (User 2026-10-26).

## Hintergrund
- consentLaw liefert den Banner als `<script src="{CONSENTLAW_URL}/banner.js?token={api_token}" defer></script>` aus (Snippet im consentLaw-Dashboard, Modul `banner`). Der Token ist derselbe Domain-Token wie für die Rechtstexte (`CONSENTLAW_TOKEN`).
- `banner.js` stellt `window.CookieScript.open()` bereit. Ein Aufruf vor dem Laden der Banner-Einstellungen wird vorgemerkt und danach ausgeführt.

## Akzeptanzkriterien
- **AK1 — Script-Tag:** Gegeben `CONSENTLAW_TOKEN` ist gesetzt und der Banner ist eingeschaltet, wenn `<x-consentlaw::banner />` gerendert wird, dann enthält die Ausgabe genau ein `<script>` mit `src="{base_url}/banner.js?token={token}"` und dem Attribut `defer`. `base_url` steht ohne abschließenden `/` (auch wenn `CONSENTLAW_URL` mit `/` endet), der Token ist URL-kodiert und HTML-maskiert.
- **AK2 — Ohne Token nichts:** Gegeben `CONSENTLAW_TOKEN` ist leer oder nicht gesetzt, wenn `<x-consentlaw::banner />` gerendert wird, dann ist die Ausgabe leer (kein Script, kein Fehler, keine Exception).
- **AK3 — Schalter:** Gegeben `CONSENTLAW_BANNER_ENABLED=false`, wenn `<x-consentlaw::banner />` gerendert wird, dann ist die Ausgabe leer, auch bei gesetztem Token. Ist die Variable nicht gesetzt, ist der Banner eingeschaltet. Der Wert steht in der Konfiguration unter `consentlaw.banner.enabled`.
- **AK4 — Kein API-Aufruf:** Gegeben AK1, AK2 oder AK3, wenn die Komponente gerendert wird, dann ruft der Client die consentLaw-API nicht auf (kein HTTP-Request, kein Cache-, kein Fallback-Zugriff).
- **AK5 — Link „Cookie-Einstellungen“:** Gegeben der Banner ist laut AK1 aktiv, wenn `<x-consentlaw::cookie-einstellungen />` gerendert wird, dann entsteht ein Link mit dem Text „Cookie-Einstellungen“, der beim Klick `window.CookieScript.open()` aufruft, sofern `window.CookieScript` existiert, und die Seite weder neu lädt noch zum Seitenanfang springt. Mit Slot ersetzt der Slot den Text; übergebene Attribute (z. B. `class`) landen am Link.
- **AK6 — Link nur bei aktivem Banner:** Gegeben kein Token (AK2) oder `CONSENTLAW_BANNER_ENABLED=false` (AK3), wenn `<x-consentlaw::cookie-einstellungen />` gerendert wird, dann ist die Ausgabe leer.
- **AK7 — README:** Die README beschreibt beide Komponenten, den Schalter `CONSENTLAW_BANNER_ENABLED` und dass `banner.js` von consentLaw geladen wird.

## Randfälle
- **Randfall 1:** `CONSENTLAW_URL` mit abschließendem `/` → kein doppelter `/` vor `banner.js` (AK1).
- **Randfall 2:** Komponente zweimal im Layout → zweimal ausgegeben. Verhindern ist Sache des Layouts, nicht der Komponente.
- **Randfall 3:** Link geklickt, bevor `banner.js` geladen ist → `window.CookieScript` fehlt, der Klick tut nichts und erzeugt keinen JavaScript-Fehler (AK5).

## Nicht-Ziele
- `banner.js` oder die Banner-Einstellungen über die eigene Domain ausliefern (Proxy) – vom User verworfen (2026-10-26).
- Preconnect/Preload auf consentlaw.eu.
- Script-Manager, Google-Tags, Consent-Mode – das ist Schritt 3 in it-hilbert.com.
- Änderungen an `banner.js` in consentLaw.

## Entscheidungsspielraum
- Keine neuen Composer- oder npm-Pakete.
- Anonyme Blade-Komponenten wie die bestehenden; ob für die Bedingung „Banner aktiv“ (AK1–AK3, AK6) eine kleine Support-Klasse entsteht, entscheidet der Coder. Die Bedingung steht aber nur an einer Stelle.
- Markup des Links (`<a href="#">` oder `<button>`) frei, solange AK5 erfüllt ist.
