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
use Illuminate\Support\Facades\RateLimiter;
use PKP\citation\Citation;
use PKP\citation\enum\CitationProcessingStatus;
use PKP\citation\externalServices\orcid\Inbound;
use PKP\job\exceptions\JobException;
use PKP\jobs\BaseJob;

class OrcidJob extends BaseJob
{
    /** Cache-key prefix for the per-second and per-day limiters that throttle all ORCID lookups below ORCID's documented limits. */
    protected const RATE_LIMIT_KEY_PREFIX = 'orcid-lookups';

    protected const PER_SECOND_LIMIT = 11;

    /**
     * Just under ORCID's anonymous-tier 25k reads/day-per-IP quota (assumes a single app
     * server/shared cache); applied uniformly since the tier isn't known at this stage.
     */
    protected const DAILY_LIMIT = 24500;

    /**
     * Calls a rate-limited external API and self-releases on 429/503, each release spending
     * an attempt — hence far above BaseJob's default. $maxExceptions still bounds real errors.
     */
    public $tries = 500;

    /** Retries here wait on an external service, so pace them wider than BaseJob's default. */
    public int $backoff = 300;

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

        $authorsChanged = [];
        $performedLookup = false;

        foreach ($authors as $author) {
            if (empty($author['orcid']) || !empty($author['orcidLookedUp'])) {
                $authorsChanged[] = $author;
                continue;
            }

            $performedLookup = true;

            // One ORCID request per author, so hit the limiter per author.
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
                    case 500:
                    case 502:
                    case 504:
                        // Service is unwell or unreachable: fail fast and visibly, then bulk-retry once it recovers.
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

        // No ORCIDs to resolve (or all resolved on a prior run): leave the citation untouched;
        // IsProcessedJob still finalizes the chain.
        if (!$performedLookup) {
            return;
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

    /**
     * The ORCID rate limits this job holds itself under. Each has an explicit, namespaced
     * cache key so the per-second and per-day counters stay independent.
     *
     * @return list<Limit>
     */
    protected function rateLimits(): array
    {
        return [
            Limit::perSecond(self::PER_SECOND_LIMIT)->by(self::RATE_LIMIT_KEY_PREFIX . ':per-second'),
            Limit::perDay(self::DAILY_LIMIT)->by(self::RATE_LIMIT_KEY_PREFIX . ':per-day'),
        ];
    }

    /** @return int|null Null if a slot was reserved, otherwise the number of seconds to wait before retrying. */
    protected function reserveRateLimitSlot(): ?int
    {
        foreach ($this->rateLimits() as $limit) {
            if (RateLimiter::tooManyAttempts($limit->key, $limit->maxAttempts)) {
                return RateLimiter::availableIn($limit->key);
            }

            RateLimiter::hit($limit->key, $limit->decaySeconds);
        }

        return null;
    }
}
