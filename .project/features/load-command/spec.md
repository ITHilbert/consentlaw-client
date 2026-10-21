# Spec: Command `consentlaw:load`

## Ziel
Der Command, der Cache und Sperrvermerk verwirft und alle Inhalte frisch lädt, heißt `consentlaw:load` (User-Vorgabe 2026-10-21).

## Akzeptanzkriterien
- **AK1 — Laden:** Gegeben das Package ist installiert, wenn `php artisan consentlaw:load` (optional `--locale=…`) aufgerufen wird, dann lädt er alle Inhalte frisch – Ausgaben, Exit-Codes, Token-Hinweis, Fallback-Stand und Störungsmeldungen wie bisher.
- **AK2 — Ein Name:** Code, Meldungstexte (`Stoerungsfall`), Code-Kommentare und README nennen den Command nur als `consentlaw:load`. Im Changelog „Unveröffentlicht“ steht kein Eintrag zur Umbenennung.

## Nicht-Ziele
- Verhaltensänderung des Commands.
- Button „Neu laden“ und Methode `refresh()`.
- Abgeschlossene Feature-Dokumente unter `.project/features/`.
