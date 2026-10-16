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
use League\CommonMark\Node\Inline\Text;

/**
 * Einzige Stelle im Package, die Markdown aus consentLaw in HTML wandelt.
 *
 * Der Inhalt kommt von einem externen System und landet ungeprüft auf der
 * Kunden-Homepage – rohes HTML darf deshalb nie durchgereicht werden:
 *
 *  - html_input = strip         → <script>, <iframe>, <img onerror=…> usw. fallen weg
 *  - allow_unsafe_links = false → javascript:-, data:- und vbscript:-Ziele fallen weg
 *
 * Zeilenumbrüche: consentLaw pflegt Texte zeilenweise (Adressblöcke mit einfachem
 * Zeilenumbruch). Deshalb ist auch ein weicher Umbruch ein <br /> (`renderer.soft_break`);
 * Leerzeilen trennen weiterhin Absätze, Code-Blöcke bleiben unberührt.
 *
 * Außerdem werden <br>, <br/> und <br /> (üblich in Adresszeilen) zu einem harten
 * Zeilenumbruch und erscheinen als <br /> in der Ausgabe. Steht das <br> direkt vor einem
 * Zeilenende, entfällt es – das Zeilenende erzeugt bereits das <br /> (kein doppelter Umbruch).
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
            'renderer' => ['soft_break' => "<br />\n"],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addEventListener(DocumentParsedEvent::class, self::umbruecheUebernehmen(...));

        return self::$converter = new MarkdownConverter($environment);
    }

    /**
     * Ersetzt inline <br>-Tags durch harte Zeilenumbrüche (bevor html_input sie entfernt).
     * Ein <br> direkt vor einem Zeilenende wird nur entfernt: Das Zeilenende ist bereits ein <br />.
     */
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
            $folgenden = self::leerzeichenVorZeilenende($node);

            if ($folgenden !== null) {
                foreach ($folgenden as $leerzeichen) {
                    $leerzeichen->detach();
                }
                $node->detach();

                continue;
            }

            $node->replaceWith(new Newline(Newline::HARDBREAK));
        }
    }

    /**
     * Prüft, ob nach dem Knoten (ggf. nur durch Leerzeichen/Tabs getrennt) ein Zeilenende folgt.
     *
     * @return list<Text>|null die Leerzeichen-Knoten dazwischen, null wenn kein Zeilenende folgt
     */
    private static function leerzeichenVorZeilenende(HtmlInline $node): ?array
    {
        $leerzeichen = [];

        for ($folgend = $node->next(); $folgend !== null; $folgend = $folgend->next()) {
            if ($folgend instanceof Newline) {
                return $leerzeichen;
            }

            if (! $folgend instanceof Text || trim($folgend->getLiteral(), " \t") !== '') {
                return null;
            }

            $leerzeichen[] = $folgend;
        }

        return null;
    }
}
