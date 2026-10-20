# Plan: stoerungs-meldung

Spec: [spec.md](spec.md) · Branch: `feature/stoerungs-meldung`

## Hinweise
- Erkennung: `Support/ContentResolver.php` (`fromFallback`: kein Fallback → AK1, Alter > warnAfter → AK3), `Console/ConsentLawReloadCommand.php` (alle 404 → AK2).
- Events unter `src/Events/`, Listener + Notification/Mailable unter `src/Benachrichtigung/` o. ä.; Sperre über den Cache (`add` mit 24 h).
- Config-Block `alerts` (mail, mailer, mail_listener) in `src/Config/consentlaw.php`; README + Changelog „Unveröffentlicht“.
- Token-Maskierung: vorhandenes `Support/TokenMaskierung.php` nutzen.

## Aufgaben
- [x] T0 TESTS: `test-writer` — AK1–AK9, Rot-Nachweis.
- [x] T1 (AK1–AK9) [senior] Events, Listener, Mail-Vorlage, Sperre, Config, README.
- [x] NACHWEIS · REVIEW freigegeben (Pint in Tests nachgezogen)

## Stand
Fertig (Review freigegeben), in main gemergt und gepusht, kein Release.

## Entscheidungen (Moderator)
- HANDOFF T1: `test_standardwerte_der_alerts_config` war unerfüllbar (`?? 'fehlt'` mit `assertNull`) → Korrektur durch `test-writer`.
- 24-h-Sperre bleibt auch nach gescheitertem Versand (sonst wartet bei kaputtem SMTP jeder Seitenaufruf auf den Timeout).
- Fremde Listener auf die Events werden nicht abgesichert (Laravel-Konvention); AK9 gilt für den eigenen Mailversand.
- Echter Lauf zeigte: ohne `MAIL_FROM_ADDRESS` scheitert der Versand (AK9 greift) → README-Hinweis.
