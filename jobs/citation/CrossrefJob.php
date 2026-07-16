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
 * @brief Job for retrieving structured metadata for citations from external services.
 */

namespace PKP\jobs\citation;

use APP\facades\Repo;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\RateLimiter;
use PKP\citation\enum\CitationProcessingStatus;
use PKP\citation\externalServices\crossref\Inbound;
use PKP\job\exceptions\JobException;
use PKP\jobs\BaseJob;

class CrossrefJob extends BaseJob
{
    /** Name of the shared rate limiter throttling all Crossref lookups below the "polite pool" limit of 10 requests/second. */
    protected const RATE_LIMITER_NAME = 'crossref-lookups';

    protected int $contextId;
    protected int $citationId;
    protected string $contactEmail = '';

    public function __construct(int $contextId, int $citationId, string $contactEmail)
    {
        parent::__construct();
        $this->contextId = $contextId;
        $this->citationId = $citationId;
        $this->contactEmail = $contactEmail;
    }

    /**
     * Job middleware; self-throttles below Crossref's rate limit so this job (across all
     * queued citations) is released back to the queue instead of routinely hitting a 429.
     */
    public function middleware(): array
    {
        RateLimiter::for(self::RATE_LIMITER_NAME, fn () => Limit::perSecond(9));

        return [new RateLimited(self::RATE_LIMITER_NAME)];
    }

    /**
     * Handle the queue job execution process
     *
     * @throws JobException
     */
    public function handle(): void
    {
        $citation = Repo::citation()->get($this->citationId);

        if (!$citation) {
            throw new JobException(JobException::INVALID_PAYLOAD);
        }

        if ($citation->getProcessingStatus() >= CitationProcessingStatus::CROSSREF->value) {
            return;
        }

        $service = new Inbound($this->contactEmail);

        $citationChanged = $service->getWork($citation);

        if (empty($citationChanged)) {
            switch ($service->statusCode) {
                case 408:
                case 504:
                    throw new JobException(__('admin.job.failed.connection.externalService', [
                        'statusCode' => $service->statusCode]));
                case 429:
                case 503:
                    // Crossref returns either 429 or 503 when the rate limit is exceeded.
                    $this->release($service->retryAfter !== null ? $service->retryAfter + 3 : 60);
                    return;
                default:
                    return;
            }
        }
        $citationChanged->setProcessingStatus(CitationProcessingStatus::CROSSREF->value);
        Repo::citation()->edit($citationChanged, []);
    }
}
