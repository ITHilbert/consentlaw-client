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

    private const LOCALE_MUSTER = '/^[a-z]{2}([_-][A-Za-z]{2})?$/D';

    public function __construct(
        private readonly ConsentLawClient $client,
        private readonly NeuLadenFreigabe $freigabe,
    ) {}

    public function __invoke(Request $request, string $typ): RedirectResponse
    {
        // Auch in der Aktion prüfen: Die Route kann aus einem Route-Cache stammen,
        // obwohl die Freigabe hier nicht (mehr) aktiv ist.
        abort_unless($this->freigabe->aktiv(), 404);

        $istDokument = array_key_exists($typ, ConsentLawClient::DOCUMENT_TYPES);

        abort_unless($istDokument || $typ === self::JOBS, 404);

        $locale = $this->locale($request);
        $client = $locale === null ? $this->client : $this->client->locale($locale);

        try {
            $istDokument ? $client->document($typ)->refresh() : $client->jobs()->refresh();
        } catch (ConsentLawException) {
            // Störung (z. B. fehlender Token): Der Resolver hat sie bereits protokolliert,
            // der Nutzer sieht den bisherigen Stand bzw. den Fallback – keine Fehlerseite.
        }

        return redirect()->to($this->rueckleitung($request));
    }

    /**
     * Optionale Sprachvariante aus dem Formular; ungültige Angaben → 422 ohne API-Aufruf.
     */
    private function locale(Request $request): ?string
    {
        $locale = $request->input('locale');

        if ($locale === null || $locale === '') {
            return null;
        }

        // Kein Validator: Das Paket setzt keinen Validation-Provider voraus.
        abort_unless(is_string($locale) && preg_match(self::LOCALE_MUSTER, $locale) === 1, 422);

        return $locale;
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
