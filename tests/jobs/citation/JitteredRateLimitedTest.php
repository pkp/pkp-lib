<?php

/**
 * @file tests/jobs/citation/JitteredRateLimitedTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for the spread JitteredRateLimited adds to a throttled job's release.
 */

namespace PKP\tests\jobs\citation;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\RateLimiter as CacheRateLimiter;
use Illuminate\Cache\Repository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PKP\jobs\citation\JitteredRateLimited;
use PKP\tests\PKPTestCase;
use ReflectionMethod;

#[RunTestsInSeparateProcesses]
#[CoversClass(JitteredRateLimited::class)]
class JitteredRateLimitedTest extends PKPTestCase
{
    /** Matches JitteredRateLimited::MAX_RELEASE_JITTER_SECONDS. */
    protected const CONFIGURED_MAX_JITTER = 600;

    /** Match JitteredRateLimited::MIN_/MAX_SHORT_WINDOW_RELEASE_SECONDS. */
    protected const CONFIGURED_MIN_SHORT_RELEASE = 30;
    protected const CONFIGURED_MAX_SHORT_RELEASE = 120;

    /** Draws per assertion: the delay is randomised, so one sample proves nothing about its range. */
    protected const SAMPLES = 50;

    /** A second either side of the window, in case the clock ticks between arranging and reading it. */
    protected const CLOCK_TOLERANCE = 1;

    protected Repository $cache;

    protected function setUp(): void
    {
        parent::setUp();

        // RateLimited's constructor resolves the limiter from the container, so bind it first.
        $this->cache = new Repository(new ArrayStore());
        app()->instance(CacheRateLimiter::class, new CacheRateLimiter($this->cache));
    }

    /**
     * Laravel derives the delay from a ':timer' entry holding when the exceeded window closes.
     *
     * @return int[] One delay per sample.
     */
    private function delaysForWindowEndingIn(int $seconds): array
    {
        $key = 'test-limiter';
        $this->cache->put($key . ':timer', time() + $seconds, $seconds + 60);

        $middleware = new JitteredRateLimited('citation-lookups');
        $method = new ReflectionMethod($middleware, 'getTimeUntilNextRetry');
        $method->setAccessible(true);

        $delays = [];
        for ($i = 0; $i < self::SAMPLES; $i++) {
            $delays[] = $method->invoke($middleware, $key);
        }

        return $delays;
    }

    /** A per-second window is replaced outright: coming back in seconds would spend $tries on pickups. */
    public function testShortWindowIsHeldBackForMinutes(): void
    {
        $delays = $this->delaysForWindowEndingIn(1);

        $this->assertGreaterThanOrEqual(self::CONFIGURED_MIN_SHORT_RELEASE, min($delays));
        $this->assertLessThanOrEqual(self::CONFIGURED_MAX_SHORT_RELEASE, max($delays));

        // Spread across the range rather than pinned to one value.
        $this->assertGreaterThan(min($delays), max($delays));
    }

    /** A daily quota parks every job against one shared timer, so the spread widens with the wait. */
    public function testDailyWindowScalesTheSpreadUpToTheCap(): void
    {
        $window = 60 * 60 * 24;
        $delays = $this->delaysForWindowEndingIn($window);

        $base = $window + 3;
        $this->assertGreaterThanOrEqual($base - self::CLOCK_TOLERANCE, min($delays));
        $this->assertLessThanOrEqual($base + self::CONFIGURED_MAX_JITTER + self::CLOCK_TOLERANCE, max($delays));

        // Well past the flat floor, so the spread is genuinely scaling rather than defaulting.
        $this->assertGreaterThan($base + self::CONFIGURED_MAX_JITTER / 2, max($delays));
    }

    /** Coming back before the window closes would ignore the limit we set out to respect. */
    public function testTheSpreadIsOnlyEverAdded(): void
    {
        $window = 60 * 60;
        $delays = $this->delaysForWindowEndingIn($window);

        $this->assertGreaterThanOrEqual($window - self::CLOCK_TOLERANCE, min($delays));
    }
}
