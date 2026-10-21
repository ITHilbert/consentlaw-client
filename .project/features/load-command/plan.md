# Plan: load-command

Spec: [spec.md](spec.md) · Branch: `feature/load-command`

## Hinweise
- Klasse `src/Console/ConsentLawReloadCommand.php` → `ConsentLawLoadCommand.php` (Registrierung im Provider mitziehen).
- Tests, die den Command über seinen Namen oder seine Klasse aufrufen (ReloadCommandRegistrierungTest, StoerungsAlertsTest, TokenSchutzTest u. a.) → test-writer stellt auf `consentlaw:load` um.
- Changelog „Unveröffentlicht“: Eintrag „`consentlaw:warm` heißt jetzt `consentlaw:reload`“ ersatzlos streichen (Package ohne Tag, nichts live).

## Aufgaben
- [ ] T0 TESTS: `test-writer` — AK1, AK2; Rot-Nachweis.
- [ ] T1 (AK1, AK2) [pro] Umbenennen, Meldungstexte, README, Changelog.
- [ ] NACHWEIS · REVIEW

## Stand
Spec freigegeben (2026-10-21), T0 startet.
