{{-- Kurzform für <x-consentlaw::document type="impressum" /> --}}
@props(['locale' => null, 'showStaleNotice' => false])

<x-consentlaw::document type="impressum" :locale="$locale" :show-stale-notice="$showStaleNotice" {{ $attributes }}>
    {{ $slot ?? '' }}
</x-consentlaw::document>
