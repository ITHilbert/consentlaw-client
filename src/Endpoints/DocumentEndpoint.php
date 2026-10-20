<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Endpoints;

use ITHilbert\ConsentLaw\Data\Abrufergebnis;
use ITHilbert\ConsentLaw\Data\Document;
use ITHilbert\ConsentLaw\Support\ContentResolver;

/**
 * Zugriff auf ein Rechtsdokument (Impressum, Datenschutz, AGB, …).
 *
 * Ein Objekt pro Dokumenttyp – erzeugt vom ConsentLawClient.
 */
final readonly class DocumentEndpoint
{
    public function __construct(
        private ContentResolver $resolver,
        private string $type,
        private string $apiPath,
        private string $token,
        private ?string $locale,
    ) {}

    /** Das Dokument, oder null wenn weder API noch Fallback etwas liefern. */
    public function get(): ?Document
    {
        return $this->abrufen()->inhalt;
    }

    /**
     * Wie get(), nennt aber zusätzlich den Grund: aktuell, aus dem Fallback,
     * nicht veröffentlicht (404) oder Störung ohne Fallback.
     *
     * @return Abrufergebnis<Document>
     */
    public function abrufen(): Abrufergebnis
    {
        $abruf = $this->resolver->resolve(
            key: $this->cacheKey(),
            typ: $this->type,
            locale: $this->locale,
            endpoint: "api/{$this->apiPath}/{$this->token}",
            query: ['locale' => $this->locale],
        );

        if ($abruf->payload === null) {
            return new Abrufergebnis($abruf->status, null);
        }

        return new Abrufergebnis($abruf->status, inhalt: Document::fromApi(
            type: $this->type,
            payload: $abruf->payload,
            isStale: $abruf->isStale(),
            fetchedAt: $abruf->fetchedAt,
        ), stand: $abruf->fetchedAt);
    }

    /**
     * Verwirft Cache-Eintrag und Sperrvermerk und ruft sofort frisch ab.
     * Die eine Stelle für „Neu laden“ (Button und `consentlaw:reload`).
     *
     * @return Abrufergebnis<Document>
     */
    public function neuLaden(): Abrufergebnis
    {
        $this->forget();

        return $this->abrufen();
    }

    /** Nur der Inhalt, leerer String wenn nichts verfügbar ist. */
    public function content(): string
    {
        return $this->get()?->content ?? '';
    }

    public function exists(): bool
    {
        $document = $this->get();

        return $document !== null && ! $document->isEmpty();
    }

    /** Verwirft Cache-Eintrag und Sperrvermerk, erzwingt beim nächsten Zugriff einen API-Abruf. */
    public function refresh(): ?Document
    {
        $this->forget();

        return $this->get();
    }

    /** Verwirft Cache-Eintrag und Sperrvermerk (Fallback-Datei bleibt erhalten). */
    public function forget(): void
    {
        $this->resolver->forget($this->cacheKey());
    }

    /**
     * Der Token geht (gekürzt gehasht) in den Key ein: Nach einem Token-Wechsel
     * darf kein Inhalt der vorherigen Domain wiederverwendet werden.
     */
    private function cacheKey(): string
    {
        $parts = array_filter([$this->type, $this->locale, substr(sha1($this->token), 0, 8)]);

        return implode('.', $parts);
    }
}
