# Spec: jobs-google-for-jobs

Das JSON-LD der Jobs (Schema.org `JobPosting`) ist für Google for Jobs unvollständig: Es fehlen `hiringOrganization` und bei Homeoffice-Stellen `jobLocationType`/`applicantLocationRequirements` (Pflichtfelder → Fehler in der Search Console). consentLaw liefert diese Angaben künftig je Stelle; der Client wird gegen den vereinbarten Vertrag gebaut, bevor die API ihn ausliefert (User, 2026-10-20).

Außerdem braucht eine Homepage mit eigenem Markup (z. B. Aufklapp-Menü in it-hilbert.com) das JSON-LD einzeln, ohne die Listen-Komponente.

## API-Vertrag (neu je Stelle, additiv)
- `hiring_organization`: `{ "name": string, "url": string|null, "logo": string|null }` oder `null`
- `remote`: `bool`
- `applicant_countries`: `string[]` (ISO-3166-1 alpha-2, z. B. `["DE"]`; `[]` wenn nicht remote)

## Akzeptanzkriterien
- **AK1 — Daten lesen:** Gegeben eine API-Antwort mit den neuen Feldern, dann stellt `Job` sie bereit: Arbeitgeber (Name, URL, Logo), `remote`, Länderliste. Gegeben eine Antwort **ohne** die Felder (heutige API) oder mit falschen Typen, dann gilt: kein Arbeitgeber, `remote = false`, leere Länderliste — ohne Fehler. Ein Arbeitgeber ohne (nicht leeren) Namen gilt als fehlend. Länder-Codes werden nur übernommen, wenn sie zwei Buchstaben sind (Großschreibung normalisiert), Doppelte entfallen.
- **AK2 — Arbeitgeber im Schema:** Gegeben ein Job mit Arbeitgeber, dann enthält sein JSON-LD `hiringOrganization` mit `@type: Organization`, `name`, und – nur wenn gesetzt – `sameAs` (URL) und `logo`. URL und Logo werden nur übernommen, wenn sie gültige absolute `http(s)`-URLs mit Host sind (kein Leerzeichen, keine Steuerzeichen, nicht protokollrelativ). Ohne Arbeitgeber fehlt `hiringOrganization` (nichts wird erfunden).
- **AK3 — Homeoffice:** Gegeben `remote = true` mit Ländern, dann enthält das JSON-LD `jobLocationType: "TELECOMMUTE"` und `applicantLocationRequirements` als Liste `{ "@type": "Country", "name": "<Code>" }` je Land. Gegeben `remote = false`, dann fehlen beide Felder. `remote = true` ohne Länder → `jobLocationType` wird ausgegeben, `applicantLocationRequirements` fehlt.
- **AK4 — Ort bleibt:** Gegeben ein Job mit `location`, dann bleibt `jobLocation` wie bisher – auch zusammen mit `remote = true` (Hybrid).
- **AK5 — Schema einzeln nutzbar:** Es gibt eine Komponente `<x-consentlaw::jobs-schema :jobs="$jobs" />`, die je Job ein `<script type="application/ld+json">` ausgibt und sonst nichts. `<x-consentlaw::jobs />` erzeugt sein JSON-LD über genau diese Komponente (eine Stelle für den Schema-Aufbau); `:with-schema="false"` (gebunden) unterdrückt das JSON-LD wie bisher. Die bisherigen Felder des JSON-LD (`title`, `description`, `employmentType`, `datePosted`, `validThrough`, `jobLocation`) bleiben unverändert.
- **AK6 — Sicher ausgegeben:** Werte aus der API können das Script nicht verlassen: ein Arbeitgeber-Name `</script><script>alert(1)</script>` erscheint nur escaped im JSON (wie heute per `JSON_HEX_TAG`).
- **AK7 — Eigenes Markup dokumentiert** *(geschärft nach Review 1)*: Die README zeigt, wie eine Homepage Jobs im eigenen Markup ausgibt: Daten über den Weg, der ohne Token nicht wirft (AK11 aus `neu-laden-rueckmeldung`), Button per Partial, Beschreibung per AK8, JSON-LD per `jobs-schema`. Das Beispiel enthält keine eigene Fallunterscheidung. Gegeben der Codeblock der README wird mit einer Markdown- und einer HTML-Stelle gerendert, dann erscheinen beide Beschreibungen vollständig.
- **AK8 — Beschreibung als HTML an einer Stelle** *(ergänzt nach Review 1)*: `Job` liefert seine Beschreibung fertig als HTML (`descriptionHtml()`): Markdown gerendert über `MarkdownRenderer`, HTML unverändert wie heute in der Listen-Komponente. Listen-Komponente, `JobPostingSchema` und das README-Beispiel nutzen nur diese Methode; die Unterscheidung Markdown/HTML steht nirgends sonst.
- **AK9 — Kommentar der Komponente** *(ergänzt nach Review 1)*: Der Kopfkommentar von `components/jobs.blade.php` verweist für eigenes Markup auf den README-Abschnitt bzw. den Weg aus AK7, nicht auf `ConsentLaw::jobs()` (wirft ohne Token).
## Randfälle
- API liefert `hiring_organization` als String oder Liste → wie fehlend (AK1).
- `applicant_countries` enthält `"de"`, `"DEU"`, `""`, `null` → nur `"DE"` bleibt.
- Leere Job-Liste → `jobs-schema` gibt nichts aus.

## Nicht-Ziele
- Änderungen an consentLaw (eigene Session, Anweisung beim User).
- Weitere JobPosting-Felder (Gehalt, `identifier`, `directApply`).
- Sichtbare Ausgabe von Arbeitgeber/Homeoffice in der Listen-Komponente.
