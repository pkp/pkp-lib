<?php

/**
 * @file jobs/citation/CitationJob.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CitationJob
 *
 * @ingroup jobs
 *
 * @brief Base class for the jobs in a citation's metadata lookup chain.
 */

namespace PKP\jobs\citation;

use APP\facades\Repo;
use Illuminate\Support\Facades\Log;
use PKP\citation\enum\CitationProcessingStatus;
use PKP\jobs\BaseJob;
use Throwable;

abstract class CitationJob extends BaseJob
{
    protected int $contextId;
    protected int $citationId;

    /**
     * Called by the queue when this job is abandoned. Marks the citation FAILED so it stops
     * appearing as queued or still processing, and logs the failure for debugging.
     */
    public function failed(Throwable $e): void
    {
        $citation = Repo::citation()->get($this->citationId);
        $lastProcessingStatus = null;
        if ($citation) {
            $lastProcessingStatus = $citation->getProcessingStatus();
            $citation->setProcessingStatus(CitationProcessingStatus::FAILED->value);
            Repo::citation()->edit($citation, []);
        }

        Log::error('Citation metadata lookup abandoned', [
            'job' => static::class,
            'contextId' => $this->contextId,
            'citationId' => $this->citationId,
            'publicationId' => $citation?->getData('publicationId'),
            'lastProcessingStatus' => $lastProcessingStatus,
            'attempts' => $this->attempts(),
            'reason' => $e->getMessage(),
        ] + $this->getFailureLogContext());
    }

    /**
     * Additional details for the failure log entry.
     */
    protected function getFailureLogContext(): array
    {
        return [];
    }
}
