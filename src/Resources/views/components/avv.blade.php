{{-- Kurzform für <x-consentlaw::document type="avv" /> --}}
@props(['locale' => null, 'showStaleNotice' => false])

<x-consentlaw::document type="avv" :locale="$locale" :show-stale-notice="$showStaleNotice" {{ $attributes }}>
    {{ $slot ?? '' }}
</x-consentlaw::document>
