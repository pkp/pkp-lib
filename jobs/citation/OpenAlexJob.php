<?php

/**
 * @file jobs/citation/OpenAlexJob.php
 *
 * Copyright (c) 2025-2026 Simon Fraser University
 * Copyright (c) 2025-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OpenAlexJob
 *
 * @ingroup jobs
 *
 * @brief Job for retrieving a citation's metadata from OpenAlex by its DOI.
 */

namespace PKP\jobs\citation;

use APP\facades\Repo;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use PKP\citation\enum\CitationProcessingStatus;
use PKP\citation\externalServices\openAlex\Inbound;

class OpenAlexJob extends CitationLookupJob
{
    protected const RATE_LIMITER_NAME = 'openalex-lookups';

    /**
     * OpenAlex's ceiling is 100 requests/second. No daily counterpart: retrieving a single entity
     * by DOI (used here) is free and unmetered.
     *
     * @see https://help.openalex.org/api/authentication/
     * @see https://help.openalex.org/access/example-costs/
     */
    protected const PER_SECOND_LIMIT = 90;

    /** The ceiling is a per-second one, so a 429 needs only a short wait. */
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
     * Job middleware; self-throttles below OpenAlex's rate limit rather than routinely earning a 429.
     */
    public function middleware(): array
    {
        RateLimiter::for(self::RATE_LIMITER_NAME, fn () => Limit::perSecond(self::PER_SECOND_LIMIT));

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

        if ($citation->getProcessingStatus() >= CitationProcessingStatus::OPEN_ALEX->value || !$citation->getData('doi')) {
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
                    // OpenAlex returns 429 when the rate limit is exceeded; 503 handled defensively.
                    $this->retryAfterRateLimit($service->retryAfter, $service->statusCode);
                    return;
                case 404:
                default:
                    return;
            }
        }
        $citationChanged->setProcessingStatus(CitationProcessingStatus::OPEN_ALEX->value);
        Repo::citation()->edit($citationChanged, []);
    }
}
