# Plan: review-reste

Spec: [spec.md](spec.md) · Branch: `feature/review-reste`

## Aufgaben
- [x] T1 (AK1, AK2, AK3, Randfall 1, Randfall 2) test-writer: Tests schreiben, rot nachweisen — Tests: tests/ReviewResteTest.php
- [ ] T2 (AK1, AK2, AK3) [pro] `Document::contentHtml()` und Nutzung in beiden Document-Views; Paket-Jobs-Seite mit genau einem Abruf; README `with-schema` — Tests: tests/ReviewResteTest.php
- [ ] T3 NACHWEIS (Moderator): GATE, ganze Suite, Probe in it-hilbert.com (`/impressum`, `/jobs`)
- [ ] T4 Merge, Push, Index „Fertig“ (kein Release-Tag ohne Ansage des Users)

## Stand
Spec und Plan angelegt (2026-10-27), Auftrag des Users aus it-hilbert.com: ToDo abarbeiten nach eigenem Ermessen – Spec gilt damit als freigegeben.
T1 erledigt: ReviewResteTest 21 Tests, 13 rot (fehlendes Verhalten), 8 grün (Bestandsschutz), selbst nachgeprüft.
