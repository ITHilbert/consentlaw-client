{{--
    „Neu laden“-Button (nur Entwicklung/Test). Wird von document und jobs eingebunden.

    Erscheint nur, wenn NeuLadenFreigabe aktiv ist (Schalter an, nicht production).
    Erwartet $typ (Dokumenttyp oder „jobs“); optional $locale (nur dann wird das Feld „locale“ mitgesendet). Darstellung per Klasse .consentlaw-refresh anpassbar.
--}}
@if(app(\ITHilbert\ConsentLaw\Support\NeuLadenFreigabe::class)->aktiv() && \Illuminate\Support\Facades\Route::has('consentlaw.neu-laden'))
    <form method="post" action="{{ route('consentlaw.neu-laden', $typ) }}" class="consentlaw-refresh">
        @csrf
        @if(! empty($locale))
            <input type="hidden" name="locale" value="{{ $locale }}">
        @endif
        <button type="submit">{{ __('Neu laden') }}</button>
    </form>
@endif
