<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Http;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use ITHilbert\ConsentLaw\ConsentLawClient;
use ITHilbert\ConsentLaw\Exceptions\ConsentLawException;
use ITHilbert\ConsentLaw\Support\NeuLadenFreigabe;

/**
 * Lädt einen Inhalt per Button frisch von consentLaw (nur bei freigegebenem Button,
 * siehe {@see NeuLadenFreigabe}).
 */
final class NeuLadenController
{
    public const JOBS = 'jobs';

    public function __construct(private readonly ConsentLawClient $client) {}

    public function __invoke(Request $request, string $typ): RedirectResponse
    {
        $istDokument = array_key_exists($typ, ConsentLawClient::DOCUMENT_TYPES);

        abort_unless($istDokument || $typ === self::JOBS, 404);

        try {
            $istDokument ? $this->client->document($typ)->refresh() : $this->client->jobs()->refresh();
        } catch (ConsentLawException) {
            // Störung (z. B. fehlender Token): Der Resolver hat sie bereits protokolliert,
            // der Nutzer sieht den bisherigen Stand bzw. den Fallback – keine Fehlerseite.
        }

        return redirect()->to($this->rueckleitung($request));
    }

    /**
     * Ziel nur auf dem eigenen Host: Pfad und Query des Referers, sonst die Startseite.
     * Eingabeparameter werden nie ausgewertet; ein fremder Referer wird verworfen.
     */
    private function rueckleitung(Request $request): string
    {
        $basis = $request->getSchemeAndHttpHost();
        $referer = parse_url((string) $request->headers->get('referer'));

        if (! is_array($referer) || strcasecmp((string) ($referer['host'] ?? ''), $request->getHost()) !== 0) {
            return $basis.'/';
        }

        $pfad = (string) ($referer['path'] ?? '');
        $query = isset($referer['query']) ? '?'.$referer['query'] : '';

        return $basis.($pfad === '' || $pfad[0] !== '/' ? '/' : '').$pfad.$query;
    }
}
