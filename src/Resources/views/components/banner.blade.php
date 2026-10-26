{{-- Consent-Banner: lädt banner.js von consentLaw. Ohne Token oder bei ausgeschaltetem Banner leer. --}}
@php($scriptUrl = app(\ITHilbert\ConsentLaw\Support\BannerFreigabe::class)->scriptUrl())
@if ($scriptUrl !== null)
<script src="{{ $scriptUrl }}" defer></script>
@endif
