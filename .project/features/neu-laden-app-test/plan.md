# Plan: neu-laden-app-test

Spec: [spec.md](spec.md) · Branch: `feature/neu-laden-app-test`

## Hinweise
- `src/Config/consentlaw.php`: `'refresh_button' => env('APP_TEST', false)`; `CONSENTLAW_REFRESH_BUTTON` entfällt.
- `NeuLadenFreigabe::aktiv()` bleibt die einzige Entscheidungsstelle, ohne Production-Prüfung.
- Partial `components/partials/neu-laden.blade.php` in `document.blade.php` und `jobs.blade.php` an den Anfang verschieben (vor Inhalt und Slot); Styles im Partial.
- Bestehende Tests in `tests/NeuLadenButtonTest.php` zu AK1/AK2/AK3/AK7 widersprechen der neuen Spec → test-writer passt sie an.
- Agent-Hooks greifen in diesem Repo nicht → Moderator prüft „Tests unverändert“ per `git diff`.

## Aufgaben
- [ ] T0 TESTS: `test-writer` — AK1, AK2, AK3, AK7, AK8, AK9; AK4–AK6 weiter grün; Rot-Nachweis.
- [ ] T1 (AK1–AK3, AK7–AK9) [pro] Config, Freigabe ohne Production-Sperre, Partial nach oben + Styles, README (Schalter, Config-Cache, CSRF-Hinweis, Changelog).
- [ ] NACHWEIS · REVIEW · DOKU
- [ ] Danach it-hilbert.com (eigener Branch): `.env.example` ohne `CONSENTLAW_REFRESH_BUTTON`, `env('APP_TEST')` in Layouts → `config(...)`.

## Stand
Spec und Plan zur Freigabe.
