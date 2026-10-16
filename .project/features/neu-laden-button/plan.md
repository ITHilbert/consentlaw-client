# Plan: neu-laden-button

Spec: [spec.md](spec.md) · Branch: `feature/neu-laden-button` · Ziel-Release: `1.2.0`

## Hinweise
- Config `consentlaw.refresh_button` (env `CONSENTLAW_REFRESH_BUTTON`, Standard false); Freigabe-Logik (Schalter UND nicht production) an EINER Stelle, genutzt von Route-Registrierung und Komponenten.
- Route POST (Middleware `web` + eigener Limiter 6/min), ruft den vorhandenen `refresh()` der Endpoints; Rückleitung `back()`.
- Button als eigene Blade-Teilansicht, eingebunden in document- und jobs-Komponente (eine Stelle).
- Agent-Hooks greifen in diesem Repo nicht → Moderator prüft „Tests unverändert“ per `git diff`.

## Aufgaben
- [x] T0 TESTS: `test-writer` — AK1–AK6, Rot-Nachweis.
- [x] T1 (AK1–AK6) [pro] Config, Freigabe-Logik, Route + Controller-Aktion, Button-Partial, README.
- [x] REVIEW Runde 1: nicht freigegeben (AK4 mit locale; Freigabe nicht in der Aktion geprüft).
- [x] T2 TESTS: `test-writer` — AK4 (locale), AK7.
- [x] T3 (AK4, AK7) [pro] Locale-Feld im Button + Prüfung im Controller, Freigabe-Prüfung im Controller.
- [x] NACHWEIS · REVIEW Runde 2 freigegeben (README-Hinweise eingearbeitet) · Release `1.2.0` · Release `1.2.0` · it-hilbert.com `composer update` + `.env` lokal einschalten

## Stand
Fertig, Release 1.2.0. Offen: it-hilbert.com `composer update` + Schalter lokal.
