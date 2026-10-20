# Plan: jobs-google-for-jobs

Spec: [spec.md](spec.md) · Branch: `feature/jobs-google-for-jobs`

## Aufgaben
- [x] T0 TESTS: `test-writer` — 38 neue Tests rot (fehlendes Verhalten), Rest grün. Befund: heutiges JSON-LD kaputt – `@context` im Blade wird als Laravel-Direktive kompiliert (wie it-hilbert `fix-blade-context`) → T2 baut das Schema in PHP.
- [x] T1 (AK1) [pro] `Job` um Arbeitgeber (eigene kleine Datenklasse), `remote`, Länder ergänzen; tolerantes Parsen.
- [x] T2 (AK2–AK6) [pro] Komponente `jobs-schema` (Schema-Aufbau an einer Stelle, z. B. Methode/Klasse statt Array im Blade), `jobs` nutzt sie.
- [x] T3 (AK7) [pro] README: Abschnitt „Eigenes Markup“ + neue Felder.
- [x] NACHWEIS: 717/717 grün; Tests seit 631da94 nur MarkdownAusgabeTest (test-writer, c68b4ec) geändert. Probe in it-hilbert-Kopie: echte API → 6 gültige JSON-LD mit korrektem @context (vorher kaputt); Fixture mit neuem Vertrag → hiringOrganization, TELECOMMUTE, Country DE.
- [ ] REVIEW: `reviewer`.

## Stand
NACHWEIS fertig, Review läuft.
