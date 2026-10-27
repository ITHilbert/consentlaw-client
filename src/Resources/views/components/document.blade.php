{{--
    Rendert ein consentLaw-Dokument.

    Nutzung:  <x-consentlaw::document type="impressum" />
              <x-consentlaw::impressum />          (Kurzform, siehe unten)

    Der Inhalt kommt als HTML oder Markdown von consentLaw. Markdown wird
    serverseitig und gefiltert gerendert (Document::contentHtml(), Markdown-Filter: rohes HTML und
    unsichere Links fallen weg). HTML (format = html) wird unescaped ausgegeben;
    consentLaw filtert es beim Speichern.
--}}
@props([
    'type',
    'locale' => null,
    'class' => 'consentlaw-document',
    'showStaleNotice' => false,
])

@include('consentlaw::components.partials.neu-laden', ['typ' => $type, 'locale' => $locale])

@php
    $document = app(\ITHilbert\ConsentLaw\Support\KomponentenInhalt::class)->dokument($type, $locale);
@endphp

@if($document && ! $document->isEmpty())
    <div {{ $attributes->merge(['class' => $class]) }}>
        @if($showStaleNotice && $document->isStale)
            <p class="consentlaw-stale-notice" role="status">
                {{ __('Zuletzt aktualisiert:') }}
                {{ $document->fetchedAt?->diffForHumans() }}
            </p>
        @endif

        {!! $document->contentHtml() !!}
    </div>
@else
    {{-- Kein Inhalt verfügbar: bewusst leer, damit kein kaputtes Layout entsteht. --}}
    {{ $slot ?? '' }}
@endif
