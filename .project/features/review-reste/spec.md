# Spec: review-reste (offene Punkte aus den Reviews)

## Ausgangslage
Aus den Reviews von jobs-google-for-jobs und neu-laden-rueckmeldung stehen drei Punkte im Index „Geplant“:
- Die Unterscheidung Markdown/HTML für Rechtstexte steht doppelt im Blade (`components/document.blade.php` und `pages/document.blade.php`: `$document->isMarkdown() ? MarkdownRenderer::render(…) : …`). Für Stellen gibt es dafür seit jobs-google-for-jobs AK8 eine Methode `Job::descriptionHtml()`.
- `ConsentLawPageController` holt für die Paket-Jobs-Seite die Stellen (`'jobs' => $this->inhalt->jobs(null, false)`), die View `pages/jobs.blade.php` nutzt `$jobs` aber nicht, sondern rendert `<x-consentlaw::jobs>`, das die Stellen erneut holt → doppelter Abruf und bei Störung doppelte Log-Warnung.
- Die README erwähnt das Attribut `with-schema` der Komponente `<x-consentlaw::jobs>` nicht. Statisch geschrieben (`with-schema="false"`) ist der Wert der String `"false"` und damit wahr – das JSON-LD wird trotzdem ausgegeben.

Anlass: ToDo von it-hilbert.com („Kleinere Punkte aus den Reviews, Package consentlaw-client“), User-Auftrag 2026-10-27 („alles von der ToDo-Liste, nach eigenem Ermessen“).

## Akzeptanzkriterien
- **AK1 — Rechtstext-HTML an einer Stelle:** Gegeben ein `Document` — Dann liefert `Document::contentHtml()` bei Markdown die Ausgabe von `MarkdownRenderer::render($content)` und bei HTML den Inhalt unverändert; `components/document.blade.php` und `pages/document.blade.php` geben den Inhalt nur noch über `contentHtml()` aus (keine eigene Unterscheidung Markdown/HTML im Blade). Die gerenderte Ausgabe beider Views ist für Markdown- und HTML-Dokumente gleich wie vorher.
- **AK2 — Paket-Jobs-Seite holt die Stellen einmal:** Gegeben die Paket-Route für Stellen (`consentlaw.routes.enabled`) — Wenn die Seite aufgerufen wird — Dann werden die Stellen je Aufruf genau einmal abgerufen (ein Aufruf von `KomponentenInhalt::jobs` bzw. eine Anfrage an die Jobs-API bei leerem Cache), und bei einer Störung steht die zugehörige Warnung genau einmal im Log. Die Ausgabe der Seite (Stellen, JSON-LD, Leertext „Zurzeit sind keine Stellen ausgeschrieben.“) bleibt gleich.
- **AK3 — `with-schema` dokumentiert:** Gegeben die README — Dann beschreibt sie das Attribut `with-schema` von `<x-consentlaw::jobs>` mit der gebundenen Form `:with-schema="false"` als Weg, das JSON-LD zu unterdrücken, und weist darauf hin, dass die statische Form `with-schema="false"` das JSON-LD **nicht** unterdrückt.

## Randfälle
1. Leerer Inhalt (`Document::isEmpty()`): `contentHtml()` liefert einen leeren String, die Views verhalten sich wie bisher.
2. Unbekanntes oder fehlendes Format: wie bisher behandelt (`Inhaltsformat::ausApi`), `contentHtml()` folgt `isMarkdown()`.

## Nicht-Ziele
- Statisches `with-schema="false"` technisch in `false` umwandeln (nur dokumentieren).
- Übrige Punkte aus „Geplant“ (Banner-Schalter, Token trimmen, CSP).

## Entscheidungsspielraum
- Ob der Controller die Stellen gar nicht mehr holt oder die View sie an die Komponente durchreicht, entscheidet der Coder – Hauptsache genau ein Abruf (AK2). Keine neuen Pakete.
