{{--
    Rendert ein consentLaw-Dokument.

    Nutzung:  <x-consentlaw::document type="impressum" />
              <x-consentlaw::impressum />          (Kurzform, siehe unten)

    Der Inhalt kommt als HTML oder Markdown von consentLaw. Markdown wird
    serverseitig gerendert; HTML wird bewusst unescaped ausgegeben, da es aus
    der eigenen, authentifizierten Quelle stammt (nicht aus Nutzereingaben).
--}}
@props([
    'type',
    'locale' => null,
    'class' => 'consentlaw-document',
    'showStaleNotice' => false,
])

@php
    $client   = app(\ITHilbert\ConsentLaw\ConsentLawClient::class);
    $document = ($locale ? $client->locale($locale) : $client)->document($type)->get();
@endphp

@if($document && ! $document->isEmpty())
    <div {{ $attributes->merge(['class' => $class]) }}>
        @if($showStaleNotice && $document->isStale)
            <p class="consentlaw-stale-notice" role="status">
                {{ __('Zuletzt aktualisiert:') }}
                {{ $document->fetchedAt?->diffForHumans() }}
            </p>
        @endif

        {!! $document->isMarkdown()
                ? \Illuminate\Support\Str::markdown($document->content)
                : $document->content !!}
    </div>
@else
    {{-- Kein Inhalt verfügbar: bewusst leer, damit kein kaputtes Layout entsteht. --}}
    {{ $slot ?? '' }}
@endif
