<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Tests;

use Carbon\CarbonImmutable;
use ITHilbert\ConsentLaw\Data\FallbackEntry;
use PHPUnit\Framework\TestCase;

final class FallbackEntryTest extends TestCase
{
    /** diffInSeconds() liefert float – ohne Cast gab es hier einen TypeError. */
    public function test_alter_ist_ein_integer(): void
    {
        $entry = new FallbackEntry(['content' => 'x'], CarbonImmutable::now()->subSeconds(90));

        $this->assertIsInt($entry->ageInSeconds());
        $this->assertGreaterThanOrEqual(89, $entry->ageInSeconds());
        $this->assertLessThanOrEqual(91, $entry->ageInSeconds());
    }

    public function test_zukunftsdatum_ergibt_kein_negatives_alter(): void
    {
        $entry = new FallbackEntry([], CarbonImmutable::now()->addHour());

        $this->assertSame(0, $entry->ageInSeconds());
    }
}
