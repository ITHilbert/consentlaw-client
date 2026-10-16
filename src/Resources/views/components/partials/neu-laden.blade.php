{{--
    „Neu laden“-Button (nur Entwicklung/Test). Wird von document und jobs eingebunden.

    Erscheint nur, wenn NeuLadenFreigabe aktiv ist (Schalter an, nicht production).
    Erwartet $typ (Dokumenttyp oder „jobs“). Darstellung per Klasse .consentlaw-refresh anpassbar.
--}}
@if(app(\ITHilbert\ConsentLaw\Support\NeuLadenFreigabe::class)->aktiv() && \Illuminate\Support\Facades\Route::has('consentlaw.neu-laden'))
    <form method="post" action="{{ route('consentlaw.neu-laden', $typ) }}" class="consentlaw-refresh">
        @csrf
        <button type="submit">{{ __('Neu laden') }}</button>
    </form>
@endif
