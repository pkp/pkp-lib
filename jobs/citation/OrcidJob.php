<?php

/**
 * @file jobs/citation/OrcidJob.php
 *
 * Copyright (c) 2025-2026 Simon Fraser University
 * Copyright (c) 2025-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OrcidJob
 *
 * @ingroup jobs
 *
 * @brief Job for retrieving structured metadata for citations from external services.
 */

namespace PKP\jobs\citation;

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\RateLimiter;
use PKP\citation\enum\CitationProcessingStatus;
use PKP\citation\externalServices\orcid\Inbound;
use PKP\job\exceptions\JobException;
use PKP\jobs\BaseJob;

class OrcidJob extends BaseJob
{
    /** Name of the shared rate limiter throttling all ORCID lookups below ORCID's documented 12 requests/second sustained limit. */
    protected const RATE_LIMITER_NAME = 'orcid-lookups';

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
     * Job middleware; self-throttles below ORCID's rate limit so this job (across all
     * queued citations) is released back to the queue instead of routinely hitting a 429.
     */
    public function middleware(): array
    {
        RateLimiter::for(self::RATE_LIMITER_NAME, fn () => Limit::perSecond(11));

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

        if ($citation->getProcessingStatus() >= CitationProcessingStatus::ORCID->value) {
            return;
        }

        $authors = $citation->getData('authors');
        if (empty($authors)) {
            return;
        }

        $context = Application::getContextDAO()->getById($this->contextId);
        $service = new Inbound($this->contactEmail, $context);

        $authorsChanged = [];

        foreach ($authors as $author) {
            if (empty($author['orcid'])) {
                $authorsChanged[] = $author;
                continue;
            }

            $authorChanged = $service->getAuthor($author);

            if (empty($authorChanged)) {
                switch ($service->statusCode) {
                    case 401:
                        // Cached access token is invalid; clear it so the next lookup fetches a fresh one.
                        $service->clearCachedAccessToken();
                        break;
                    case 404:
                        $author['orcid'] = '';
                        break;
                    case 408:
                    case 504:
                        throw new JobException(__('admin.job.failed.connection.externalService', [
                            'statusCode' => $service->statusCode]));
                    case 429:
                    case 503:
                        // ORCID returns 503 (rather than 429) specifically when the burst allowance is exceeded.
                        $this->release($service->retryAfter !== null ? $service->retryAfter + 3 : 60);
                        return;
                }
                $authorsChanged[] = $author;
                continue;
            }

            $authorsChanged[] = $authorChanged;
        }

        $citation->setData('authors', $authorsChanged);
        $citation->setProcessingStatus(CitationProcessingStatus::ORCID->value);
        Repo::citation()->edit($citation, []);
    }
}
