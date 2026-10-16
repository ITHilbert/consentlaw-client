# Plan: zeilenumbruch-token

Spec: [spec.md](spec.md) · Branch: `feature/zeilenumbruch-token` · Ziel-Release: `1.1.0`

## Hinweise
- AK1: im zentralen `Support\MarkdownRenderer` (CommonMark-Option `renderer.soft_break` = `"<br />\n"`), keine zweite Render-Stelle.
- AK2: Maskierung an einer Stelle (z. B. im `HttpTransport` beim Bau der Meldungs-URL); auch Log-Kontexte im `ContentResolver` prüfen. Tests über einen Log-Fake in `tests/AppTestCase.php`.
- Agent-Hooks greifen in diesem Repo nicht → Moderator prüft „Tests unverändert“ per `git diff`.

## Aufgaben
- [ ] T0 TESTS: `test-writer` — AK1, AK2, Rot-Nachweis.
- [ ] T1 (AK1, AK2) [pro] Soft-Break im Renderer, Token-Maskierung; README (Format, Sicherheit), Upgrade-Hinweis 1.1.
- [ ] NACHWEIS · REVIEW · Release-Tag `1.1.0`

## Stand
Spec mit dem User abgestimmt (Chat 2026-10-16). Nächster Schritt: TESTS.
