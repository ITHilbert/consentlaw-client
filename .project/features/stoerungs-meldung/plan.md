# Plan: stoerungs-meldung

Spec: [spec.md](spec.md) · Branch: `feature/stoerungs-meldung`

## Hinweise
- Erkennung: `Support/ContentResolver.php` (`fromFallback`: kein Fallback → AK1, Alter > warnAfter → AK3), `Console/ConsentLawReloadCommand.php` (alle 404 → AK2).
- Events unter `src/Events/`, Listener + Notification/Mailable unter `src/Benachrichtigung/` o. ä.; Sperre über den Cache (`add` mit 24 h).
- Config-Block `alerts` (mail, mailer, mail_listener) in `src/Config/consentlaw.php`; README + Changelog „Unveröffentlicht“.
- Token-Maskierung: vorhandenes `Support/TokenMaskierung.php` nutzen.

## Aufgaben
- [x] T0 TESTS: `test-writer` — AK1–AK9, Rot-Nachweis.
- [ ] T1 (AK1–AK9) [senior] Events, Listener, Mail-Vorlage, Sperre, Config, README.
- [ ] NACHWEIS · REVIEW

## Stand
Rote Tests committet (583 Tests, 48 rot in StoerungsEventsTest/StoerungsMailTest). Als Nächstes T1 [senior].
