# Plan: banner-komponente

Spec: [spec.md](spec.md) · Branch: `feature/banner-komponente`

## Hinweise
- Komponenten unter `src/Resources/views/components/` (Namespace `consentlaw`, schon registriert im `ConsentLawServiceProvider`).
- Schalter in `src/Config/consentlaw.php` als neuer Block `banner` mit Kommentar im Stil der übrigen Blöcke.
- consentLaw-Task #99 (https://dashboard.lan/agentdashboard/tasks/99): `window.CookieScript` → `window.ConsentLaw`, alter Name bleibt in der Übergangszeit. Nach Erledigung zieht `cookie-einstellungen` auf `window.ConsentLaw.open()` um (eigener Bugfix/Feature).
- `CONSENTLAW_BANNER_ENABLED=` (leer) ist in der Spec offen; über `env()` ergibt das `''` → ausgeschaltet. Bewusst nicht geregelt (Annahme Moderator 2026-10-26).
- Abnehmer danach: it-hilbert.com (eigenes Feature, bindet die Komponente ins Layout ein und ersetzt den toten Usercentrics-Link im Footer).

## Aufgaben
- [x] T0 TESTS: `test-writer` — AK1–AK7; rot: 26 (Banner 12, Link 10, README 4), übrige 790 grün (d5a406c).
- [x] T1 (AK1, AK2, AK3, AK4) [pro] Komponente `banner` + Schalter `banner.enabled` — Tests: `tests/BannerKomponenteTest.php` — 03b1022, GATE grün, NACHWEIS 12/12 grün; „Banner aktiv“ in `Support/BannerFreigabe`
- [x] T2 (AK5, AK6) [pro] Komponente `cookie-einstellungen` — Tests: `tests/CookieEinstellungenKomponenteTest.php` — GATE grün, NACHWEIS 10/10 grün
- [x] T3 (AK7) [junior] README, Changelog „Unveröffentlicht“ — Tests: `tests/BannerReadmeTest.php` — GATE grün, 4/4; Zeile in der Konfig-Tabelle vom Moderator ergänzt
- [x] NACHWEIS: ganze Suite 816/816 grün, Tests seit d5a406c unverändert. Pint `--test` meldet nur `tests/NeuLadenRueckmeldungTest.php` (Altlast, nicht Teil des Features)
- [ ] REVIEW

## Stand
T1–T3 fertig, Suite grün (2026-10-26). Nächster Schritt: REVIEW.
