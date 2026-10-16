# Spec: Robustheit & sichere Ausgabe (consentlaw-client)

## Ziel
Rechtstexte und Jobs sind auf der Kunden-Homepage **immer** verfügbar, die Seite wird bei einem consentLaw-Ausfall nicht langsam, und Inhalte aus consentLaw können kein Skript in die Homepage einschleusen.

## Ausgangslage
- Bei einer Störung wird der Fallback-Stand geliefert, aber nicht gecacht → nach Ablauf der TTL wartet **jeder** Seitenaufruf erneut auf den API-Timeout (5 s + Retry).
- Jede 4xx-Antwort (auch 429 durch das neue Rate-Limit von consentLaw) gilt als „nicht veröffentlicht“ → kein Fallback, Inhalt verschwindet.
- Markdown wird an vier Stellen per `Str::markdown()` ohne Einschränkung gerendert → rohes HTML (`<script>`, `onerror`) aus consentLaw landet ungefiltert auf der Seite. Job-Beschreibungen filtert consentLaw ausdrücklich nicht.
- Standard-URL `https://consentlaw.eu` antwortet mit 301 auf `https://www.consentlaw.eu`.
- `consentlaw:warm` endet mit FAILURE, sobald ein Typ für die Domain nicht veröffentlicht ist (z. B. Kunde ohne AGB) → Deploy-Skripte scheitern.
- Für den Kernablauf (Cache → API → Fallback) gibt es keine Tests.

## Akzeptanzkriterien

- **AK1 — Kernablauf abgesichert (bestehendes Verhalten):**
  - a) Gegeben ein frischer Cache-Eintrag, wenn ein Inhalt abgerufen wird, dann erfolgt kein HTTP-Aufruf.
  - b) Gegeben kein Cache-Eintrag und eine erfolgreiche API-Antwort, wenn abgerufen wird, dann wird der Inhalt geliefert, gecacht und als Fallback-Datei gespeichert.
  - c) Gegeben eine Störung (Timeout/Verbindungsfehler/5xx) und eine Fallback-Datei, wenn abgerufen wird, dann wird der Fallback-Inhalt geliefert und als veraltet (`isStale`) markiert.
  - d) Gegeben eine 404-Antwort, wenn abgerufen wird, dann wird nichts geliefert (null bzw. leere Job-Liste) — auch wenn eine Fallback-Datei existiert.
- **AK2 — Nur 404 heißt „nicht veröffentlicht“:** Gegeben eine Antwort mit einem anderen Fehlerstatus als 404 (z. B. 429, 401, 403) und eine Fallback-Datei, wenn abgerufen wird, dann wird wie bei einer Störung der Fallback geliefert (AK1c).
- **AK3 — Kein Dauer-Warten bei Ausfall:** Gegeben eine Störung (AK1c oder AK2), wenn innerhalb der Wartezeit `consentlaw.fallback.retry_after` (Standard 300 s) erneut abgerufen wird, dann erfolgt kein HTTP-Aufruf, sondern der Fallback wird direkt geliefert — auch wenn kein Fallback existiert (dann direkt nichts). Nach Ablauf der Wartezeit wird die API erneut gefragt.
- **AK4 — Sichere Markdown-Ausgabe:** Gegeben Markdown-Inhalt (Dokument oder Job-Beschreibung) mit rohem HTML (`<script>`, `<iframe>`, `<img onerror=…>`) oder einem `javascript:`-Link, wenn er gerendert wird (Komponente, Fertig-Seite, JSON-LD der Jobs), dann enthält die Ausgabe weder das Tag noch den Event-Handler noch den `javascript:`-Link. `<br>`, `<br/>`, `<br />` im Markdown werden zu einem Zeilenumbruch. Das Rendern geschieht an **einer** Stelle im Package.
- **AK5 — Format aus der API:** Gegeben eine Antwort mit `"format": "markdown"` bzw. `"html"`, wenn das Dokument erzeugt wird, dann gilt dieses Format, ohne Erkennung am Inhalt. *(bestehendes Verhalten, absichern)*
- **AK6 — Standard-URL:** Gegeben keine `CONSENTLAW_URL`, wenn der Client eine Anfrage baut, dann geht sie an `https://www.consentlaw.eu`.
- **AK7 — warm scheitert nur bei echten Problemen:** Gegeben `consentlaw:warm`, wenn ein Typ nicht veröffentlicht ist (404), dann wird er als Hinweis gemeldet, der Befehl endet aber mit SUCCESS. FAILURE nur, wenn ein Typ wegen einer Störung weder von der API noch aus dem Fallback geliefert werden konnte, oder ohne Token.

## Randfälle
- Fallback-Datei vorhanden, aber beschädigt (kein JSON) → wie „kein Fallback“, keine Exception.
- Cache deaktiviert (`cache.enabled=false`) → AK3 gilt trotzdem (Wartezeit hängt nicht am Inhalts-Cache).
- HTML-Format (`format: html`, alte Daten) wird nicht durch den Markdown-Renderer geschickt; consentLaw filtert HTML beim Speichern.

## Nicht-Ziele
- Sprachauswahl (`locale`) — consentLaw ignoriert sie (Entscheidung 0002 dort); Ausbau im Client eigenes Thema.
- Scheduler-Eintrag für `consentlaw:warm` — gehört ins Kundenprojekt.
- HTML-Sanitizing für `format: html` im Client.
