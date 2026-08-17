{{-- Fertige Stellen-Übersicht (nur bei consentlaw.routes.enabled). --}}
@extends($layout ?? 'consentlaw::layouts.minimal')

@section('title', __('Stellenangebote'))

@section('content')
    <h1>{{ __('Stellenangebote') }}</h1>

    <x-consentlaw::jobs>
        <p>{{ __('Zurzeit sind keine Stellen ausgeschrieben.') }}</p>
    </x-consentlaw::jobs>
@endsection
