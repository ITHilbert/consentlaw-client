## In Arbeit
- [load-command](load-command/plan.md) — `consentlaw:reload` → `consentlaw:load`

## Geplant
- `Document::contentHtml()` – Markdown/HTML-Unterscheidung in `components/document` und `pages/document` an eine Stelle ziehen (wie AK8 jobs-google-for-jobs)
- README: `:with-schema="false"` dokumentieren (statisch `with-schema="false"` ist truthy)
- Package-Jobs-Seite: Controller übergibt `jobs`, View rendert `<x-consentlaw::jobs>` erneut → doppelter Abruf/doppelte Log-Warnung (Review 2 neu-laden-rueckmeldung)

## Fertig
- [jobs-google-for-jobs](jobs-google-for-jobs/plan.md) — JobPosting mit Arbeitgeber und Homeoffice, Komponente jobs-schema, `@context` repariert (2026-10-20)
- [neu-laden-rueckmeldung](neu-laden-rueckmeldung/plan.md) — „Neu laden“ meldet Erfolg oder Fehler, Seiten ohne Token ohne 500 (2026-10-20)
- [alerts-anbindung](alerts-anbindung/plan.md) — Störungsmeldung über das Basis-Package alerts statt eigener Mail (2026-10-20)
- [stoerungs-meldung](stoerungs-meldung/plan.md) — Meldung per Mail bei Störungen (2026-10-20)
- [reload-command](reload-command/plan.md) — `consentlaw:warm` → `consentlaw:reload` (2026-10-20)
- [neu-laden-app-test](neu-laden-app-test/plan.md) — „Neu laden“ über APP_TEST, auch auf Production, Button oben (2026-10-20)
- [neu-laden-button](neu-laden-button/plan.md) — Button „Neu laden“ per .env (2026-10-16)
- [zeilenumbruch-token](zeilenumbruch-token/plan.md) — Zeilenumbrüche & Token-Schutz (2026-10-16)
- [robustheit](robustheit/plan.md) — Fallback, sichere Markdown-Ausgabe, Format-Allowlist (2026-10-16)
