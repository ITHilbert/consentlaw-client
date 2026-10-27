# Plan: review-reste

Spec: [spec.md](spec.md) · Branch: `feature/review-reste`

## Aufgaben
- [x] T1 (AK1, AK2, AK3, Randfall 1, Randfall 2) test-writer: Tests schreiben, rot nachweisen — Tests: tests/ReviewResteTest.php
- [x] T2 (AK1, AK2, AK3) [pro] `Document::contentHtml()` und Nutzung in beiden Document-Views; Paket-Jobs-Seite mit genau einem Abruf; README `with-schema` — Tests: tests/ReviewResteTest.php
- [x] T3 NACHWEIS (Moderator): GATE, ganze Suite, Probe in it-hilbert.com (`/impressum`, `/jobs`)
- [ ] T4 Merge, Push, Index „Fertig“ (kein Release-Tag ohne Ansage des Users)

## Stand
Spec und Plan angelegt (2026-10-27), Auftrag des Users aus it-hilbert.com: ToDo abarbeiten nach eigenem Ermessen – Spec gilt damit als freigegeben.
T1 erledigt: ReviewResteTest 21 Tests, 13 rot (fehlendes Verhalten), 8 grün (Bestandsschutz), selbst nachgeprüft.
T2/T3 erledigt: coder-pro meldete HANDOFF (alter `MarkdownAusgabeTest` verlangte `MarkdownRenderer::render(` im Blade – widerspricht AK1) → test-writer passte den Test an (`Data/Document.php` als einzige Stelle). GATE dadurch „nicht geprüft“; selbst: ganze Paket-Suite 837 grün, Pint ok, Diff gesichtet (`contentHtml()`, Controller holt keine Stellen mehr, README-Absatz `with-schema`).
