<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Http;

use Illuminate\Http\Request;
use Illuminate\View\View;
use ITHilbert\ConsentLaw\ConsentLawClient;

/**
 * Rendert die optionalen Fertig-Seiten (nur aktiv bei consentlaw.routes.enabled).
 */
final class ConsentLawPageController
{
    public function __construct(private readonly ConsentLawClient $client) {}

    public function show(Request $request): View
    {
        /** @var string $type */
        $type = $request->route()->defaults['consentlawType'];

        if ($type === 'jobs') {
            return view('consentlaw::pages.jobs', [
                'jobs' => $this->client->jobs()->current(),
                'layout' => config('consentlaw.routes.layout'),
            ]);
        }

        $document = $this->client->document($type)->get();

        abort_if($document === null || $document->isEmpty(), 404);

        return view('consentlaw::pages.document', [
            'document' => $document,
            'layout' => config('consentlaw.routes.layout'),
        ]);
    }
}
