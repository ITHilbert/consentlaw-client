# Plan: jobs-standardtext

Spec: [spec.md](spec.md) · Branch: `feature/jobs-standardtext` (Stapel `stapel/2026-11-09`)

## Aufgaben
- [x] T0 TESTS: `test-writer` — Datei `tests/JobsStandardtextTest.php`
- [x] T1 (AK1–AK8) [pro] Blade `components/jobs.blade.php`, `pages/jobs.blade.php`, Config, README — Tests: tests/JobsStandardtextTest.php

Bestehende Tests: tests/NeuLadenRueckmeldungTest.php tests/ReviewResteTest.php tests/JobGoogleFuerJobsSchemaTest.php tests/FehlerstatusTest.php tests/KernablaufTest.php

Marketing: entfällt (keine neue Seite). Review: am Ende des Stapels (Seite/Ausgabe, Risiko normal).

## Stand
T0 33 Tests rot (grünbar nachgewiesen vom test-writer per Wegwerf-Umsetzung), T1 umgesetzt. NACHWEIS: Paket-Suite 872/872 grün (Moderator selbst), Tests seit Test-Commit unverändert, Pint ok. GATE-Log rot nur wegen Format der PROJEKT-Zeile im Auftrag (kein Codefehler). Echtlauf: entfällt (Paket ohne App; Blade-Rendering in Tests). Annahme: ungültiges Attribut bei gültiger Config → Config gilt. Review: am Ende des Stapels.
