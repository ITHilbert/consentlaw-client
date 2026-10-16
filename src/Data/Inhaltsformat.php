<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Data;

/**
 * Format eines Inhalts (Dokument oder Job-Beschreibung).
 *
 * Allowlist: Nur der exakte Wert `html` gilt als HTML und wird ungefiltert ausgegeben.
 * Jeder andere Wert (leer, `HTML`, `text`, unbekannt, falscher Datentyp, fehlend) gilt als
 * Markdown und läuft durch den Filter des MarkdownRenderers.
 */
enum Inhaltsformat: string
{
    case Markdown = 'markdown';
    case Html = 'html';

    public static function ausApi(mixed $wert): self
    {
        return $wert === 'html' ? self::Html : self::Markdown;
    }
}
