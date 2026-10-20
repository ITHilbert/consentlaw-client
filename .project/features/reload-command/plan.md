# Plan: reload-command

Spec: [spec.md](spec.md) · Branch: `feature/reload-command`

## Hinweise
- Klasse `src/Console/ConsentLawWarmCommand.php` → `ConsentLawReloadCommand.php` (Registrierung im Provider mitziehen).
- Tests mit `warm` im Namen/Aufruf: WarmCommandTest, WarmTokenHinweisTest, TokenSchutzTest, NichtVeroeffentlichtCacheTest → test-writer.

## Aufgaben
- [x] T0 TESTS: `test-writer` — AK1, AK2; Rot-Nachweis.
- [x] T1 (AK1–AK3) [pro] Umbenennen, README, Changelog.
- [ ] NACHWEIS · REVIEW

## Stand
T1 umgesetzt, 524/524 grün, Pint grün. Echter Lauf (Kopie it-hilbert.com): reload lädt 6 Typen, Exit 0; warm unbekannt. Als Nächstes REVIEW.
