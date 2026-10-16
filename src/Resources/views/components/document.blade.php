{{--
    Rendert ein consentLaw-Dokument.

    Nutzung:  <x-consentlaw::document type="impressum" />
              <x-consentlaw::impressum />          (Kurzform, siehe unten)

    Der Inhalt kommt als HTML oder Markdown von consentLaw. Markdown wird
    serverseitig und gefiltert gerendert (MarkdownRenderer: rohes HTML und
    unsichere Links fallen weg). HTML (format = html) wird unescaped ausgegeben;
    consentLaw filtert es beim Speichern.
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
                ? \ITHilbert\ConsentLaw\Support\MarkdownRenderer::render($document->content)
                : $document->content !!}
    </div>
@else
    {{-- Kein Inhalt verfügbar: bewusst leer, damit kein kaputtes Layout entsteht. --}}
    {{ $slot ?? '' }}
@endif

@include('consentlaw::components.partials.neu-laden', ['typ' => $type, 'locale' => $locale])
