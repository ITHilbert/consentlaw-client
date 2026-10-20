# Plan: fix-rueckleitung

Spec: [../neu-laden-button/spec.md](../neu-laden-button/spec.md) (AK4a) · Branch: `fix/rueckleitung-ohne-referer`

## Fehlerbild
In it-hilbert.com (Layout mit `no-referrer`) führt „Neu laden“ auf `/` statt zurück auf die Seite. Ursache: Rückleitung nur per Referer.

## Aufgaben
- [x] T0 TESTS: `test-writer` — Regressionstest AK4a (POST ohne Referer → Seite), Sicherheitsfälle, Rot-Nachweis.
- [x] T1 (AK4a) [pro] Feld `zurueck` im Partial, Prüfung im Controller.
- [x] NACHWEIS: 646/646 grün; echter Lauf it-hilbert.com (no-referrer): Formular aus HTML, POST ohne Referer → /impressum bzw. /datenschutz?x=1. Browser-Sichtprüfung durch User offen.

## Stand
Fertig, in main gemergt und gepusht, kein Release.
