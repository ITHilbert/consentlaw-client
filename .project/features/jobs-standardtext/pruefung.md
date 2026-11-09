# Prüfung: jobs-standardtext — 2026-11-09

Geprüft: Paket-Repo consentlaw-client `404bd22..2b1f39a` (Host-Stapel `stapel/2026-11-09-3`, Basis `184ce18`). Keine `abdeckung.md` vorhanden; Gliederung nach Spec.
Paket-Suite im Review-Platz: `php vendor/bin/phpunit -c phpunit.xml --bootstrap packages/consentlaw-client/tests/bootstrap.php packages/consentlaw-client/tests` → OK (872 Tests, 3936 Assertions).
Zusätzlich Sonde (Scratchpad, `SondeJobsTest` gegen `AppTestCase`, Projektkonfiguration) für Übersetzung des Initiativ-Satzes, XSS-Varianten, ungültiges Attribut, Kommentar-Slot.
Echtlauf im Host nicht möglich: der Host lädt `vendor/ithilbert/consentlaw-client` (Composer, alter Stand), nicht das Submodul, und hat keine Paket-Routen aktiv — Rendering und Seite `/jobs` sind in der Test-App des Pakets nachgewiesen.

| AK / Aussage (Zitat) | Ergebnis | Nachweis |
|---|---|---|
| AK1 „Gegeben keine Stelle und kein Slot, wenn `<x-consentlaw::jobs />` gerendert wird, dann steht im Ergebnis `Zurzeit sind keine offenen Stellen ausgeschrieben.`“ | bestanden | `test_ohne_stelle_und_slot_steht_der_standardtext_im_leer_absatz` grün (Textvergleich exakt, Gegenprobe API-Aufruf) |
| AK1 „(über `__()`, übersetzbar)“ | bestanden | `test_standardtext_ist_uebersetzbar` (JSON-Übersetzung greift); Blade: `{{ __('Zurzeit sind keine offenen Stellen ausgeschrieben.') }}` |
| AK1 „in einem `<p class="consentlaw-jobs__leer">`“ | bestanden | XPath genau ein `p.consentlaw-jobs__leer` in allen AK1-Tests |
| AK1 „(Attribute der Komponente, z. B. `class`, werden dort angewendet)“ | bestanden | `test_attribute_der_komponente_landen_am_leer_absatz` (class + data-test am Absatz); Sonde: `data-x="<b>" id="y"` landen am `<p>`, Wert escaped |
| AK2 „Gegeben AK1 und eine gültige E-Mail-Adresse (Konfiguration `consentlaw.jobs.initiativ_email` …“ | bestanden | `test_initiativ_satz_aus_der_konfiguration_im_selben_absatz_mit_mailto_link` |
| AK2 „… Umgebungsvariable `CONSENTLAW_JOBS_INITIATIV_EMAIL` …“ | bestanden | `test_initiativ_satz_aus_der_umgebungsvariable` (App neu gebaut, Config liest env, Satz erscheint) |
| AK2 „… oder Attribut `initiativ-email`“ | bestanden | `test_initiativ_satz_aus_dem_attribut_bei_leerer_konfiguration` |
| AK2 „das Attribut geht vor“ | bestanden | `test_attribut_geht_vor_konfiguration` (mit Gegenprobe Config allein) |
| AK2 „dann folgt im selben Absatz der Satz `Initiativbewerbungen gern an <Adresse>.`“ | bestanden | Textvergleich `STANDARDTEXT + ' ' + Satz` im selben `<p>` |
| AK2 „(`__()`)“ | bestanden | Sonde: JSON-Übersetzung für `Initiativbewerbungen gern an :adresse.` → `Speculative applications to <a href="mailto:jobs@example.de">jobs@example.de</a> please.`; kein Test im Feature dafür (Fund sollte) |
| AK2 „die Adresse als `mailto:`-Link“ | bestanden | `assertMailtoLink` (href `mailto:` + Adresse, Linktext = Adresse) |
| AK2 „HTML-escaped“ | bestanden | `test_adresse_mit_html_zeichen_wird_escaped`, `test_kaufmanns_und_in_der_adresse_wird_escaped`; Sonde: `"><script>alert(1)</script>"@example.de` (gültig laut FILTER_VALIDATE_EMAIL) → `&quot;&gt;&lt;script&gt;…` in href und Text, `o'neil@…` → `&#039;` |
| AK2 „Ohne Adresse … entfällt der Satz“ | bestanden | Datenreihe `nichts gesetzt` |
| AK2 „… oder bei ungültiger Adresse (nicht `FILTER_VALIDATE_EMAIL`, auch leer/nur Leerzeichen) entfällt der Satz“ | bestanden | Datenreihen Config/Attribut leer, nur Leerzeichen, ohne @, mit Leerzeichen; Sonde `x@example.de" onmouseover="alert(1)` → kein Satz |
| AK2 „nichts sonst ändert sich“ | bestanden | gleicher Test: `assertSame(trim($basis), trim($html))` gegen Ausgabe ohne Adresse |
| AK3 „Gegeben ein nicht leerer Slot (nach Trim), dann erscheint nur der Slot“ | bestanden | `test_nicht_leerer_slot_ersetzt_standardtext_und_initiativ_satz` |
| AK3 „kein Standardtext, kein Initiativ-Satz (bisheriges Verhalten)“ | bestanden | gleicher Test, Adresse konfiguriert, Gegenprobe ohne Slot |
| AK3 „Ein leerer oder nur aus Leerraum bestehender Slot zählt als ‚kein Slot‘“ | bestanden | `test_leerer_slot_zaehlt_als_kein_slot` (leer, Leerzeichen, Umbrüche/Tabs); Sonde: Slot nur mit Blade-Kommentar → Standardtext |
| AK4 „Gegeben mindestens eine Stelle, dann erscheinen weder Standardtext noch Initiativ-Satz“ | bestanden | `test_mit_stelle_weder_standardtext_noch_initiativ_satz_liste_und_json_ld_bleiben` (Adresse konfiguriert) |
| AK4 „Liste und JSON-LD bleiben wie bisher“ | bestanden | gleicher Test (1× `consentlaw-job`, 1× ld+json); Diff ändert den `isNotEmpty`-Zweig nicht; `JobGoogleFuerJobsSchemaTest`, `KernablaufTest` in der Suite grün |
| AK5 „Gegeben AK1 (mit oder ohne Adresse), dann enthält die Ausgabe kein `application/ld+json`“ | bestanden | `test_kein_json_ld_im_leeren_fall` beide Datenreihen, Gegenprobe mit Stelle |
| AK6 „Gegeben kein `CONSENTLAW_TOKEN`, dann erscheint der Standardtext“ | bestanden | `test_ohne_token_erscheint_der_standardtext` (0 API-Aufrufe) |
| AK6 „(Seite antwortet weiter ohne Fehler)“ | bestanden | `test_seite_antwortet_ohne_token_ohne_fehler_mit_standardtext` → 200 |
| AK7 „Die Seite `/jobs` des Pakets (`pages/jobs.blade.php`) übergibt keinen eigenen Slot mehr“ | bestanden | Diff: `<x-consentlaw::jobs />`; `test_paket_seite_uebergibt_keinen_eigenen_slot` |
| AK7 „und zeigt damit den Standardtext“ | bestanden | `test_paket_seite_zeigt_ohne_stelle_den_standardtext` (Router-Dispatch, 200, h1 als Gegenprobe) |
| AK7 „(samt Initiativ-Satz, falls konfiguriert)“ | bestanden | `test_paket_seite_zeigt_den_initiativ_satz_falls_konfiguriert` |
| AK8 „`consentlaw.jobs.initiativ_email` steht (Standard `null`) in `src/Config/consentlaw.php`“ | bestanden | Diff `'initiativ_email' => env('CONSENTLAW_JOBS_INITIATIV_EMAIL')`; `test_config_enthaelt_initiativ_email_mit_standard_null`, `test_config_liest_die_umgebungsvariable` |
| AK8 „die README (Abschnitt Jobs) beschreibt Standardtext“ | bestanden | README-Diff Abschnitt Jobs nennt Text und `<p class="consentlaw-jobs__leer">` |
| AK8 „… Konfiguration“ | bestanden | nennt `consentlaw.jobs.initiativ_email` und `CONSENTLAW_JOBS_INITIATIV_EMAIL` |
| AK8 „… Attribut“ | bestanden | Beispiel `initiativ-email="jobs@example.de"`, „das Attribut geht vor“ |
| AK8 „… und dass ein Slot ihn ersetzt“ | bestanden | „Ein nicht leerer Slot ersetzt den Standardtext samt Initiativ-Satz“; README-Test grün |
| Randfall 1 „Adresse mit HTML-Zeichen → escaped, nicht als Markup ausgegeben“ | bestanden | Tests s. AK2 escaped, Sonde mit `<script>` |
| Randfall 2 „Adresse nur im Attribut gesetzt, Config leer → Attribut gilt“ | bestanden | `test_initiativ_satz_aus_dem_attribut_bei_leerer_konfiguration` |
| Randfall 2 „Attribut leer-String und Config gesetzt → Config gilt“ | bestanden | `test_leeres_attribut_laesst_die_konfiguration_gelten` |
| Nicht-Ziel „Mehrsprachige Texte über die Deutschen hinaus“ | eingehalten | keine Sprachdateien im Diff |
| Nicht-Ziel „Änderungen an consentLaw oder an den Websites“ | eingehalten | Diff nur im Paket |

## Sicherheit
- Ausgabe-Escaping: einzige Rohausgabe `{!! __('Initiativbewerbungen gern an :adresse.', ['adresse' => '<a href="mailto:'.e($initiativ).'">'.e($initiativ).'</a>']) !!}` — Adresse in href und Linktext per `e()`; zusätzlich nur `FILTER_VALIDATE_EMAIL`-gültige Werte. Sonde mit gültigen, aber feindlichen Adressen (`"><script>…"@…`, `'`, `"a\"b"@…`) zeigte nur Entities, kein Markup, kein Attributausbruch. `javascript:`-Schema ausgeschlossen (fester Präfix `mailto:`). Der Übersetzungstext selbst wird roh ausgegeben — Übersetzungsdateien sind App-eigen, vertrauenswürdig.
- Weitere Attribute über `$attributes->merge()` (Blade escaped Werte; Sonde `data-x="<b>"` → escaped im Quelltext).
- Keine Rechte, keine Mandanten, keine Eingaben von Besuchern; Adresse kommt nur aus Config bzw. Template. Risiko `normal` passt (keine Mails, kein Geld, keine Daten, kein Consent).

## Regressionen
- Paket-Suite (872) grün, einmal gelaufen. `ReviewResteTest::LEERTEXT` wurde auf den neuen Text nachgezogen — gewollte Folge von AK7 (Paket-Seite zeigt jetzt den Standardtext), kein verdeckter Bruch.
- Verhaltensänderung für Websites: Wer bisher `<x-consentlaw::jobs />` ohne Slot nutzt, sieht jetzt den Standardtext statt nichts — genau das Ziel der Spec (#1008).
- Host-Suite nicht gelaufen (laut Auftrag einmal in Schritt 5 des Stapels).

## Funde: 0 muss, 1 sollte, 2 kann
- sollte: Übersetzbarkeit des Initiativ-Satzes (AK2 „(`__()`)“) ist durch keinen Test belegt, nur die des Standardtexts; Verhalten per Sonde korrekt (→ Folge-Task #1028).
- kann: Ungültiges Attribut bei gültiger Config → Config gilt (Sonde `initiativ-email="kaputt"` → Satz mit Config-Adresse). Die Spec regelt nur „leer-String → Config“; die Lesart ist als Annahme in `plan.md` vermerkt und folgerichtig, aber nicht getestet.
- kann: Adresse wird im `href` nur HTML-escaped, nicht URL-kodiert; bei exotischen, gültigen Adressen mit Leerzeichen in Anführungszeichen (`"a b"@…`) entsteht ein `mailto:` mit Leerzeichen — Browser tolerieren das, praktisch irrelevant.

## Neue Tasks: #1028 Jobs-Komponente – Nacharbeit aus Review jobs-standardtext
## Fehlerprotokoll
- {"schwere":"sollte","ak":"AK2","fund":"Übersetzbarkeit des Initiativ-Satzes über __() ist nicht getestet, nur die des Standardtexts","ursache":"Ein AK nennt __() für zwei Sätze, der Test deckt nur den ersten ab","anweisung":"Nennt ein AK eine Eigenschaft (z. B. übersetzbar) für mehrere Texte, je Text einen eigenen Test schreiben"}
## Fazit: freigegeben
