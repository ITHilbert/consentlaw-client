{{--
    Gibt je Stelle das JSON-LD (Schema.org JobPosting, Google for Jobs) aus – sonst nichts.
    Der Aufbau liegt in JobPostingSchema (kein Literal-@context im Blade).

    Nutzung (z. B. bei eigenem Markup):
        <x-consentlaw::jobs-schema :jobs="$jobs" />
--}}
@props(['jobs' => []])

@foreach($jobs as $job)
<script type="application/ld+json">{!! \ITHilbert\ConsentLaw\Support\JobPostingSchema::json($job) !!}</script>
@endforeach
