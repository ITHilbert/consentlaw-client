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
- [ ] NACHWEIS · REVIEW

## Stand
T1 umgesetzt, 583/583 grün (auch mit APP_TEST), Pint grün. Echter Lauf (Kopie it-hilbert.com, Mail ins Log): drei Fälle je eine Mail an zwei Empfänger, Sperre greift, Token nie im Log. Als Nächstes REVIEW.

## Entscheidungen (Moderator)
- HANDOFF T1: `test_standardwerte_der_alerts_config` war unerfüllbar (`?? 'fehlt'` mit `assertNull`) → Korrektur durch `test-writer`.
- 24-h-Sperre bleibt auch nach gescheitertem Versand (sonst wartet bei kaputtem SMTP jeder Seitenaufruf auf den Timeout).
- Fremde Listener auf die Events werden nicht abgesichert (Laravel-Konvention); AK9 gilt für den eigenen Mailversand.
- Echter Lauf zeigte: ohne `MAIL_FROM_ADDRESS` scheitert der Versand (AK9 greift) → README-Hinweis.
