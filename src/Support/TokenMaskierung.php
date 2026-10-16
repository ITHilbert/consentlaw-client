<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Support;

/**
 * Einzige Stelle, die den Domain-Token für Meldungen und Logs unkenntlich macht.
 *
 * Der Token steht im URL-Pfad (`api/impressum/<token>`) und damit auch in Meldungen
 * fremder Bibliotheken (Guzzle/cURL nennen die vollständige URL). Er darf weder in einer
 * Exception-Meldung noch im Log erscheinen – sichtbar bleiben höchstens die ersten
 * {@see self::SICHTBAR} Zeichen, kürzere Tokens werden vollständig maskiert.
 */
final class TokenMaskierung
{
    /** Anzahl der Zeichen, die von einem Token höchstens sichtbar bleiben. */
    private const SICHTBAR = 4;

    /** Maske für Tokens bis einschließlich {@see self::SICHTBAR} Zeichen. */
    private const VOLLMASKE = '***';

    /** Das Token steht laut API-Vertrag im letzten Pfadsegment des Endpunkts. */
    public static function ausEndpunkt(string $endpoint): string
    {
        $pfad = (string) parse_url($endpoint, PHP_URL_PATH);

        return rawurldecode(basename(rtrim($pfad, '/')));
    }

    public static function maskiere(string $token): string
    {
        if (mb_strlen($token) <= self::SICHTBAR) {
            return self::VOLLMASKE;
        }

        return mb_substr($token, 0, self::SICHTBAR).'…';
    }

    /** Ersetzt den Token (auch URL-kodiert) in einem beliebigen Text durch die Maske. */
    public static function bereinige(string $text, string $token): string
    {
        if ($token === '') {
            return $text;
        }

        return str_replace(
            array_values(array_unique([$token, rawurlencode($token), urlencode($token)])),
            self::maskiere($token),
            $text,
        );
    }
}
