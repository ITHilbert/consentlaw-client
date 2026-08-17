{{-- Kurzform für <x-consentlaw::document type="datenschutz" /> --}}
@props(['locale' => null, 'showStaleNotice' => false])

<x-consentlaw::document type="datenschutz" :locale="$locale" :show-stale-notice="$showStaleNotice" {{ $attributes }}>
    {{ $slot ?? '' }}
</x-consentlaw::document>
