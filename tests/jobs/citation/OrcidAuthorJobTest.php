<?php

/**
 * @file tests/jobs/citation/OrcidAuthorJobTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for OrcidAuthorJob's author matching and tier-aware rate limits.
 */

namespace PKP\tests\jobs\citation;

use Illuminate\Cache\RateLimiting\Limit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PKP\jobs\citation\OrcidAuthorJob;
use PKP\tests\PKPTestCase;
use ReflectionMethod;

#[RunTestsInSeparateProcesses]
#[CoversClass(OrcidAuthorJob::class)]
class OrcidAuthorJobTest extends PKPTestCase
{
    /** Matches OrcidAuthorJob::PER_SECOND_LIMIT. */
    protected const CONFIGURED_PER_SECOND_LIMIT = 11;

    /** Matches OrcidAuthorJob::DAILY_LIMIT. */
    protected const CONFIGURED_DAILY_LIMIT = 24500;

    protected const ORCID_ID = '0000-0002-1825-0097';

    private function job(string $orcidId = self::ORCID_ID): OrcidAuthorJob
    {
        return new OrcidAuthorJob(1, 2, $orcidId, 'contact@example.org');
    }

    private function invoke(OrcidAuthorJob $job, string $method, array $args = []): mixed
    {
        $reflected = new ReflectionMethod($job, $method);
        $reflected->setAccessible(true);
        return $reflected->invoke($job, ...$args);
    }

    /** The job finds its own author by iD, so a reordered or rewritten authors array still resolves. */
    public function testFindsItsAuthorByOrcidIdRegardlessOfPosition(): void
    {
        $authors = [
            ['givenName' => 'A', 'orcid' => '0000-0001-1111-1111'],
            ['givenName' => 'B', 'orcid' => ''],
            ['givenName' => 'C', 'orcid' => self::ORCID_ID],
        ];

        $this->assertSame(2, $this->invoke($this->job(), 'findAuthorIndex', [$authors]));
    }

    /** iDs are stored with or without the https://orcid.org/ prefix, so matching normalises both sides. */
    public function testMatchesRegardlessOfOrcidPrefix(): void
    {
        $authors = [['givenName' => 'C', 'orcid' => 'https://orcid.org/' . self::ORCID_ID]];

        $this->assertSame(0, $this->invoke($this->job(), 'findAuthorIndex', [$authors]));
        $this->assertSame(0, $this->invoke($this->job('https://orcid.org/' . self::ORCID_ID), 'findAuthorIndex', [$authors]));
    }

    /** The author (or their iD) can be gone by the time the job runs; that is not an error. */
    public function testReportsNoIndexWhenTheAuthorIsGone(): void
    {
        $authors = [['givenName' => 'A', 'orcid' => '0000-0001-1111-1111']];

        $this->assertNull($this->invoke($this->job(), 'findAuthorIndex', [$authors]));
        $this->assertNull($this->invoke($this->job(), 'findAuthorIndex', [[]]));
    }

    /**
     * One tier, whatever the journal has configured: ORCID's per-second limit plus the per-IP daily
     * quota, on separate counters so they cannot clobber each other.
     */
    public function testThrottledPerSecondAndPerDay(): void
    {
        /** @var Limit[] $limits */
        $limits = $this->invoke($this->job(), 'rateLimits');

        $this->assertCount(2, $limits);

        $maxAttemptsByDecay = [];
        foreach ($limits as $limit) {
            $maxAttemptsByDecay[$limit->decaySeconds] = $limit->maxAttempts;
            $this->assertStringStartsWith('orcid-lookups:', $limit->key);
        }

        $this->assertSame(self::CONFIGURED_PER_SECOND_LIMIT, $maxAttemptsByDecay[1] ?? null);
        $this->assertSame(self::CONFIGURED_DAILY_LIMIT, $maxAttemptsByDecay[60 * 60 * 24] ?? null);

        // Distinct keys, or the two counters would clobber each other.
        $this->assertCount(2, array_unique(array_map(fn (Limit $limit) => $limit->key, $limits)));
    }
}
