# Plan: neu-laden-rueckmeldung

Spec: [spec.md](spec.md) · Branch: `feature/neu-laden-rueckmeldung`

## Aufgaben
- [x] T0 TESTS: `test-writer` — Tests je AK1–AK10, Rot-Nachweis (27 rot, AK9 Schutztest grün).
- [x] T0b TESTS: `test-writer` — AK11 ergänzen, AK5-Test auf echte Komponenten-Seite umstellen.
- [x] T1 (Randfall Doppelaufruf) [pro] `Abrufergebnis` um den Zeitpunkt des Stands ergänzen; Endpoint-Methode „verwerfen + abrufen“ für Dokumente und Jobs; `consentlaw:reload` und `NeuLadenController` nutzen sie.
- [x] T2 (AK1–AK7, AK9) [pro] Controller legt Ergebnis (Status, Stand, Typ, Sprache) als Flash ab, nur wenn eine Session existiert; fehlender Token als eigener Fall.
- [x] T3 (AK1–AK8, AK10) [pro] Partial zeigt die Meldung passend zu Typ/Sprache, Styles für ok/fehler.
- [x] T4 (AK11) [pro] Komponenten `document` und `jobs` (und Partial) fangen `MissingTokenException` ab: leer bzw. Slot, Log-Warnung.
- [x] NACHWEIS: 665/665 grün, Tests seit Rot-Commit unverändert. Echter Lauf in einer Kopie von it-hilbert.com (Formular aus HTML, Cookies, kein Referer): Erfolg (/impressum, /datenschutz), Störung mit Fallback, ohne Token (Seite 200, Meldung, Log-Warnung), Meldung beim nächsten Aufruf weg. Kein Browser – Sichtprüfung durch User offen. Hinweis: Stand in app.timezone, it-hilbert.com hat UTC.
- [ ] REVIEW: `reviewer`.

## Stand
Umsetzung und NACHWEIS fertig, Review läuft.
