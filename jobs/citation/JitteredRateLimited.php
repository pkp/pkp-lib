<?php

/**
 * @file jobs/citation/JitteredRateLimited.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class JitteredRateLimited
 *
 * @ingroup jobs
 *
 * @brief Rate limiting middleware that spreads out the releases it hands to throttled jobs.
 */

namespace PKP\jobs\citation;

use Illuminate\Queue\Middleware\RateLimited;

class JitteredRateLimited extends RateLimited
{
    /** Seconds to spread a throttled release over, at most. */
    protected const MAX_RELEASE_JITTER_SECONDS = 600;

    /** Bounds for a release against a per-second window. */
    protected const MIN_SHORT_WINDOW_RELEASE_SECONDS = 30;
    protected const MAX_SHORT_WINDOW_RELEASE_SECONDS = 120;

    /**
     * @copydoc RateLimited::getTimeUntilNextRetry()
     *
     * A short delay hands the job straight back, and every pickup counts against $tries (max 255).
     * A long one is kept but spread: jobs share the window's timer and would otherwise return together.
     */
    protected function getTimeUntilNextRetry($key): int
    {
        $delay = parent::getTimeUntilNextRetry($key);

        if ($delay < self::MIN_SHORT_WINDOW_RELEASE_SECONDS) {
            return random_int(self::MIN_SHORT_WINDOW_RELEASE_SECONDS, self::MAX_SHORT_WINDOW_RELEASE_SECONDS);
        }

        return $delay + random_int(0, min((int) ($delay * 0.1), self::MAX_RELEASE_JITTER_SECONDS));
    }
}
