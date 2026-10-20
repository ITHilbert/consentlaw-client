<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Http;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use ITHilbert\ConsentLaw\ConsentLawClient;
use ITHilbert\ConsentLaw\Data\Abrufergebnis;
use ITHilbert\ConsentLaw\Data\Abrufstatus;
use ITHilbert\ConsentLaw\Exceptions\ConsentLawException;
use ITHilbert\ConsentLaw\Exceptions\MissingTokenException;
use ITHilbert\ConsentLaw\Support\NeuLadenFreigabe;
use ITHilbert\ConsentLaw\Support\NeuLadenRueckmeldung;

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
        private readonly NeuLadenRueckmeldung $rueckmeldung,
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
            $ergebnis = $istDokument ? $client->document($typ)->neuLaden() : $client->jobs()->neuLaden();
            $rueckmeldung = $this->rueckmeldung->aus($ergebnis);
        } catch (MissingTokenException) {
            $rueckmeldung = $this->rueckmeldung->keinToken();
        } catch (ConsentLawException) {
            // Sonstige Störung: Der Resolver hat sie bereits protokolliert, der Nutzer sieht
            // den bisherigen Stand bzw. den Fallback – keine Fehlerseite.
            $rueckmeldung = $this->rueckmeldung->aus(new Abrufergebnis(Abrufstatus::Stoerung, null));
        }

        $this->rueckmeldung->ablegen($request, $typ, $locale, $rueckmeldung);

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
     * Ziel nur auf dem eigenen Host: das Feld „zurueck“ (nur als lokaler Pfad), sonst Pfad und
     * Query des Referers, sonst die Startseite. Sonst wird keine Eingabe ausgewertet;
     * ein fremder Referer wird verworfen.
     */
    private function rueckleitung(Request $request): string
    {
        $basis = $request->getSchemeAndHttpHost();
        $zurueck = $request->input('zurueck');

        if (is_string($zurueck) && $this->istLokalerPfad($zurueck)) {
            return $basis.$zurueck;
        }

        $referer = parse_url((string) $request->headers->get('referer'));

        if (! is_array($referer) || strcasecmp((string) ($referer['host'] ?? ''), $request->getHost()) !== 0) {
            return $basis.'/';
        }

        $pfad = (string) ($referer['path'] ?? '');
        $query = isset($referer['query']) ? '?'.$referer['query'] : '';

        return $basis.($pfad === '' || $pfad[0] !== '/' ? '/' : '').$pfad.$query;
    }

    /**
     * Lokaler Pfad: genau ein führendes „/“ (nicht „//“ oder „/\“), keine Steuerzeichen,
     * weder Schema noch Host.
     */
    private function istLokalerPfad(string $wert): bool
    {
        if ($wert === '' || $wert[0] !== '/' || (isset($wert[1]) && ($wert[1] === '/' || $wert[1] === '\\'))) {
            return false;
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $wert) === 1) {
            return false;
        }

        $teile = parse_url($wert);

        return is_array($teile) && ! isset($teile['scheme']) && ! isset($teile['host']);
    }
}
