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
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PKP\jobs\citation\OrcidJob;
use PKP\tests\PKPTestCase;

#[RunTestsInSeparateProcesses]
#[CoversClass(OrcidJob::class)]
class OrcidJobTest extends PKPTestCase
{
    protected const RATE_LIMITER_NAME = 'orcid-lookups';

    /** Matches the perSecond() value configured in OrcidJob::middleware(). */
    protected const CONFIGURED_PER_SECOND_LIMIT = 11;

    /** Matches the DAILY_LIMIT constant configured in OrcidJob::middleware(). */
    protected const CONFIGURED_DAILY_LIMIT = 24500;

    protected function setUp(): void
    {
        parent::setUp();

        // Bind an isolated, in-memory cache store for the rate limiter instead of the app's
        // configured (file-based) default, so this test doesn't depend on filesystem locking.
        app()->instance(CacheRateLimiter::class, new CacheRateLimiter(new Repository(new ArrayStore())));
    }

    /**
     * The job should self-throttle to ORCID's documented per-second limit: the configured
     * number of calls should run normally, and the next one should be released instead of
     * being allowed to execute (i.e. hit the external service).
     */
    public function testPerSecondLimiterThrottlesAfterConfiguredLimit(): void
    {
        $job = new OrcidJob(1, 1, 'test@example.org');
        $middleware = $job->middleware()[0];

        $fakeJob = Mockery::mock();
        $fakeJob->shouldReceive('release')->once()->with(Mockery::any());

        $ranCount = 0;
        $next = function () use (&$ranCount) {
            $ranCount++;
        };

        for ($i = 0; $i < self::CONFIGURED_PER_SECOND_LIMIT; $i++) {
            $middleware->handle($fakeJob, $next);
        }
        $this->assertSame(self::CONFIGURED_PER_SECOND_LIMIT, $ranCount);

        $middleware->handle($fakeJob, $next);
        $this->assertSame(self::CONFIGURED_PER_SECOND_LIMIT, $ranCount);
    }

    /**
     * The job also registers a daily cap alongside the per-second one (see OrcidJob::middleware()
     * and its DAILY_LIMIT constant). Actually driving 24,500+ calls through the middleware to
     * exercise it end-to-end isn't practical in a fast unit test, so this instead verifies the
     * daily limit is genuinely configured as its own, independent limit: resolving the named
     * limiter should yield two distinct Limit objects (per-second and per-day), not just the
     * per-second one alone, and not colliding on cache key (both default to an empty Limit::$key,
     * which Laravel's RateLimiter::limiter() automatically disambiguates via fallbackKey() when
     * it detects duplicates in the returned array).
     */
    public function testDailyLimitIsConfiguredAlongsidePerSecondLimit(): void
    {
        $job = new OrcidJob(1, 1, 'test@example.org');
        $job->middleware(); // registers the named limiter

        $resolvedLimits = RateLimiter::limiter(self::RATE_LIMITER_NAME)(null);

        $this->assertCount(2, $resolvedLimits);

        $maxAttemptsByDecay = [];
        foreach ($resolvedLimits as $limit) {
            $maxAttemptsByDecay[$limit->decaySeconds] = $limit->maxAttempts;
        }

        $this->assertSame(self::CONFIGURED_PER_SECOND_LIMIT, $maxAttemptsByDecay[1] ?? null);
        $this->assertSame(self::CONFIGURED_DAILY_LIMIT, $maxAttemptsByDecay[60 * 60 * 24] ?? null);
    }
}
