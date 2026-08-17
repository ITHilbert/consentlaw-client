{{-- Kurzform für <x-consentlaw::document type="cookie-richtlinie" /> --}}
@props(['locale' => null, 'showStaleNotice' => false])

<x-consentlaw::document type="cookie-richtlinie" :locale="$locale" :show-stale-notice="$showStaleNotice" {{ $attributes }}>
    {{ $slot ?? '' }}
</x-consentlaw::document>
