# Spec: Command `consentlaw:reload` statt `consentlaw:warm`

## Ziel
Der Command heißt, was er tut: Er verwirft Cache und Sperrvermerk und lädt alle Inhalte frisch. Reine Umbenennung, kein Alias (User-Vorgabe 2026-10-20; auf den Servern gibt es noch keine Cron-Jobs).

## Akzeptanzkriterien
- **AK1 — Neuer Name:** Gegeben das Package ist installiert, wenn `php artisan consentlaw:reload` (optional `--locale=…`) aufgerufen wird, dann verhält er sich exakt wie bisher `consentlaw:warm` (Ausgaben, Exit-Codes, Token-Hinweis, Fallback-Stand).
- **AK2 — Alter Name weg:** Gegeben das Package ist installiert, wenn `consentlaw:warm` aufgerufen wird, dann ist der Command unbekannt (kein Alias).
- **AK3 — Doku:** README und Code-Kommentare nennen nur noch `consentlaw:reload`; Changelog unter „Unveröffentlicht“ als Breaking Change. Abschnitte zu bereits veröffentlichten Versionen („Upgrade auf …“) bleiben unverändert.

## Nicht-Ziele
- Verhaltensänderung des Commands.
- Anpassung der Homepages (`routes/console.php` in it-hilbert.com) — eigener Schritt beim Update.
