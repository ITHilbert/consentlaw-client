# Plan: reload-command

Spec: [spec.md](spec.md) · Branch: `feature/reload-command`

## Hinweise
- Klasse `src/Console/ConsentLawWarmCommand.php` → `ConsentLawReloadCommand.php` (Registrierung im Provider mitziehen).
- Tests mit `warm` im Namen/Aufruf: WarmCommandTest, WarmTokenHinweisTest, TokenSchutzTest, NichtVeroeffentlichtCacheTest → test-writer.

## Aufgaben
- [x] T0 TESTS: `test-writer` — AK1, AK2; Rot-Nachweis.
- [x] T1 (AK1–AK3) [pro] Umbenennen, README, Changelog.
- [x] NACHWEIS · REVIEW freigegeben

## Stand
Fertig (Review freigegeben), in main gemergt, nicht gepusht, kein Release. Homepages beim Update auf reload umstellen.
