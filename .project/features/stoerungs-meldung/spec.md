# Spec: Meldung per Mail bei Störungen

## Ziel
Fehlende oder veraltete Rechtstexte sind abmahnfähig – ein Log liest niemand. Das Package meldet drei Störungsfälle als **Event**; ein mitgelieferter Listener schickt dazu eine Mail aus einer Vorlage. Empfänger aus der `.env`, Versand über den Laravel-Mailer der App (`MAIL_*`). Der Listener ist eine Übergangslösung: Ein späteres Alert-Package übernimmt die Events, der Listener wird dann per Config abgeschaltet.

## Akzeptanzkriterien
- **AK1 — Kein Inhalt lieferbar:** Gegeben die API ist gestört (kein 404) und es gibt keine Fallback-Datei (oder der Fallback ist abgeschaltet), wenn ein Typ abgerufen wird (Seitenaufruf, Button oder `consentlaw:reload`), dann wird das Event `ITHilbert\ConsentLaw\Events\RechtstextNichtLieferbar` (Typ, Locale, Fehlertext) ausgelöst.
- **AK2 — Token vermutlich ungültig:** Gegeben `consentlaw:reload` läuft und **alle** Typen antworten „nicht veröffentlicht“ (404), dann wird das Event `TokenVermutlichUngueltig` ausgelöst (zusätzlich zur bisherigen Ausgabe und Exit-Code).
- **AK3 — Notstand veraltet:** Gegeben die API ist gestört und der ausgelieferte Fallback ist älter als `fallback.warn_after` (Standard 7 Tage, `CONSENTLAW_FALLBACK_WARN_AFTER`), dann wird das Event `NotstandVeraltet` (Typ, Locale, Alter, Fehlertext) ausgelöst. Der Inhalt wird weiter ausgeliefert.
- **AK4 — Mail:** Gegeben `CONSENTLAW_ALERT_MAIL` ist gesetzt (eine oder mehrere Adressen, kommagetrennt), wenn eines der Events ausgelöst wird, dann geht eine Mail an diese Adressen. Sie nennt: Domain/App-URL, Fall, Typ, Zeitpunkt, Fehlertext (Token maskiert, nie im Klartext), bei AK3 das Alter, und was zu tun ist. Versand über den Standard-Mailer der App; optional `CONSENTLAW_ALERT_MAILER` für einen anderen konfigurierten Mailer.
- **AK5 — Ohne Empfänger keine Mail:** Gegeben `CONSENTLAW_ALERT_MAIL` ist leer, dann werden die Events trotzdem ausgelöst, aber keine Mail versendet.
- **AK6 — Keine Mailflut:** Für jeden Fall (AK1, AK2, AK3) geht höchstens **eine Mail pro 24 Stunden** raus – unabhängig von Typ und Anzahl der Seitenaufrufe (Sperre über den Cache).
- **AK7 — Vorlage:** Die Mail kommt aus einer Blade-Vorlage des Packages, die per `vendor:publish --tag=consentlaw-views` überschreibbar ist.
- **AK8 — Abschaltbar:** Gegeben `consentlaw.alerts.mail_listener` ist `false` (`CONSENTLAW_ALERT_LISTENER=false`), dann registriert das Package seinen Mail-Listener nicht; die Events werden weiter ausgelöst.
- **AK9 — Seite bleibt heil:** Schlägt der Mailversand fehl (z. B. SMTP nicht erreichbar), dann wird das geloggt, und der Seitenaufruf bzw. der Command verhält sich wie ohne Mail (kein 500, gleicher Exit-Code).

## Randfälle
- Während der Sperrzeit nach einer Störung (`retry_after`, 5 Min.) wird nicht erneut abgerufen → kein neues Event; ohnehin greift AK6.
- Ohne Cron auf dem Server läuft `consentlaw:reload` nicht stündlich → AK2 fällt nur bei manuellem Aufruf oder nach Einrichten des Crons auf; AK1/AK3 auch beim Seitenaufruf.
- Mehrere Typen gleichzeitig gestört → eine Mail je Fall (AK6); die Mail nennt den auslösenden Typ und weist darauf hin, dass weitere betroffen sein können.

## Nicht-Ziele
- Allgemeines Alerting (500er, andere Packages) – eigenes Package, siehe Diskussion „alerts“.
- Andere Kanäle als Mail (Slack o. ä.).
- Meldung für einzelne „nicht veröffentlicht“-Typen (Kunde ohne AGB ist normal).
