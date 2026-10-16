# Spec: Zeilenumbrüche & Token-Schutz (Release 1.1.0)

## Ziel
Rechtstexte werden so dargestellt, wie sie in consentLaw zeilenweise gepflegt sind, und der Domain-Token erscheint nie in Logs oder Fehlermeldungen.

## Ausgangslage (Befunde aus dem Review in it-hilbert.com, 2026-10-16)
- consentLaw liefert Adressblöcke als Markdown mit einfachen Zeilenumbrüchen (`\r\n`). Nach CommonMark-Standard sind das weiche Umbrüche → im HTML ein Leerzeichen → Anschrift, Telefon, E-Mail stehen in einer Zeile.
- Bei Störungen enthält die Fehlermeldung die vollständige API-URL samt Token (`…/api/impressum/<token>`); sie landet in `laravel.log` (Verstoß gegen die Sicherheitsregel „keine Geheimnisse in Logs“).

## Akzeptanzkriterien
- **AK1 — Einfacher Zeilenumbruch = Zeilenumbruch:** Gegeben Markdown mit einfachen Zeilenumbrüchen (`\n` oder `\r\n`) innerhalb eines Absatzes, wenn es gerendert wird (Dokumente und Job-Beschreibungen, alle Ausgabestellen inkl. JSON-LD), dann steht an jeder dieser Stellen ein `<br />`. Leerzeilen trennen weiterhin Absätze (`<p>`), Listen/Überschriften/Tabellen/Code-Blöcke bleiben unverändert, die Filterung nach AK4/AK10 (Feature robustheit) gilt unverändert.
- **AK2 — Kein Token in Meldungen:** Gegeben eine Störung, ein 404 oder ein sonstiger Fehler beim Abruf, wenn das Package eine Exception-Meldung erzeugt oder ins Log schreibt, dann enthält keine Meldung und kein Log-Kontext den Token im Klartext. URLs erscheinen mit maskiertem Token (z. B. `…/api/impressum/abcd…`, höchstens die ersten 4 Zeichen).

## Randfälle
- Zeilenende mit zwei Leerzeichen (klassischer harter Umbruch) → genau ein `<br />`, kein doppelter.
- `<br>` im Text (bestehendes Verhalten) → weiterhin genau ein `<br />`.
- Token kürzer als 5 Zeichen → vollständig maskiert.

## Nicht-Ziele
- Korrektur der Inhalte in consentLaw (zusammengeklebte Absätze sind ein consentLaw-Fehler, eigener Bugfix dort).
- Rotation des Tokens (Aufgabe des Betreibers).
