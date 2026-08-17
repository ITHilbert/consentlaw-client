{{-- Kurzform für <x-consentlaw::document type="agb" /> --}}
@props(['locale' => null, 'showStaleNotice' => false])

<x-consentlaw::document type="agb" :locale="$locale" :show-stale-notice="$showStaleNotice" {{ $attributes }}>
    {{ $slot ?? '' }}
</x-consentlaw::document>
