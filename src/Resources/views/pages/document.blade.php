{{-- Fertige Seite für ein Rechtsdokument (nur bei consentlaw.routes.enabled). --}}
@php
    $titles = [
        'impressum' => __('Impressum'),
        'datenschutz' => __('Datenschutzerklärung'),
        'agb' => __('Allgemeine Geschäftsbedingungen'),
        'cookie-richtlinie' => __('Cookie-Richtlinie'),
        'avv' => __('Auftragsverarbeitungsvertrag'),
    ];
    $pageTitle = $titles[$document->type] ?? ucfirst($document->type);
@endphp

@extends($layout ?? 'consentlaw::layouts.minimal')

@section('title', $pageTitle)

@section('content')
    <article class="consentlaw-page">
        {!! $document->isMarkdown()
                ? \ITHilbert\ConsentLaw\Support\MarkdownRenderer::render($document->content)
                : $document->content !!}
    </article>
@endsection
