{{--
    Listet die Stellenangebote der Domain.

    Nutzung:
        <x-consentlaw::jobs />
        <x-consentlaw::jobs :include-expired="true" />
        <x-consentlaw::jobs initiativ-email="jobs@example.de" />

    Ohne Stelle und ohne Slot erscheint der Standardtext „Zurzeit sind keine
    offenen Stellen ausgeschrieben.“, bei gültiger Adresse (Attribut
    initiativ-email oder Config consentlaw.jobs.initiativ_email) mit Satz zur
    Initiativbewerbung. Ein nicht leerer Slot ersetzt beides.

    Eigenes Markup gewünscht? Dann statt dieser Komponente die Daten über
    KomponentenInhalt holen und das JSON-LD per <x-consentlaw::jobs-schema />
    ausgeben – Beispiel: README, Abschnitt „Eigenes Markup“.

    Das <script type="application/ld+json"> liefert strukturierte Daten für
    Google for Jobs (Schema.org JobPosting).
--}}
@props([
    'locale' => null,
    'includeExpired' => false,
    'class' => 'consentlaw-jobs',
    'withSchema' => true,
    'initiativEmail' => null,
])

@include('consentlaw::components.partials.neu-laden', ['typ' => 'jobs', 'locale' => $locale])

@php
    $jobs = app(\ITHilbert\ConsentLaw\Support\KomponentenInhalt::class)->jobs($locale, (bool) $includeExpired);
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
                    {!! $job->descriptionHtml() !!}
                </div>
            </article>

        @endforeach
    </div>

    @if($withSchema)
        <x-consentlaw::jobs-schema :jobs="$jobs" />
    @endif
@elseif(trim((string) $slot) !== '')
    {{ $slot }}
@else
    @php
        $gueltig = static function (mixed $adresse): ?string {
            $adresse = is_string($adresse) ? trim($adresse) : '';

            return $adresse !== '' && filter_var($adresse, FILTER_VALIDATE_EMAIL) !== false ? $adresse : null;
        };
        $initiativ = $gueltig($initiativEmail) ?? $gueltig(config('consentlaw.jobs.initiativ_email'));
        $leerKlasse = 'consentlaw-jobs__leer'.($class !== 'consentlaw-jobs' && $class !== '' ? ' '.$class : '');
    @endphp
    <p {{ $attributes->merge(['class' => $leerKlasse]) }}>{{ __('Zurzeit sind keine offenen Stellen ausgeschrieben.') }}@if($initiativ !== null) {!! __('Initiativbewerbungen gern an :adresse.', ['adresse' => '<a href="mailto:'.e($initiativ).'">'.e($initiativ).'</a>']) !!}@endif</p>
@endif
