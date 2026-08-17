{{--
    Listet die Stellenangebote der Domain.

    Nutzung:
        <x-consentlaw::jobs />
        <x-consentlaw::jobs :include-expired="true" />

    Eigenes Markup gewünscht? Dann statt dieser Komponente direkt die Daten
    nutzen und im eigenen Layout ausgeben:

        @foreach(ConsentLaw::jobs()->current() as $job) ... @endforeach

    Das <script type="application/ld+json"> liefert strukturierte Daten für
    Google for Jobs (Schema.org JobPosting).
--}}
@props([
    'locale' => null,
    'includeExpired' => false,
    'class' => 'consentlaw-jobs',
    'withSchema' => true,
])

@php
    $client = app(\ITHilbert\ConsentLaw\ConsentLawClient::class);
    $endpoint = ($locale ? $client->locale($locale) : $client)->jobs();
    $jobs = $includeExpired ? $endpoint->all() : $endpoint->current();
@endphp

@if($jobs->isNotEmpty())
    <div {{ $attributes->merge(['class' => $class]) }}>
        @foreach($jobs as $job)
            <article class="consentlaw-job" id="job-{{ $job->id }}">
                <h2 class="consentlaw-job__title">{{ $job->title }}</h2>

                @if($job->employmentTypeLabel || $job->location || $job->validThrough)
                    <p class="consentlaw-job__meta">
                        @if($job->employmentTypeLabel){{ $job->employmentTypeLabel }}@endif
                        @if($job->location) &middot; {{ $job->location }} @endif
                        @if($job->validThrough) &middot; {{ __('Bewerbungsfrist') }}: {{ $job->validThrough->format('d.m.Y') }} @endif
                    </p>
                @endif

                <div class="consentlaw-job__description">
                    {!! $job->isMarkdown()
                            ? \Illuminate\Support\Str::markdown($job->description)
                            : $job->description !!}
                </div>
            </article>

            @if($withSchema)
                <script type="application/ld+json">
                    {!! json_encode(array_filter([
                        '@context' => 'https://schema.org',
                        '@type' => 'JobPosting',
                        'title' => $job->title,
                        'description' => $job->isMarkdown()
                            ? \Illuminate\Support\Str::markdown($job->description)
                            : $job->description,
                        'employmentType' => $job->employmentTypeSchema,
                        'datePosted' => $job->datePosted?->toIso8601String(),
                        'validThrough' => $job->validThrough?->toIso8601String(),
                        'jobLocation' => $job->location ? [
                            '@type' => 'Place',
                            'address' => ['@type' => 'PostalAddress', 'addressLocality' => $job->location],
                        ] : null,
                    ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}
                </script>
            @endif
        @endforeach
    </div>
@else
    {{ $slot ?? '' }}
@endif
