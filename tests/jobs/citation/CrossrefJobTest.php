<?php

/**
 * @file tests/jobs/citation/CrossrefJobTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for the CrossrefJob's proactive rate-limiter self-throttling.
 */

namespace PKP\tests\jobs\citation;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\RateLimiter as CacheRateLimiter;
use Illuminate\Cache\Repository;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PKP\jobs\citation\CrossrefJob;
use PKP\tests\PKPTestCase;

#[RunTestsInSeparateProcesses]
#[CoversClass(CrossrefJob::class)]
class CrossrefJobTest extends PKPTestCase
{
    /** Matches the perSecond() value configured in CrossrefJob::middleware(). */
    protected const CONFIGURED_LIMIT = 9;

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
     * The job should self-throttle to Crossref's polite-pool limit: the configured number of
     * calls should run normally, and the next one should be released back to the queue instead
     * of being allowed to execute (i.e. hit the external service).
     */
    public function testRateLimiterThrottlesAfterConfiguredLimit(): void
    {
        $job = new CrossrefJob(1, 1, 'test@example.org');
        $middleware = $job->middleware()[0];

        $fakeJob = Mockery::mock();
        $fakeJob->shouldReceive('release')->once()->with(Mockery::any());

        $ranCount = 0;
        $next = function () use (&$ranCount) {
            $ranCount++;
        };

        // The configured limit of calls should all be allowed through to $next().
        for ($i = 0; $i < self::CONFIGURED_LIMIT; $i++) {
            $middleware->handle($fakeJob, $next);
        }
        $this->assertSame(self::CONFIGURED_LIMIT, $ranCount);

        // The next call should be throttled: release() is called instead of $next().
        $middleware->handle($fakeJob, $next);
        $this->assertSame(self::CONFIGURED_LIMIT, $ranCount);
    }

    /**
     * Once the limiter's window has cleared (simulated here by flushing the underlying
     * store, standing in for the passage of time), the job should be allowed to run again.
     */
    public function testRateLimiterAllowsRunAgainAfterClearing(): void
    {
        $job = new CrossrefJob(1, 1, 'test@example.org');
        $middleware = $job->middleware()[0];

        $fakeJob = Mockery::mock();
        $fakeJob->shouldNotReceive('release');

        $ranCount = 0;
        $next = function () use (&$ranCount) {
            $ranCount++;
        };

        for ($i = 0; $i < self::CONFIGURED_LIMIT; $i++) {
            $middleware->handle($fakeJob, $next);
        }
        $this->assertSame(self::CONFIGURED_LIMIT, $ranCount);

        // RateLimited hashes the limiter name/key internally (md5), so rather than guess
        // that internal key format, just flush the whole (test-isolated) store directly.
        $this->arrayStore->flush();

        $middleware->handle($fakeJob, $next);
        $this->assertSame(self::CONFIGURED_LIMIT + 1, $ranCount);
    }
}
