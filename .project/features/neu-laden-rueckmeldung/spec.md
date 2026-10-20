# Spec: neu-laden-rueckmeldung

„Neu laden“ meldet, ob der Abruf geklappt hat. Bisher leitet der Button nur zurück; ob neue Daten geladen wurden oder consentLaw gestört war, sieht man nicht (Anlass: it-hilbert.com, Impressum).

Baut auf `neu-laden-app-test` (Schalter, Button oben) und `neu-laden-button` AK4a (Rückleitung) auf; beide gelten unverändert.

## Akzeptanzkriterien
Alle AKs: Gegeben `APP_TEST=true` und die Homepage hat eine Session (`StartSession` in der `web`-Gruppe), soweit nicht anders genannt. „Meldung“ = Element mit `role="status"` direkt beim Button, oberhalb des Inhalts.

- **AK1 — Erfolg:** Wenn „Neu laden“ ausgelöst wird und consentLaw den Inhalt liefert, dann landet der Nutzer wieder auf der Seite und sieht die Meldung „Neu geladen – Stand: TT.MM.JJJJ HH:MM“ (Zeitpunkt des Abrufs, Zeitzone der App).
- **AK2 — Störung mit Fallback:** Wenn consentLaw nicht erreichbar ist (Timeout, 5xx) und ein gespeicherter Stand existiert, dann lautet die Meldung „Neu laden fehlgeschlagen: consentLaw nicht erreichbar. Angezeigt wird der Stand vom TT.MM.JJJJ HH:MM.“
- **AK3 — Störung ohne Fallback:** Wie AK2, aber ohne gespeicherten Stand → „Neu laden fehlgeschlagen: consentLaw nicht erreichbar, kein gespeicherter Stand vorhanden.“
- **AK4 — Nicht veröffentlicht:** Wenn consentLaw mit 404 antwortet → „Neu laden fehlgeschlagen: Inhalt bei consentLaw nicht veröffentlicht oder Token ungültig.“
- **AK5 — Kein Token:** Wenn `CONSENTLAW_TOKEN` leer ist → „Neu laden fehlgeschlagen: kein Token konfiguriert (CONSENTLAW_TOKEN).“ Der Token-Wert erscheint in keiner Meldung.
- **AK6 — Einmalig:** Wenn die Seite danach erneut aufgerufen wird (ohne „Neu laden“), dann ist die Meldung weg.
- **AK7 — Nur am betroffenen Inhalt:** Stehen mehrere Komponenten auf einer Seite (z. B. Impressum und Jobs), dann erscheint die Meldung nur bei der Komponente, deren Button ausgelöst wurde (gleicher Typ und gleiche Sprachvariante).
- **AK8 — Darstellung:** Erfolg und Fehler sind optisch unterscheidbar (z. B. grün/rot), dezent im Stil des Buttons, Styles im Partial (Klassen `consentlaw-refresh-meldung`, `…--ok`, `…--fehler`, überschreibbar). Meldungstext wird escaped ausgegeben.
- **AK9 — Ohne Session:** Gegeben die Homepage hat keine Session, wenn „Neu laden“ ausgelöst wird, dann funktioniert es wie bisher (Rückleitung, Abruf) ohne Meldung und ohne Fehler.
- **AK10 — Schalter aus:** Gegeben `APP_TEST` aus, dann erscheint weder Button noch Meldung, auch wenn noch eine Meldung in der Session liegt.
- **AK11 — Seite ohne Token** *(ergänzt nach T0, User 2026-10-20)*: Gegeben `CONSENTLAW_TOKEN` ist leer, wenn eine Dokument-Komponente (`<x-consentlaw::impressum />` usw., `<x-consentlaw::document />`) oder die Jobs-Komponente gerendert wird, dann antwortet die Seite mit 200 statt 500: Die Komponente verhält sich wie „kein Inhalt verfügbar“ (leer bzw. Slot), ohne API-Aufruf, und es wird eine Warnung ins Laravel-Log geschrieben („CONSENTLAW_TOKEN fehlt“). Mit `APP_TEST=true` erscheint der Button, und nach „Neu laden“ ist die Meldung aus AK5 auf dieser Seite sichtbar. Gilt in jeder Umgebung.

## Randfälle
- Rate-Limit (mehr als 6 Aufrufe/min) → 429 wie bisher, keine Meldung.
- Die Meldung nennt keine Exception-Texte, URLs oder Token — nur die festen Texte oben.
- Der Konsolen-Befehl `consentlaw:reload` und der Button nutzen denselben Ablauf „verwerfen + abrufen“ (eine Stelle, Execution-Veto Doppelaufruf).

## Nicht-Ziele
- Keine Meldung per JavaScript/AJAX, kein Neuladen ohne Seitenwechsel.
- Kein dauerhaft sichtbarer „Stand“ ohne vorheriges „Neu laden“ (der bestehende Stale-Hinweis bleibt, wie er ist).
- Die PHP-API (`ConsentLaw::document()`, `jobs()`) wirft ohne Token weiterhin `MissingTokenException`; nur die Blade-Komponenten fangen ab.
- Keine Störungsmail bei fehlendem Token (Konfigurationsfehler; `consentlaw:reload` endet ohne Token bereits mit FAILURE).
- Keine Übersetzung der Meldungen über Deutsch hinaus (Texte laufen über `__()`, übersetzbar bleibt es).
