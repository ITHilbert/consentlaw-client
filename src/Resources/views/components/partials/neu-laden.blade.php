{{--
    „Neu laden“-Button (Hilfen-Schalter APP_TEST). Wird von document und jobs am Anfang eingebunden.

    Erscheint nur, wenn NeuLadenFreigabe aktiv ist (Schalter an, in jeder Umgebung).
    Erwartet $typ (Dokumenttyp oder „jobs“); optional $locale (nur dann wird das Feld „locale“ mitgesendet).
    Darstellung: dezentes Entwicklungswerkzeug; die Regeln im <style>-Block haben niedrige Spezifität
    (eine Klasse) und lassen sich über .consentlaw-refresh im Seiten-CSS überschreiben. Keine externen Assets.
--}}
@if(app(\ITHilbert\ConsentLaw\Support\NeuLadenFreigabe::class)->aktiv())
    <style>
        .consentlaw-refresh { display: block; margin: 0 0 1rem; padding: 0; }
        .consentlaw-refresh button {
            display: inline-block;
            margin: 0;
            padding: 0.125rem 0.5rem;
            border: 1px dashed #9ca3af;
            border-radius: 0.25rem;
            background: transparent;
            color: #6b7280;
            font: inherit;
            font-size: 0.75rem;
            line-height: 1.5;
            cursor: pointer;
        }
        .consentlaw-refresh button:hover { color: #374151; border-color: #6b7280; }
        .consentlaw-refresh-meldung {
            margin: 0 0 1rem;
            padding: 0.25rem 0.5rem;
            border: 1px solid transparent;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            line-height: 1.5;
        }
        .consentlaw-refresh-meldung--ok { color: #166534; background: #f0fdf4; border-color: #bbf7d0; }
        .consentlaw-refresh-meldung--fehler { color: #991b1b; background: #fef2f2; border-color: #fecaca; }
    </style>
    @php
        $meldung = app(\ITHilbert\ConsentLaw\Support\NeuLadenRueckmeldung::class)->fuer(request(), $typ, $locale ?? null);
    @endphp
    @if($meldung)
        <p class="consentlaw-refresh-meldung consentlaw-refresh-meldung--{{ $meldung['ok'] ? 'ok' : 'fehler' }}" role="status">{{ $meldung['text'] }}</p>
    @endif
    <form method="post" action="{{ route('consentlaw.neu-laden', $typ) }}" class="consentlaw-refresh">
        @csrf
        <input type="hidden" name="zurueck" value="{{ request()->getRequestUri() }}">
        @if(! empty($locale))
            <input type="hidden" name="locale" value="{{ $locale }}">
        @endif
        <button type="submit">{{ __('Neu laden') }}</button>
    </form>
@endif
