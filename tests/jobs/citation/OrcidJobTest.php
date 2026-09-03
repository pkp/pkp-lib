<?php

/**
 * @file tests/jobs/citation/OrcidJobTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for the OrcidJob's proactive rate-limiter self-throttling.
 */

namespace PKP\tests\jobs\citation;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\RateLimiter as CacheRateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Cache\Repository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PKP\jobs\citation\OrcidJob;
use PKP\tests\PKPTestCase;
use ReflectionMethod;

#[RunTestsInSeparateProcesses]
#[CoversClass(OrcidJob::class)]
class OrcidJobTest extends PKPTestCase
{
    /** Matches OrcidJob::PER_SECOND_LIMIT. */
    protected const CONFIGURED_PER_SECOND_LIMIT = 11;

    /** Matches OrcidJob::DAILY_LIMIT. */
    protected const CONFIGURED_DAILY_LIMIT = 24500;

    protected ArrayStore $arrayStore;

    protected function setUp(): void
    {
        parent::setUp();

        // Bind an isolated, in-memory cache store for the rate limiter instead of the app's
        // configured (file-based) default, so this test doesn't depend on filesystem locking.
        $this->arrayStore = new ArrayStore();
        app()->instance(CacheRateLimiter::class, new CacheRateLimiter(new Repository($this->arrayStore)));
    }

    /**
     * OrcidJob makes one ORCID HTTP request per author needing a lookup (unlike Crossref/
     * OpenAlex's one-request-per-job), so it must reserve one limiter slot per author rather
     * than once per job: the configured number of slot reservations should succeed, and the
     * next one should report a wait instead of being granted.
     */
    public function testPerSecondLimiterThrottlesAfterConfiguredLimit(): void
    {
        $job = new OrcidJob(1, 1, 'test@example.org');
        $method = new ReflectionMethod($job, 'reserveRateLimitSlot');
        $method->setAccessible(true);

        for ($i = 0; $i < self::CONFIGURED_PER_SECOND_LIMIT; $i++) {
            $this->assertNull($method->invoke($job));
        }

        $wait = $method->invoke($job);
        $this->assertIsInt($wait);
        $this->assertGreaterThanOrEqual(0, $wait);
    }

    /**
     * Once the limiter's window has cleared (simulated here by flushing the underlying
     * store, standing in for the passage of time), a slot should be reservable again.
     */
    public function testPerSecondLimiterAllowsRunAgainAfterClearing(): void
    {
        $job = new OrcidJob(1, 1, 'test@example.org');
        $method = new ReflectionMethod($job, 'reserveRateLimitSlot');
        $method->setAccessible(true);

        for ($i = 0; $i < self::CONFIGURED_PER_SECOND_LIMIT; $i++) {
            $this->assertNull($method->invoke($job));
        }

        $this->assertIsInt($method->invoke($job));

        // RateLimiter hashes the limiter name/key internally (md5), so rather than guess
        // that internal key format, just flush the whole (test-isolated) store directly.
        $this->arrayStore->flush();

        $this->assertNull($method->invoke($job));
    }

    /**
     * The job holds itself under a daily cap alongside the per-second one (see its DAILY_LIMIT
     * constant). Driving 24,500+ calls through the limiter to exercise it end-to-end isn't
     * practical in a fast unit test, so this instead verifies the daily limit is configured as
     * its own independent limit: two Limit objects (per-second and per-day) with the expected
     * caps, on explicit, distinct, namespaced cache keys so their counters can't collide.
     */
    public function testDailyLimitIsConfiguredAlongsidePerSecondLimit(): void
    {
        $method = new ReflectionMethod(OrcidJob::class, 'rateLimits');
        $method->setAccessible(true);

        /** @var Limit[] $limits */
        $limits = $method->invoke(new OrcidJob(1, 1, 'test@example.org'));

        $this->assertCount(2, $limits);

        $maxAttemptsByDecay = [];
        foreach ($limits as $limit) {
            $maxAttemptsByDecay[$limit->decaySeconds] = $limit->maxAttempts;
            $this->assertNotEmpty($limit->key);
            $this->assertStringStartsWith('orcid-lookups:', $limit->key);
        }

        $this->assertSame(self::CONFIGURED_PER_SECOND_LIMIT, $maxAttemptsByDecay[1] ?? null);
        $this->assertSame(self::CONFIGURED_DAILY_LIMIT, $maxAttemptsByDecay[60 * 60 * 24] ?? null);

        $this->assertCount(2, array_unique(array_map(fn (Limit $limit) => $limit->key, $limits)));
    }
}
