# Plan: reload-command

Spec: [spec.md](spec.md) · Branch: `feature/reload-command`

## Hinweise
- Klasse `src/Console/ConsentLawWarmCommand.php` → `ConsentLawReloadCommand.php` (Registrierung im Provider mitziehen).
- Tests mit `warm` im Namen/Aufruf: WarmCommandTest, WarmTokenHinweisTest, TokenSchutzTest, NichtVeroeffentlichtCacheTest → test-writer.

## Aufgaben
- [x] T0 TESTS: `test-writer` — AK1, AK2; Rot-Nachweis.
- [ ] T1 (AK1–AK3) [pro] Umbenennen, README, Changelog.
- [ ] NACHWEIS · REVIEW

## Stand
Spec aus User-Vorgabe.
