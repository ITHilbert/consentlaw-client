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
- [x] REVIEW 1: freigegeben (pruefung.md). Hinweise: refresh() doppelt, KomponentenInhalt statisch, Seiten-Controller ohne Token 500, unlesbarer Stand → 500. User: AK12, AK13 aufnehmen.
- [ ] T0c TESTS: `test-writer` — AK12, AK13, Rot-Nachweis.
- [ ] T5 (Randfälle) [pro] `refresh()` delegiert an `neuLaden()`; Token-Abfangen als injizierbare Klasse statt statischem Helfer, genutzt von Komponenten und Seiten-Controller.
- [ ] T6 (AK12, AK13) [pro] Seiten-Controller ohne Token; unlesbaren Stand abfangen.
- [ ] NACHWEIS 2, REVIEW 2.

## Stand
Review 1 freigegeben, AK12/AK13 ergänzt, T0c läuft.
