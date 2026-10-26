# Plan: banner-komponente

Spec: [spec.md](spec.md) · Branch: `feature/banner-komponente`

## Hinweise
- Komponenten unter `src/Resources/views/components/` (Namespace `consentlaw`, schon registriert im `ConsentLawServiceProvider`).
- Schalter in `src/Config/consentlaw.php` als neuer Block `banner` mit Kommentar im Stil der übrigen Blöcke.
- Abnehmer danach: it-hilbert.com (eigenes Feature, bindet die Komponente ins Layout ein und ersetzt den toten Usercentrics-Link im Footer).

## Aufgaben
- [ ] T0 TESTS: `test-writer` — AK1–AK7.
- [ ] T1 (AK1, AK2, AK3, AK4) [pro] Komponente `banner` + Schalter `banner.enabled` — Tests: `tests/BannerKomponenteTest.php`
- [ ] T2 (AK5, AK6) [pro] Komponente `cookie-einstellungen` — Tests: `tests/CookieEinstellungenKomponenteTest.php`
- [ ] T3 (AK7) [junior] README, Changelog „Unveröffentlicht“ — Tests: `tests/BannerReadmeTest.php`
- [ ] NACHWEIS
- [ ] REVIEW

## Stand
Spec vom User freigegeben (2026-10-26). Nächster Schritt: T0 TESTS.
