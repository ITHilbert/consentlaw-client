{{--
    Störungsmeldung von consentLaw (Mail-Listener MeldeStoerungPerMail).
    Überschreibbar: php artisan vendor:publish --tag=consentlaw-views
    → resources/views/vendor/consentlaw/mail/stoerung.blade.php

    Variablen:
      $fall       ITHilbert\ConsentLaw\Benachrichtigung\Stoerungsfall (bezeichnung(), beschreibung(),
                  massnahmen(), betrifftEinzelnenTyp())
      $appUrl     URL der Website (app.url)
      $zeitpunkt  Zeitpunkt der Meldung als Text
      $typ        Inhaltstyp (z. B. impressum, jobs) oder null
      $sprache    angefragte Sprache oder null
      $fehler     Fehlertext des Abrufs (Token maskiert) oder null
      $alter      Alter des ausgelieferten Fallback-Stands als Text („10 Tage“) oder null

    Ohne externe Assets (keine Bilder, Fonts, Stylesheets).
--}}
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>consentLaw-Störung: {{ $fall->bezeichnung() }}</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; font-size: 14px; line-height: 1.5; color: #222;">
    <h1 style="font-size: 18px;">consentLaw-Störung: {{ $fall->bezeichnung() }}</h1>

    <p>{{ $fall->beschreibung() }}</p>

    <table cellpadding="4" cellspacing="0" style="border-collapse: collapse;">
        <tr>
            <th align="left" valign="top">Website</th>
            <td>{{ $appUrl }}</td>
        </tr>
        <tr>
            <th align="left" valign="top">Fall</th>
            <td>{{ $fall->bezeichnung() }}</td>
        </tr>
        @if ($typ !== null)
            <tr>
                <th align="left" valign="top">Typ</th>
                <td>{{ $sprache !== null ? "{$typ} ({$sprache})" : $typ }}</td>
            </tr>
        @endif
        <tr>
            <th align="left" valign="top">Zeitpunkt</th>
            <td>{{ $zeitpunkt }}</td>
        </tr>
        @if ($alter !== null)
            <tr>
                <th align="left" valign="top">Alter des ausgelieferten Stands</th>
                <td>{{ $alter }}</td>
            </tr>
        @endif
        @if ($fehler !== null)
            <tr>
                <th align="left" valign="top">Fehlertext</th>
                <td><code>{{ $fehler }}</code></td>
            </tr>
        @endif
    </table>

    @if ($fall->betrifftEinzelnenTyp())
        <p>Gemeldet wird der Typ, bei dem die Störung zuerst auffiel – weitere Typen können betroffen sein.</p>
    @endif

    <h2 style="font-size: 16px;">Was ist zu tun?</h2>
    <ul>
        @foreach ($fall->massnahmen() as $massnahme)
            <li>{{ $massnahme }}</li>
        @endforeach
    </ul>

    <p style="color: #666; font-size: 12px;">
        Diese Meldung kommt höchstens einmal je Fall und 24 Stunden.
        Versendet vom Package ithilbert/consentlaw-client.
    </p>
</body>
</html>
