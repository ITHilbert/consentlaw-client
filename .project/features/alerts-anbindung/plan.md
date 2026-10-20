# Plan: alerts-anbindung

Spec: [spec.md](spec.md) · Branch: `feature/alerts-anbindung`

## Hinweise
- Neuer Listener `src/Benachrichtigung/MeldeStoerungAnAlerts.php` (final readonly, Port `AlertReporter` per DI), nutzt das bestehende Enum `Stoerungsfall` für Titel, Beschreibung, Maßnahmen und Schlüssel.
- Token-Maskierung über `Support/TokenMaskierung.php`.
- Entfernen:
  - `MeldeStoerungPerMail`, `StoerungsMail`, `Resources/views/mail/stoerung.blade.php`,
  - Config-Block `alerts` in `src/Config/consentlaw.php`,
  - `registerStoerungsMail()` im Provider (ersetzt durch unbedingte Registrierung).
- Tests: `tests/StoerungsAlertsTest.php` ersetzt `StoerungsMailTest.php`. Der Port ist in `AppTestCase` durch `tests/Fakes/FakeAlertReporter.php` ersetzt, die Mail-Infrastruktur der Testbasis ist entfernt.
- Testlauf: `cd packages/consentlaw-client && php ../../vendor/bin/phpunit -c phpunit.xml` (Autoload über Workspace-vendor, dort ist `ithilbert/alerts` installiert).

## Aufgaben
- [x] T0 TESTS (Moderator): AK1–AK6, Rot-Nachweis. 17 neue Tests rot; 145 bestehende Tests rot, weil der alte Mail-Listener ohne die entfernte Mail-Infrastruktur nicht mehr läuft.
- [x] T1 [senior] Listener, Provider, Rückbau, composer.json (AK1–AK7 Code). 631/631 grün (646 − 32 alte Mail-Tests + 17 neue).
- [x] T2 Doku-Writer: README inkl. Changelog-Abschnitt (AK7).
- [x] NACHWEIS · REVIEW (631/631 grün, Pint sauber)

## Stand
Fertig, in main gemergt und gepusht, kein Release.

## Entscheidungen (Moderator)
- Keine eigene Drosselung mehr (AK4): Doppelte Sperre in zwei Packages wäre redundant und würde `alerts:reset` aushebeln.
- `Stoerungsfall::betrifftEinzelnenTyp()` entfernt (nur von der Mail genutzt); Typ steht in den Details.
- Folgepunkt in alerts: Mail-Vorlage zeigt mehrzeilige Details auf einer Zeile → `white-space: pre-line`.
- Alter unter einem Tag in Stunden (Hinweis Doku-Writer) → Spec AK1 angepasst.
- Changelog: alter „Neu“-Eintrag (Mail) bleibt neben dem Breaking-Eintrag stehen. consentlaw-client wird als `dev-main` genutzt, die Mail-Variante kann also schon im Einsatz sein.
- Fehlt die Port-Bindung, wirft der Listener schon bei der Auflösung (außerhalb des catch). Akzeptiert: alerts ist per composer `require` erzwungen.
- Defensiver catch im Listener trotz Port-Vertrag „wirft nie“: Rechtstexte gehen vor (Abmahnrisiko).
