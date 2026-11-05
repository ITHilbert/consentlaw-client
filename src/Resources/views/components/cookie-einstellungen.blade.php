{{-- Schaltfläche "Cookie-Einstellungen": öffnet den Banner erneut. Ohne Token oder bei ausgeschaltetem Banner leer. --}}
@if (app(\ITHilbert\ConsentLaw\Support\BannerFreigabe::class)->aktiv())
<button type="button" onclick="window.CookieScript && window.CookieScript.open();" {{ $attributes }}>{{ $slot->isEmpty() ? 'Cookie-Einstellungen' : $slot }}</button>
@endif
