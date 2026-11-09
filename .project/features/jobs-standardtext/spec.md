# Spec: jobs-standardtext

Task #1008 (Dashboard, consentlaw-client). `<x-consentlaw::jobs />` gibt ohne Stelle und ohne Slot nichts aus. Der Text „keine Stellen“ soll aus dem Paket kommen, nicht aus jeder Website einzeln (Joerg, 2026-11-09, Anlass access-programmierer.com).

## Akzeptanzkriterien
- **AK1 — Standardtext:** Gegeben keine Stelle und kein Slot, wenn `<x-consentlaw::jobs />` gerendert wird, dann steht im Ergebnis `Zurzeit sind keine offenen Stellen ausgeschrieben.` (über `__()`, übersetzbar) in einem `<p class="consentlaw-jobs__leer">` (Attribute der Komponente, z. B. `class`, werden dort angewendet).
- **AK2 — Initiativbewerbung:** Gegeben AK1 und eine gültige E-Mail-Adresse (Konfiguration `consentlaw.jobs.initiativ_email`, Umgebungsvariable `CONSENTLAW_JOBS_INITIATIV_EMAIL`, oder Attribut `initiativ-email`; das Attribut geht vor), dann folgt im selben Absatz der Satz `Initiativbewerbungen gern an <Adresse>.` (`__()`), die Adresse als `mailto:`-Link, HTML-escaped. Ohne Adresse oder bei ungültiger Adresse (nicht `FILTER_VALIDATE_EMAIL`, auch leer/nur Leerzeichen) entfällt der Satz, nichts sonst ändert sich.
- **AK3 — Slot gewinnt:** Gegeben ein nicht leerer Slot (nach Trim), dann erscheint nur der Slot — kein Standardtext, kein Initiativ-Satz (bisheriges Verhalten). Ein leerer oder nur aus Leerraum bestehender Slot zählt als „kein Slot“.
- **AK4 — Stellen unverändert:** Gegeben mindestens eine Stelle, dann erscheinen weder Standardtext noch Initiativ-Satz; Liste und JSON-LD bleiben wie bisher.
- **AK5 — Kein JSON-LD im leeren Fall:** Gegeben AK1 (mit oder ohne Adresse), dann enthält die Ausgabe kein `application/ld+json`.
- **AK6 — Ohne Token wie „keine Stelle“:** Gegeben kein `CONSENTLAW_TOKEN`, dann erscheint der Standardtext (Seite antwortet weiter ohne Fehler).
- **AK7 — Paket-Seite:** Die Seite `/jobs` des Pakets (`pages/jobs.blade.php`) übergibt keinen eigenen Slot mehr und zeigt damit den Standardtext (samt Initiativ-Satz, falls konfiguriert).
- **AK8 — Config und README:** `consentlaw.jobs.initiativ_email` steht (Standard `null`) in `src/Config/consentlaw.php`; die README (Abschnitt Jobs) beschreibt Standardtext, Konfiguration, Attribut und dass ein Slot ihn ersetzt.

## Randfälle
- Adresse mit HTML-Zeichen → escaped, nicht als Markup ausgegeben.
- Adresse nur im Attribut gesetzt, Config leer → Attribut gilt; Attribut leer-String und Config gesetzt → Config gilt.

## Nicht-Ziele
- Mehrsprachige Texte über die Deutschen hinaus (der Schlüssel ist der deutsche Satz wie bei den anderen `__()`).
- Änderungen an consentLaw oder an den Websites.

## Entscheidungsspielraum
Markup-Details, Name der Hilfsklasse, falls eine gebraucht wird.

Risiko: normal.
