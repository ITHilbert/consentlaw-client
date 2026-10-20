# Plan: fix-rueckleitung

Spec: [../neu-laden-button/spec.md](../neu-laden-button/spec.md) (AK4a) · Branch: `fix/rueckleitung-ohne-referer`

## Fehlerbild
In it-hilbert.com (Layout mit `no-referrer`) führt „Neu laden“ auf `/` statt zurück auf die Seite. Ursache: Rückleitung nur per Referer.

## Aufgaben
- [x] T0 TESTS: `test-writer` — Regressionstest AK4a (POST ohne Referer → Seite), Sicherheitsfälle, Rot-Nachweis.
- [ ] T1 (AK4a) [pro] Feld `zurueck` im Partial, Prüfung im Controller.
- [ ] NACHWEIS (curl ohne Referer, Formular aus gerendertem HTML; Browser durch User)

## Stand
Rote Regressionstests committet (646 Tests, 21 rot). Als Nächstes T1 [pro].
