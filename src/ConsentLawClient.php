<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw;

use ITHilbert\ConsentLaw\Contracts\FallbackStore;
use ITHilbert\ConsentLaw\Endpoints\DocumentEndpoint;
use ITHilbert\ConsentLaw\Endpoints\JobsEndpoint;
use ITHilbert\ConsentLaw\Exceptions\MissingTokenException;
use ITHilbert\ConsentLaw\Support\ContentResolver;

/**
 * Einstiegspunkt des Packages.
 *
 *     ConsentLaw::impressum()->content()
 *     ConsentLaw::jobs()->current()
 *     ConsentLaw::locale('en')->datenschutz()->get()
 */
class ConsentLawClient
{
    /**
     * API-Pfad je Dokumenttyp. Schlüssel = im Code/Views genutzter Typ.
     *
     * @var array<string, string>
     */
    public const DOCUMENT_TYPES = [
        'impressum' => 'impressum',
        'datenschutz' => 'datenschutz',
        'agb' => 'agb',
        'cookie-richtlinie' => 'cookie-richtlinie',
        'avv' => 'avv',
    ];

    public function __construct(
        private readonly ContentResolver $resolver,
        private readonly FallbackStore $fallback,
        private readonly ?string $token,
        private ?string $locale = null,
    ) {}

    /** Gibt eine Instanz zurück, die eine bestimmte Sprache abfragt. */
    public function locale(?string $locale): self
    {
        $clone = clone $this;
        $clone->locale = $locale;

        return $clone;
    }

    // ── Dokumente ────────────────────────────────────────────────────────────

    public function impressum(): DocumentEndpoint
    {
        return $this->document('impressum');
    }

    public function datenschutz(): DocumentEndpoint
    {
        return $this->document('datenschutz');
    }

    public function agb(): DocumentEndpoint
    {
        return $this->document('agb');
    }

    public function cookieRichtlinie(): DocumentEndpoint
    {
        return $this->document('cookie-richtlinie');
    }

    public function avv(): DocumentEndpoint
    {
        return $this->document('avv');
    }

    /**
     * Beliebiges Dokument nach Typ – nützlich für Schleifen und Routen.
     *
     * @throws \InvalidArgumentException bei unbekanntem Typ
     * @throws MissingTokenException wenn kein Token konfiguriert ist
     */
    public function document(string $type): DocumentEndpoint
    {
        $path = self::DOCUMENT_TYPES[$type] ?? null;

        if ($path === null) {
            throw new \InvalidArgumentException(
                "Unbekannter Dokumenttyp '{$type}'. Erlaubt: "
                .implode(', ', array_keys(self::DOCUMENT_TYPES))
            );
        }

        return new DocumentEndpoint(
            resolver: $this->resolver,
            type: $type,
            apiPath: $path,
            token: $this->requireToken(),
            locale: $this->locale,
        );
    }

    // ── Jobs ─────────────────────────────────────────────────────────────────

    public function jobs(): JobsEndpoint
    {
        return new JobsEndpoint(
            resolver: $this->resolver,
            token: $this->requireToken(),
            locale: $this->locale,
        );
    }

    // ── Wartung ──────────────────────────────────────────────────────────────

    /** True, wenn ein Token konfiguriert ist (für Health-Checks/Views). */
    public function isConfigured(): bool
    {
        return $this->token !== null && $this->token !== '';
    }

    /**
     * Verwirft Cache und Fallback vollständig.
     *
     * Die Cache-Keys enthalten Locale und Token-Hash; deshalb wird pro Typ über
     * die Endpoint-Objekte invalidiert, statt Keys hier nachzubauen.
     */
    public function flush(): void
    {
        if ($this->isConfigured()) {
            foreach (array_keys(self::DOCUMENT_TYPES) as $type) {
                $this->document($type)->forget();
            }

            $this->jobs()->forget();
        }

        $this->fallback->flush();
    }

    private function requireToken(): string
    {
        if (! $this->isConfigured()) {
            throw MissingTokenException::make();
        }

        return (string) $this->token;
    }
}
