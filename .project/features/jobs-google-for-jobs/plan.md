# Plan: jobs-google-for-jobs

Spec: [spec.md](spec.md) · Branch: `feature/jobs-google-for-jobs`

## Aufgaben
- [x] T0 TESTS: `test-writer` — 38 neue Tests rot (fehlendes Verhalten), Rest grün. Befund: heutiges JSON-LD kaputt – `@context` im Blade wird als Laravel-Direktive kompiliert (wie it-hilbert `fix-blade-context`) → T2 baut das Schema in PHP.
- [ ] T1 (AK1) [pro] `Job` um Arbeitgeber (eigene kleine Datenklasse), `remote`, Länder ergänzen; tolerantes Parsen.
- [ ] T2 (AK2–AK6) [pro] Komponente `jobs-schema` (Schema-Aufbau an einer Stelle, z. B. Methode/Klasse statt Array im Blade), `jobs` nutzt sie.
- [ ] T3 (AK7) [pro] README: Abschnitt „Eigenes Markup“ + neue Felder.
- [ ] NACHWEIS: Suite; JSON-LD einer gerenderten Seite mit Fixture der neuen Felder prüfen (gültiges JSON, Felder vorhanden).

## Stand
Rote Tests committet (631da94). T1–T3 bei coder-pro.
