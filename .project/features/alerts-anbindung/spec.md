# Spec: Anbindung an das Basis-Package alerts

Vorgänger: [stoerungs-meldung](../stoerungs-meldung/spec.md) (eigene Mail als Übergangslösung).
Grundlage: Diskussion „alerts“ im Package `ithilbert/alerts`, Beschluss 7.

## Ziel
Die drei Störungsfälle werden nicht mehr per eigener Mail gemeldet, sondern über den Port `AlertReporter` des Basis-Packages `ithilbert/alerts`. Kanäle (Mail, Telegram), Drosselung und Freigabe übernimmt alerts. Die eigene Mail-Infrastruktur entfällt. Die Events bleiben unverändert.

## Akzeptanzkriterien
- **AK1 — Meldung:** Jedes der drei Events (`RechtstextNichtLieferbar`, `NotstandVeraltet`, `TokenVermutlichUngueltig`) führt zu genau einem `AlertReporter::melde()`:
  - Schlüssel `consentlaw.<fall>` (`nicht-lieferbar`, `notstand-veraltet`, `token-vermutlich-ungueltig`), einer je Fall, unabhängig vom Typ.
  - Titel = `Stoerungsfall::bezeichnung()`.
  - Fehlertext des Events, Token maskiert; beim Token-Fall kein Fehlertext.
  - Details:
    - `Website` (app.url),
    - `Typ`,
    - `Sprache` (nur wenn gesetzt),
    - `Alter` als „<n> Tage“, unter einem Tag „<n> Stunden“ (nur Notstand),
    - `Was ist passiert` (Beschreibung),
    - `Was ist zu tun` (Maßnahmen als ein Text).

    Der Token-Fall hat weder `Typ` noch `Sprache`.
- **AK2 — Echter Ablauf:** Seitenaufruf bzw. Komponente und `consentlaw:reload` lösen die Meldung wie bisher die Mail aus.
- **AK3 — Token-Schutz:** Der Token steht weder im Klartext noch URL-kodiert in Fehlertext oder Details.
- **AK4 — Keine eigene Drosselung:** Jedes Event erzeugt eine Meldung; Sperre und Sammelmeldung übernimmt alerts über den festen Schlüssel.
- **AK5 — Seite bleibt heil:** Wirft der Reporter, wird das als Warnung geloggt (Token maskiert). Seitenaufruf, Fallback-Auslieferung, Exit-Code und Ausgabe von `consentlaw:reload` bleiben unverändert.
- **AK6 — Rückbau:**
  - Entfallen:
    - Config-Block `consentlaw.alerts`,
    - ENV `CONSENTLAW_ALERT_MAIL`, `CONSENTLAW_ALERT_MAILER`, `CONSENTLAW_ALERT_LISTENER` (ab jetzt wirkungslos),
    - View `consentlaw::mail.stoerung`,
    - Klassen `MeldeStoerungPerMail`, `StoerungsMail`,
    - Abhängigkeit `illuminate/mail`.
  - Der Listener ist immer registriert.
- **AK7 — Abhängigkeit & Doku:**
  - `composer.json`: `require ithilbert/alerts`.
  - README:
    - Störungsmeldung über alerts,
    - Migration `CONSENTLAW_ALERT_MAIL` → `ALERTS_MAIL_TO`,
    - Hinweis: Das Host-Projekt braucht das VCS-Repository von alerts in seiner `composer.json` (VCS-Repositories werden von Composer nicht transitiv aufgelöst).
  - CHANGELOG mit Breaking Change.

## Nicht-Ziele
- Änderungen an Erkennung oder Events (bleiben wie in stoerungs-meldung).
- Release/Tag von alerts oder consentlaw-client.
