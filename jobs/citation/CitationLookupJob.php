<?php

/**
 * @file jobs/citation/CitationLookupJob.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CitationLookupJob
 *
 * @ingroup jobs
 *
 * @brief Base class for the jobs that enrich a citation from an external metadata service.
 * These run in a Bus::chain, and a lookup that cannot complete deliberately fails the job:
 * without what it fetches, the later stages have nothing to work with.
 */

namespace PKP\jobs\citation;

use Exception;
use Illuminate\Support\Facades\Log;
use PKP\jobs\BaseJob;

abstract class CitationLookupJob extends BaseJob
{
    /** Retries after a 408/5xx/transport failure, escalating 5m to ~10.5h over about a day. */
    public const MAX_SERVICE_RETRIES = 8;

    /** Releases spent before the job fails. Must stay <= 255: jobs.attempts is a TINYINT. */
    public $tries = 254;

    /** Wait when a rate-limited service sends no Retry-After. */
    protected const RATE_LIMIT_FALLBACK_SECONDS = 60;

    /** Random seconds added to a release, so jobs throttled together do not all come back at once. */
    public const RELEASE_JITTER_SECONDS = 3;

    protected int $contextId;
    protected int $citationId;
    protected string $contactEmail = '';

    /** Service-error retries so far, separate from $this->attempts(), which also counts rate-limit releases. */
    protected int $serviceRetries = 0;

    /**
     * Queues a delayed retry ahead of the rest of the chain, or fails the job once the budget is spent.
     */
    protected function retryAfterServiceError(int $statusCode): void
    {
        if ($this->serviceRetries >= static::MAX_SERVICE_RETRIES) {
            $this->fail(new Exception(sprintf(
                'Citation %d: %s abandoned after %d retries (HTTP %d)',
                $this->citationId,
                static::class,
                $this->serviceRetries,
                $statusCode
            )));
            return;
        }

        // Doubling from 5m: momentary failures deserve a cheap early retry. The cap only
        // matters if MAX_SERVICE_RETRIES is raised.
        $this->prependToChain(
            $this->replicateForRetry($this->serviceRetries + 1)
                ->delay(now()->addMinutes(5 * (2 ** min($this->serviceRetries, 7))))
        );
    }

    /**
     * Builds the copy of this job that a service-error retry queues, carrying the retry count forward.
     */
    protected function replicateForRetry(int $serviceRetries): static
    {
        return new static($this->contextId, $this->citationId, $this->contactEmail, $serviceRetries);
    }

    /**
     * Releases the job for the service's Retry-After, or RATE_LIMIT_FALLBACK_SECONDS when it sends none.
     */
    protected function retryAfterRateLimit(?int $retryAfter, int $statusCode): void
    {
        Log::info('Citation metadata lookup rate-limited; retrying', [
            'job' => static::class,
            'citationId' => $this->citationId,
            'statusCode' => $statusCode,
            'retryAfter' => $retryAfter,
            'attempt' => $this->attempts(),
        ]);

        $this->release(
            // +3 to come back after the service's window, not exactly on its boundary.
            ($retryAfter !== null ? $retryAfter + 3 : static::RATE_LIMIT_FALLBACK_SECONDS)
            + random_int(0, self::RELEASE_JITTER_SECONDS)
        );
    }
}
