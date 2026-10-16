# Plan: robustheit

Spec: [spec.md](spec.md) · Branch: `feature/robustheit` · Repo: `ithilbert/consentlaw-client`

## Hinweise
- Tests laufen mit dem `vendor/` von packages_dev: `../../vendor/bin/phpunit` im Package-Ordner (kein Testbench vorhanden — Laravel-Teile wie Cache `ArrayStore`, Filesystem, Facades selbst aufsetzen).
- Die Agent-Hooks (Schreibbereich, GATE) greifen hier nicht (Projekt-Root ist consentLaw) → der Moderator prüft „Tests unverändert“ per `git diff` selbst.
- Markdown-Rendering: zentral (z. B. `Support\MarkdownRenderer` o. ä.), `html_input => strip`, `allow_unsafe_links => false`, `<br>`-Varianten vorher in harte Zeilenumbrüche wandeln. Alle vier Aufrufer (document-Komponente, document-Seite, jobs-Komponente inkl. JSON-LD) nutzen ihn.
- AK3: Sperrvermerk je Key im Cache-Store (unabhängig von `cache.enabled`), Dauer `fallback.retry_after`.

## Aufgaben
- [x] T0 TESTS: `test-writer` — AK1–AK7, Rot-Nachweis.
- [x] T1 (AK1–AK3, AK6) [pro] ContentResolver/HttpTransport: 404 vs. übrige Fehler, Sperrvermerk bei Störung, Standard-URL.
- [x] T2 (AK4, AK5) [pro] Zentraler Markdown-Renderer, vier Aufrufer umstellen.
- [x] T3 (AK7) [pro] `consentlaw:warm` unterscheidet „nicht veröffentlicht“ von Störung.
- [x] REVIEW Runde 1 freigegeben, Ergänzung AK8–AK10 vom User beauftragt.
- [x] T4 TESTS: `test-writer` — AK8–AK10 (+ Pint-Stil in tests/AppTestCase.php), Rot-Nachweis.
- [x] T5 (AK8–AK10) [pro] 404-Cache ohne Retry, warm „alle 404 → Token prüfen“, Formaterkennung ausbauen; Changelog-Hinweis `ContentResolver::resolve()` liefert `Abruf`.
- [ ] NACHWEIS · REVIEW · DOKU (README) · Release-Tag `1.0.0` (Homepages binden `^1.0` statt `dev-main`)

## Stand
T0–T5 erledigt, 136/136 Tests grün. Nächster Schritt: REVIEW Runde 2, danach Release 1.0.0.
