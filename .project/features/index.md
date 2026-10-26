## In Arbeit

## Geplant
- Nach consentLaw-Task #99: `cookie-einstellungen` auf `window.ConsentLaw.open()` umstellen (alter Name `window.CookieScript` nur in der Übergangszeit)
- `CONSENTLAW_BANNER_ENABLED=off`/`no` lassen den Banner an (`env()` wandelt nur `false`/`(false)`); ggf. per `filter_var(…, FILTER_VALIDATE_BOOL)` (Review banner-komponente)
- Token nur aus Leerzeichen gilt als gesetzt → `banner.js?token=%20…`; betrifft auch die Rechtstext-Abrufe – Token zentral trimmen (Review banner-komponente)
- README: `href`/`onclick` an `<x-consentlaw::cookie-einstellungen>` werden doppelt ausgegeben, das der Komponente gewinnt – dokumentieren oder herausfiltern (Review banner-komponente)
- Pint `--test` meldet `tests/NeuLadenRueckmeldungTest.php` (seit ca0466d) → test-writer bereinigt
- `cookie-einstellungen`: Klick-Handler steht inline im `onclick` → bei strenger CSP ohne `unsafe-inline` wirkungslos. Ausweg: Datenattribut + Listener in `banner.js` (consentLaw). it-hilbert.com hat keine CSP (2026-10-26).
- `Document::contentHtml()` – Markdown/HTML-Unterscheidung in `components/document` und `pages/document` an eine Stelle ziehen (wie AK8 jobs-google-for-jobs)
- README: `:with-schema="false"` dokumentieren (statisch `with-schema="false"` ist truthy)
- Package-Jobs-Seite: Controller übergibt `jobs`, View rendert `<x-consentlaw::jobs>` erneut → doppelter Abruf/doppelte Log-Warnung (Review 2 neu-laden-rueckmeldung)

## Fertig
- [banner-komponente](banner-komponente/plan.md) — `<x-consentlaw::banner />`, Link `<x-consentlaw::cookie-einstellungen />`, Schalter `CONSENTLAW_BANNER_ENABLED`; `banner.js` bleibt bei consentLaw (2026-10-26)
- [load-command](load-command/plan.md) — Command `consentlaw:load` (2026-10-21)
- [jobs-google-for-jobs](jobs-google-for-jobs/plan.md) — JobPosting mit Arbeitgeber und Homeoffice, Komponente jobs-schema, `@context` repariert (2026-10-20)
- [neu-laden-rueckmeldung](neu-laden-rueckmeldung/plan.md) — „Neu laden“ meldet Erfolg oder Fehler, Seiten ohne Token ohne 500 (2026-10-20)
- [alerts-anbindung](alerts-anbindung/plan.md) — Störungsmeldung über das Basis-Package alerts statt eigener Mail (2026-10-20)
- [stoerungs-meldung](stoerungs-meldung/plan.md) — Meldung per Mail bei Störungen (2026-10-20)
- [reload-command](reload-command/plan.md) — `consentlaw:warm` → `consentlaw:reload` (2026-10-20)
- [neu-laden-app-test](neu-laden-app-test/plan.md) — „Neu laden“ über APP_TEST, auch auf Production, Button oben (2026-10-20)
- [neu-laden-button](neu-laden-button/plan.md) — Button „Neu laden“ per .env (2026-10-16)
- [zeilenumbruch-token](zeilenumbruch-token/plan.md) — Zeilenumbrüche & Token-Schutz (2026-10-16)
