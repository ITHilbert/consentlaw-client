<?php

declare(strict_types=1);

namespace ITHilbert\ConsentLaw\Tests;

use Carbon\CarbonImmutable;
use ITHilbert\ConsentLaw\Data\Job;
use PHPUnit\Framework\TestCase;

final class JobTest extends TestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'id' => 'abc-123',
            'title' => 'Senior PHP-Entwickler',
            'description' => '## Aufgaben',
            'description_format' => 'markdown',
            'location' => 'Kiel',
            'employment_type' => 'freelance',
            'employment_type_label' => 'Freiberufler',
            'employment_type_schema' => 'CONTRACTOR',
            'date_posted' => '2026-08-01T10:00:00+00:00',
            'is_global' => true,
        ], $overrides);
    }

    public function test_mappt_alle_felder(): void
    {
        $job = Job::fromApi($this->payload());

        $this->assertSame('abc-123', $job->id);
        $this->assertSame('Senior PHP-Entwickler', $job->title);
        $this->assertSame('Freiberufler', $job->employmentTypeLabel);
        $this->assertSame('CONTRACTOR', $job->employmentTypeSchema);
        $this->assertTrue($job->isGlobal);
        $this->assertTrue($job->isMarkdown());
    }

    public function test_abgelaufene_frist_wird_erkannt(): void
    {
        $past = Job::fromApi($this->payload([
            'valid_through' => CarbonImmutable::now()->subDay()->toIso8601String(),
        ]));
        $future = Job::fromApi($this->payload([
            'valid_through' => CarbonImmutable::now()->addDay()->toIso8601String(),
        ]));

        $this->assertTrue($past->isExpired());
        $this->assertFalse($future->isExpired());
    }

    public function test_ohne_frist_nie_abgelaufen(): void
    {
        $this->assertFalse(Job::fromApi($this->payload())->isExpired());
    }

    public function test_leere_optionale_felder_werden_null(): void
    {
        $job = Job::fromApi(['id' => '1', 'title' => 'T', 'location' => '']);

        $this->assertNull($job->location);
        $this->assertNull($job->employmentType);
        $this->assertNull($job->datePosted);
    }
}
