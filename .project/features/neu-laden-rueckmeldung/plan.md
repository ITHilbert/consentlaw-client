# Plan: neu-laden-rueckmeldung

Spec: [spec.md](spec.md) · Branch: `feature/neu-laden-rueckmeldung`

## Aufgaben
- [ ] T0 TESTS: `test-writer` — Tests je AK1–AK10, Rot-Nachweis.
- [ ] T1 (Randfall Doppelaufruf) [pro] `Abrufergebnis` um den Zeitpunkt des Stands ergänzen; Endpoint-Methode „verwerfen + abrufen“ für Dokumente und Jobs; `consentlaw:reload` und `NeuLadenController` nutzen sie.
- [ ] T2 (AK1–AK7, AK9) [pro] Controller legt Ergebnis (Status, Stand, Typ, Sprache) als Flash ab, nur wenn eine Session existiert; fehlender Token als eigener Fall.
- [ ] T3 (AK1–AK8, AK10) [pro] Partial zeigt die Meldung passend zu Typ/Sprache, Styles für ok/fehler.
- [ ] NACHWEIS: Suite; echter Lauf in it-hilbert.com (Formular aus HTML, Cookies, kein Referer von Hand): Erfolg, Token leer, Meldung nach erneutem Aufruf weg.

## Stand
Spec freigegeben (2026-10-20), T0 TESTS läuft.
