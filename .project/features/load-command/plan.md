# Plan: load-command

Spec: [spec.md](spec.md) · Branch: `feature/load-command`

## Hinweise
- Klasse `src/Console/ConsentLawReloadCommand.php` → `ConsentLawLoadCommand.php` (Registrierung im Provider mitziehen).
- Tests, die den Command über seinen Namen oder seine Klasse aufrufen (ReloadCommandRegistrierungTest, StoerungsAlertsTest, TokenSchutzTest u. a.) → test-writer stellt auf `consentlaw:load` um.
- Changelog „Unveröffentlicht“: Eintrag „`consentlaw:warm` heißt jetzt `consentlaw:reload`“ ersatzlos streichen (Package ohne Tag, nichts live).

## Aufgaben
- [x] T0 TESTS: `test-writer` — AK1, AK2; rot: 56 Errors (Klasse fehlt), 4 Failures (alte Namen).
- [ ] T1 (AK1, AK2) [pro] Umbenennen, Meldungstexte, README, Changelog.
- [ ] NACHWEIS · REVIEW

## Stand
T0 fertig, T1 läuft.
