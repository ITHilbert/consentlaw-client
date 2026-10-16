<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;
use ITHilbert\ConsentLaw\Contracts\Transport;
use ITHilbert\ConsentLaw\Exceptions\ConsentLawResponseException;
use ITHilbert\ConsentLaw\Exceptions\ConsentLawUnavailableException;
use Throwable;

/**
 * HTTP-Transport auf Basis des Laravel-HTTP-Clients.
 *
 * Übersetzt fremde Exceptions (Guzzle/Connection) in projektinterne – nach oben
 * verlässt diese Klasse ausschliesslich eine ConsentLawException.
 *
 * Nur 404 gilt als klare Aussage des Servers („nicht veröffentlicht“,
 * ConsentLawResponseException). Jeder andere Fehlerstatus wird wie eine Störung
 * behandelt (ConsentLawUnavailableException).
 *
 * Der Token steht im URL-Pfad und darf in keiner Meldung stehen (auch nicht in der
 * von Guzzle/cURL): Meldungen nennen die URL nur mit maskiertem Token, fremde Meldungen
 * werden bereinigt, und die fremde Exception wird nicht als `previous` durchgereicht.
 */
final readonly class HttpTransport implements Transport
{
    public function __construct(
        private HttpFactory $http,
        private string $baseUrl,
        private int $timeout,
        private int $connectTimeout,
        private int $retries,
        private int $retryDelay,
    ) {}

    public function get(string $endpoint, array $query = []): array
    {
        $url = rtrim($this->baseUrl, '/').'/'.ltrim($endpoint, '/');
        $token = TokenMaskierung::ausEndpunkt($endpoint);
        $anzeigeUrl = TokenMaskierung::bereinige($url, $token);

        try {
            $request = $this->http
                ->timeout($this->timeout)
                ->connectTimeout($this->connectTimeout)
                ->acceptJson();

            if ($this->retries > 0) {
                // 404 ist eine klare Aussage des Servers und wird nicht wiederholt –
                // wiederholt werden nur Störungen (Verbindungsfehler, 5xx, andere Fehlerstatus).
                $request = $request->retry(
                    $this->retries + 1,
                    $this->retryDelay,
                    static fn (Throwable $e): bool => ! ($e instanceof RequestException && $e->response->status() === 404),
                    throw: false,
                );
            }

            $response = $request->get($url, array_filter(
                $query,
                static fn (mixed $value): bool => $value !== null && $value !== ''
            ));
        } catch (ConnectionException $e) {
            throw new ConsentLawUnavailableException(
                "consentLaw ist nicht erreichbar ({$anzeigeUrl}): ".TokenMaskierung::bereinige($e->getMessage(), $token)
            );
        } catch (Throwable $e) {
            throw new ConsentLawUnavailableException(
                "Unerwarteter Transportfehler bei {$anzeigeUrl}: ".TokenMaskierung::bereinige($e->getMessage(), $token)
            );
        }

        if ($response->status() === 404) {
            throw new ConsentLawResponseException(
                "consentLaw: Token ungültig oder Inhalt nicht veröffentlicht ({$anzeigeUrl}).",
                404,
            );
        }

        if ($response->failed()) {
            // Jeder andere Fehlerstatus (5xx, 429, 401, 403 …) ist keine Aussage über den
            // Inhalt, sondern eine Störung – der Fallback soll greifen.
            throw new ConsentLawUnavailableException(
                "consentLaw antwortete mit Status {$response->status()} ({$anzeigeUrl})."
            );
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new ConsentLawResponseException(
                "consentLaw lieferte keine gültige JSON-Antwort ({$anzeigeUrl}).",
                $response->status(),
            );
        }

        return $data;
    }
}
