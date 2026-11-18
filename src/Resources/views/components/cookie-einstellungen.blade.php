{{-- Schaltfläche "Cookie-Einstellungen": öffnet den Banner erneut. Ohne Token oder bei ausgeschaltetem Banner leer. --}}
@if (app(\ITHilbert\ConsentLaw\Support\BannerFreigabe::class)->aktiv())
@once
<style>:where(button[data-consentlaw-cookie-einstellungen]) { background: none; border: 0; padding: 0; font: inherit; color: inherit; cursor: pointer; }</style>
@endonce
<button type="button" data-consentlaw-cookie-einstellungen onclick="window.ConsentLaw && window.ConsentLaw.open();" {{ $attributes }}>{{ $slot->isEmpty() ? 'Cookie-Einstellungen' : $slot }}</button>
@endif
