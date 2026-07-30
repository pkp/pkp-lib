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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use PKP\citation\Citation;
use PKP\citation\enum\CitationProcessingStatus;
use PKP\citation\externalServices\orcid\Inbound;
use PKP\job\exceptions\JobException;
use PKP\jobs\BaseJob;

class OrcidJob extends BaseJob
{
    /** Name of the shared rate limiter throttling all ORCID lookups below ORCID's documented 12 requests/second sustained limit. */
    protected const RATE_LIMITER_NAME = 'orcid-lookups';

    protected const PER_SECOND_LIMIT = 11;

    /**
     * Just under ORCID's anonymous-tier 25k reads/day-per-IP quota (assumes a single app
     * server/shared cache); applied uniformly since the tier isn't known at this stage.
     */
    protected const DAILY_LIMIT = 24500;

    /** Rate-limit releases count as attempts too, so retry indefinitely; $maxExceptions still catches real failures. */
    public $tries = 0;

    /** Sized for several sequential per-author ORCID requests, not just one. */
    public int $timeout = 300;

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

        RateLimiter::for(self::RATE_LIMITER_NAME, fn () => [
            Limit::perSecond(self::PER_SECOND_LIMIT),
            Limit::perDay(self::DAILY_LIMIT),
        ]);

        $authorsChanged = [];

        foreach ($authors as $author) {
            if (empty($author['orcid']) || !empty($author['orcidLookedUp'])) {
                $authorsChanged[] = $author;
                continue;
            }

            // One ORCID request per author, unlike Crossref/OpenAlex's one per job, so hit the limiter per author.
            $waitSeconds = $this->reserveRateLimitSlot();
            if ($waitSeconds !== null) {
                $this->releasePartialProgress($citation, $authors, $authorsChanged, $waitSeconds + 3);
                return;
            }

            $authorChanged = $this->fetchAuthor($service, $author);

            if (empty($authorChanged)) {
                switch ($service->statusCode) {
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
                        $this->releasePartialProgress($citation, $authors, $authorsChanged, $service->retryAfter !== null ? $service->retryAfter + 3 : 60);
                        return;
                }
                $author['orcidLookedUp'] = true;
                $authorsChanged[] = $author;
                continue;
            }

            $authorChanged['orcidLookedUp'] = true;
            $authorsChanged[] = $authorChanged;
        }

        $citation->setData('authors', $authorsChanged);
        $citation->setProcessingStatus(CitationProcessingStatus::ORCID->value);
        Repo::citation()->edit($citation, []);
    }

    /** Looks up an author, retrying once with a fresh token if the cached one was invalid (401). */
    protected function fetchAuthor(Inbound $service, array $author): ?array
    {
        $authorChanged = $service->getAuthor($author);

        if (empty($authorChanged) && $service->statusCode === 401) {
            $service->clearCachedAccessToken();
            $authorChanged = $service->getAuthor($author);
        }

        return $authorChanged;
    }

    /** Persists authors resolved so far before releasing, so a mid-loop rate-limit release doesn't discard progress. */
    protected function releasePartialProgress(Citation $citation, array $authors, array $authorsChanged, int $delay): void
    {
        $citation->setData('authors', array_merge($authorsChanged, array_slice($authors, count($authorsChanged))));
        Repo::citation()->edit($citation, []);
        $this->release($delay);
    }

    /** @return int|null Null if a slot was reserved, otherwise the number of seconds to wait before retrying. */
    protected function reserveRateLimitSlot(): ?int
    {
        foreach (Collection::wrap(RateLimiter::limiter(self::RATE_LIMITER_NAME)()) as $limit) {
            if (RateLimiter::tooManyAttempts($limit->key, $limit->maxAttempts)) {
                return RateLimiter::availableIn($limit->key);
            }

            RateLimiter::hit($limit->key, $limit->decaySeconds);
        }

        return null;
    }
}
