<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Tests\Fakes;

use ITHilbert\Alerts\Contracts\AlertReporter;
use ITHilbert\Alerts\Domain\Meldung;

/** Zeichnet Meldungen an das Basis-Package alerts auf; kann einen kaputten Reporter simulieren. */
final class FakeAlertReporter implements AlertReporter
{
    /** @var list<Meldung> */
    public array $meldungen = [];

    public ?\Throwable $wirft = null;

    public function melde(Meldung $meldung): void
    {
        if ($this->wirft !== null) {
            throw $this->wirft;
        }

        $this->meldungen[] = $meldung;
    }
}
