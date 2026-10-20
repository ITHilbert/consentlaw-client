# Plan: neu-laden-app-test

Spec: [spec.md](spec.md) · Branch: `feature/neu-laden-app-test`

## Hinweise
- `src/Config/consentlaw.php`: `'refresh_button' => env('APP_TEST', false)`; `CONSENTLAW_REFRESH_BUTTON` entfällt.
- `NeuLadenFreigabe::aktiv()` bleibt die einzige Entscheidungsstelle, ohne Production-Prüfung.
- Partial `components/partials/neu-laden.blade.php` in `document.blade.php` und `jobs.blade.php` an den Anfang verschieben (vor Inhalt und Slot); Styles im Partial.
- Bestehende Tests in `tests/NeuLadenButtonTest.php` zu AK1/AK2/AK3/AK7 widersprechen der neuen Spec → test-writer passt sie an.
- Agent-Hooks greifen in diesem Repo nicht → Moderator prüft „Tests unverändert“ per `git diff`.

## Aufgaben
- [x] T0 TESTS: `test-writer` — AK1, AK2, AK3, AK7, AK8, AK9; AK4–AK6 weiter grün; Rot-Nachweis.
- [x] T1 (AK1–AK3, AK7–AK9) [pro] Config, Freigabe ohne Production-Sperre, Partial nach oben + Styles, README (Schalter, Config-Cache, CSRF-Hinweis, Changelog).
- [x] NACHWEIS · REVIEW Runde 1: nicht freigegeben (AK7 → 500 bei Route-Cache; Einschalten wirkt bei Route-Cache nicht; Tests hängen an Shell-`APP_TEST`; README „Upgrade auf 1.2“ rückwirkend geändert).
- [x] T2 TESTS: `test-writer` — AK1 (neu: 404 statt „nicht registriert“), AK10 (Route-Cache-Szenario: Route/Limiter beim Boot unabhängig vom Schalter), `AppTestCase` leert `APP_TEST`.
- [ ] T3 (AK1, AK7, AK10) [pro] Route + Limiter immer registrieren, Freigabe nur in Aktion und Partial; README „Upgrade auf 1.2“ auf Stand `main` zurück, Neues nur unter „Unveröffentlicht“.
- [ ] NACHWEIS · REVIEW Runde 2 · DOKU
- [ ] Danach it-hilbert.com (eigener Branch): `.env.example` ohne `CONSENTLAW_REFRESH_BUTTON`, `env('APP_TEST')` in Layouts → `config(...)`.

## Stand
Review 1 nicht freigegeben, Spec um AK10 ergänzt, rote Tests T2 committet (521 Tests, 15 rot). Als Nächstes T3 [pro].
