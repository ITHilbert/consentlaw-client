<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Support;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\HtmlInline;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Inline\Newline;

/**
 * Einzige Stelle im Package, die Markdown aus consentLaw in HTML wandelt.
 *
 * Der Inhalt kommt von einem externen System und landet ungeprüft auf der
 * Kunden-Homepage – rohes HTML darf deshalb nie durchgereicht werden:
 *
 *  - html_input = strip         → <script>, <iframe>, <img onerror=…> usw. fallen weg
 *  - allow_unsafe_links = false → javascript:-, data:- und vbscript:-Ziele fallen weg
 *
 * Einzige Ausnahme: <br>, <br/> und <br /> (üblich in Adresszeilen) werden zu
 * einem harten Zeilenumbruch und erscheinen als <br /> in der Ausgabe.
 * Das geschieht im geparsten Dokument statt per Textersetzung vorab – so bleiben
 * <br> in Code-Spans unberührt und Tabellenzeilen intakt.
 *
 * Gerendert wird GitHub-Flavored Markdown (Tabellen, Durchgestrichenes, Autolinks).
 */
final class MarkdownRenderer
{
    private static ?MarkdownConverter $converter = null;

    public static function render(string $markdown): string
    {
        return trim((string) self::converter()->convert($markdown)->getContent());
    }

    private static function converter(): MarkdownConverter
    {
        if (self::$converter !== null) {
            return self::$converter;
        }

        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addEventListener(DocumentParsedEvent::class, self::umbruecheUebernehmen(...));

        return self::$converter = new MarkdownConverter($environment);
    }

    /** Ersetzt inline <br>-Tags durch harte Zeilenumbrüche (bevor html_input sie entfernt). */
    private static function umbruecheUebernehmen(DocumentParsedEvent $event): void
    {
        $umbrueche = [];

        foreach ($event->getDocument()->iterator() as $node) {
            if ($node instanceof HtmlInline && preg_match('#^<br\s*/?>$#i', $node->getLiteral()) === 1) {
                $umbrueche[] = $node;
            }
        }

        // Erst nach dem Durchlauf ersetzen – sonst wird der Baum während der Iteration verändert.
        foreach ($umbrueche as $node) {
            $node->replaceWith(new Newline(Newline::HARDBREAK));
        }
    }
}
