<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Support;

use Illuminate\Contracts\Config\Repository as Config;

/**
 * Einzige Stelle, die entscheidet, ob der „Neu laden“-Button (Route und Anzeige) aktiv ist.
 *
 * Aktiv ist er genau dann, wenn der Schalter `consentlaw.refresh_button` (ENV `APP_TEST`)
 * an ist – in jeder Umgebung, auch auf `production`.
 */
final class NeuLadenFreigabe
{
    public function __construct(
        private readonly Config $config,
    ) {}

    public function aktiv(): bool
    {
        return (bool) $this->config->get('consentlaw.refresh_button', false);
    }
}
