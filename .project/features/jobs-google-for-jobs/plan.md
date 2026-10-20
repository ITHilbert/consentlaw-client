# Plan: jobs-google-for-jobs

Spec: [spec.md](spec.md) · Branch: `feature/jobs-google-for-jobs`

## Aufgaben
- [x] T0 TESTS: `test-writer` — 38 neue Tests rot (fehlendes Verhalten), Rest grün. Befund: heutiges JSON-LD kaputt – `@context` im Blade wird als Laravel-Direktive kompiliert (wie it-hilbert `fix-blade-context`) → T2 baut das Schema in PHP.
- [x] T1 (AK1) [pro] `Job` um Arbeitgeber (eigene kleine Datenklasse), `remote`, Länder ergänzen; tolerantes Parsen.
- [x] T2 (AK2–AK6) [pro] Komponente `jobs-schema` (Schema-Aufbau an einer Stelle, z. B. Methode/Klasse statt Array im Blade), `jobs` nutzt sie.
- [x] T3 (AK7) [pro] README: Abschnitt „Eigenes Markup“ + neue Felder.
- [x] NACHWEIS: 717/717 grün; Tests seit 631da94 nur MarkdownAusgabeTest (test-writer, c68b4ec) geändert. Probe in it-hilbert-Kopie: echte API → 6 gültige JSON-LD mit korrektem @context (vorher kaputt); Fixture mit neuem Vertrag → hiringOrganization, TELECOMMUTE, Country DE.
- [x] REVIEW 1: nicht freigegeben – AK7: README-Beispiel zeigt HTML-Beschreibungen nicht (immer MarkdownRenderer); Hinweise H1 Kommentar, H2 with-schema, H3 URL mit Leerzeichen. Optimierungslauf (Skill-Regel README-Beispiele, Template 7bc0ed9).
- [ ] T0b TESTS: `test-writer` — AK7 (README-Codeblock gerendert, Markdown + HTML), AK8, AK9, AK2 (URL streng).
- [ ] T4 (AK2, AK7–AK9) [pro] `Job::descriptionHtml()`, Nutzung in Komponente/Schema/README; URL-Prüfung; Kopfkommentar.
- [ ] NACHWEIS 2, REVIEW 2.

## Stand
Review 1 nicht freigegeben; AK7 geschärft, AK8/AK9 ergänzt (User-Freigabe 2026-10-20). T0b läuft.
