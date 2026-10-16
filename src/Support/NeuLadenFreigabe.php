<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Support;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;

/**
 * Einzige Stelle, die entscheidet, ob der „Neu laden“-Button (Route und Anzeige) aktiv ist.
 *
 * Aktiv nur, wenn der Schalter `consentlaw.refresh_button` an ist UND die App nicht in der
 * Umgebung `production` läuft. Auf Production ist der Button bewusst nie möglich – dort
 * gelten `consentlaw:warm` bzw. der stündliche Abruf.
 */
final class NeuLadenFreigabe
{
    public function __construct(
        private readonly Config $config,
        private readonly Application $app,
    ) {}

    public function aktiv(): bool
    {
        return (bool) $this->config->get('consentlaw.refresh_button', false)
            && ! $this->app->environment('production');
    }
}
