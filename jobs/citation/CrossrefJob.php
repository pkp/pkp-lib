<?php

/**
 * @file jobs/citation/CrossrefJob.php
 *
 * Copyright (c) 2025-2026 Simon Fraser University
 * Copyright (c) 2025-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CrossrefJob
 *
 * @ingroup jobs
 *
 * @brief Job for finding a citation at Crossref by searching on its raw text.
 */

namespace PKP\jobs\citation;

use APP\facades\Repo;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use PKP\citation\enum\CitationProcessingStatus;
use PKP\citation\externalServices\crossref\Inbound;

class CrossrefJob extends CitationLookupJob
{
    protected const RATE_LIMITER_NAME = 'crossref-lookups';

    /**
     * Crossref's limits for query endpoints (bibliographic search used here): 3 requests/second
     * and 3 concurrent in the polite pool, 1 and 1 in the public.
     *
     * @see https://www.crossref.org/blog/announcing-changes-to-rest-api-rate-limits/
     */
    protected const POLITE_POOL_LIMIT = 3;

    /** Used when there is no contact email, so no mailto to send. */
    protected const PUBLIC_POOL_LIMIT = 1;

    /** Crossref sends no Retry-After with a 429, and its window is only a second long. */
    protected const RATE_LIMIT_FALLBACK_SECONDS = 5;

    public function __construct(int $contextId, int $citationId, string $contactEmail, int $serviceRetries = 0)
    {
        parent::__construct();
        $this->contextId = $contextId;
        $this->citationId = $citationId;
        $this->contactEmail = $contactEmail;
        $this->serviceRetries = $serviceRetries;
    }

    /**
     * Job middleware; self-throttles below Crossref's rate limit instead of routinely hitting a 429.
     */
    public function middleware(): array
    {
        // The two pools are separate machines with independent limits, so separate counters.
        [$limit, $pool] = $this->contactEmail !== ''
            ? [self::POLITE_POOL_LIMIT, 'polite']
            : [self::PUBLIC_POOL_LIMIT, 'public'];

        RateLimiter::for(
            self::RATE_LIMITER_NAME,
            fn () => Limit::perSecond($limit)->by(self::RATE_LIMITER_NAME . ':' . $pool)
        );

        return [new JitteredRateLimited(self::RATE_LIMITER_NAME)];
    }

    /**
     * Handle the queue job execution process
     */
    public function handle(): void
    {
        $citation = Repo::citation()->get($this->citationId);

        if (!$citation) {
            return;
        }

        if ($citation->getProcessingStatus() >= CitationProcessingStatus::CROSSREF->value || $citation->getData('doi')) {
            return;
        }

        $service = new Inbound($this->contactEmail);

        $citationChanged = $service->getWork($citation);

        if (empty($citationChanged)) {
            switch ($service->statusCode) {
                case 408:
                case 500:
                case 502:
                case 504:
                    $this->retryAfterServiceError($service->statusCode);
                    return;
                case 429:
                case 503:
                    // Crossref returns either 429 or 503 when the rate limit is exceeded.
                    $this->retryAfterRateLimit($service->retryAfter, $service->statusCode);
                    return;
                default:
                    return;
            }
        }
        $citationChanged->setProcessingStatus(CitationProcessingStatus::CROSSREF->value);
        Repo::citation()->edit($citationChanged, []);
    }
}
