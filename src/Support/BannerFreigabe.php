<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Support;

use Illuminate\Contracts\Config\Repository as Config;

/**
 * Einzige Stelle, die entscheidet, ob der Consent-Banner aktiv ist (Komponenten `banner`
 * und `cookie-einstellungen`): Token gesetzt und `consentlaw.banner.enabled` eingeschaltet.
 * Greift nie auf die consentLaw-API, den Cache oder den Fallback zu.
 */
final class BannerFreigabe
{
    public function __construct(
        private readonly Config $config,
    ) {}

    public function aktiv(): bool
    {
        $token = $this->config->get('consentlaw.token');

        return is_string($token) && $token !== ''
            && (bool) $this->config->get('consentlaw.banner.enabled', true);
    }

    /** Adresse von banner.js; null, wenn der Banner nicht aktiv ist. */
    public function scriptUrl(): ?string
    {
        if (! $this->aktiv()) {
            return null;
        }

        $basis = rtrim((string) $this->config->get('consentlaw.base_url'), '/');

        return $basis.'/banner.js?token='.rawurlencode((string) $this->config->get('consentlaw.token'));
    }
}
