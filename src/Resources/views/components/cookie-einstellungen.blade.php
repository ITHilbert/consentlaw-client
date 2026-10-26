{{-- Link "Cookie-Einstellungen": öffnet den Banner erneut. Ohne Token oder bei ausgeschaltetem Banner leer. --}}
@if (app(\ITHilbert\ConsentLaw\Support\BannerFreigabe::class)->aktiv())
<a href="#" onclick="event.preventDefault(); window.CookieScript && window.CookieScript.open();" {{ $attributes }}>{{ $slot->isEmpty() ? 'Cookie-Einstellungen' : $slot }}</a>
@endif
