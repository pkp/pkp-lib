<?php

/**
 * @file jobs/citation/OrcidAuthorJob.php
 *
 * Copyright (c) 2025-2026 Simon Fraser University
 * Copyright (c) 2025-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OrcidAuthorJob
 *
 * @ingroup jobs
 *
 * @brief Job for resolving one citation author's name from their ORCID iD.
 */

namespace PKP\jobs\citation;

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use PKP\citation\externalServices\orcid\Inbound;
use PKP\pid\Orcid;

class OrcidAuthorJob extends CitationLookupJob
{
    /** A stale token costs four requests here - token, record, twice - and all must finish before Laravel kills the worker. */
    public int $timeout = 120;

    protected const RATE_LIMITER_NAME = 'orcid-lookups';

    /**
     * ORCID's anonymous tier: 12 requests/second and 25k reads/day, the day counted per IP so the
     * counter is install-wide. A registered client gets more, but getAuthor() falls back to
     * anonymous when it cannot fetch a token, so the lower tier is the only safe assumption.
     *
     * @see https://info.orcid.org/ufaqs/what-are-the-api-limits/
     */
    protected const PER_SECOND_LIMIT = 11;

    protected const DAILY_LIMIT = 24500;

    protected string $orcidId;

    public function __construct(int $contextId, int $citationId, string $orcidId, string $contactEmail, int $serviceRetries = 0)
    {
        parent::__construct();
        $this->contextId = $contextId;
        $this->citationId = $citationId;
        $this->orcidId = $orcidId;
        $this->contactEmail = $contactEmail;
        $this->serviceRetries = $serviceRetries;
    }

    /**
     * Self-throttles below ORCID's limits by releasing the job, not blocking whatever runs it.
     */
    public function middleware(): array
    {
        RateLimiter::for(self::RATE_LIMITER_NAME, fn () => $this->rateLimits());

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

        $authors = $citation->getData('authors') ?: [];
        $index = $this->findAuthorIndex($authors);

        if ($index === null) {
            return;
        }

        $service = new Inbound($this->contactEmail, Application::getContextDAO()->getById($this->contextId));

        $authorChanged = $this->fetchAuthor($service, $authors[$index]);

        if (empty($authorChanged)) {
            switch ($service->statusCode) {
                case 404:
                    $authors[$index]['orcid'] = '';
                    break;
                case 408:
                case 500:
                case 502:
                case 504:
                    $this->retryAfterServiceError($service->statusCode);
                    return;
                case 429:
                case 503:
                    // ORCID returns 503 (rather than 429) when the burst allowance is exceeded.
                    $this->retryAfterRateLimit($service->retryAfter, $service->statusCode);
                    return;
                default:
                    return;
            }
        } else {
            $authors[$index] = $authorChanged;
        }

        $citation->setData('authors', $authors);
        Repo::citation()->edit($citation, []);
    }

    /**
     * @copydoc CitationLookupJob::replicateForRetry()
     */
    protected function replicateForRetry(int $serviceRetries): static
    {
        return new static($this->contextId, $this->citationId, $this->orcidId, $this->contactEmail, $serviceRetries);
    }

    /**
     * Looks up an author, retrying once with a fresh token if the cached one was invalid (401).
     */
    protected function fetchAuthor(Inbound $service, array $author): ?array
    {
        $authorChanged = $service->getAuthor($author);

        if (empty($authorChanged) && $service->statusCode === 401) {
            $service->clearCachedAccessToken();
            $authorChanged = $service->getAuthor($author);
        }

        return $authorChanged;
    }

    /**
     * Locates this job's author in the citation's author list by iD, rather than by position.
     *
     * @return int|null Index of the author this job was queued for, or null if that iD is no longer on the citation.
     */
    protected function findAuthorIndex(array $authors): ?int
    {
        $wanted = Orcid::removePrefix($this->orcidId);

        foreach ($authors as $index => $author) {
            if (!empty($author['orcid']) && Orcid::removePrefix($author['orcid']) === $wanted) {
                return $index;
            }
        }

        return null;
    }

    /**
     * The limits this job holds itself to; keyed apart, or the two counters would be the same one.
     *
     * @return list<Limit>
     */
    protected function rateLimits(): array
    {
        return [
            Limit::perSecond(self::PER_SECOND_LIMIT)->by(self::RATE_LIMITER_NAME . ':per-second'),
            Limit::perDay(self::DAILY_LIMIT)->by(self::RATE_LIMITER_NAME . ':per-day'),
        ];
    }
}
