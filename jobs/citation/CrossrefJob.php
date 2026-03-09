<?php

/**
 * @file jobs/citation/CrossrefJob.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
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
use PKP\citation\enum\CitationProcessingStatus;
use PKP\citation\externalServices\crossref\Inbound;
use PKP\job\exceptions\JobException;
use PKP\jobs\BaseJob;

class CrossrefJob extends BaseJob implements \PKP\queue\ContextAwareJob
{
    protected int $citationId;
    protected string $contactEmail = '';
    protected int $contextId;

    public function __construct(int $citationId, string $contactEmail, int $contextId)
    {
        parent::__construct();
        $this->citationId = $citationId;
        $this->contactEmail = $contactEmail;
        $this->contextId = $contextId;
    }

    /**
     * Get the context ID for this job.
     */
    public function getContextId(): int
    {
        return $this->contextId;
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
                case '408':
                case '504':
                    throw new JobException(__('admin.job.failed.connection.externalService', [
                        'statusCode' => $service->statusCode]));
                default:
                    return;
            }
        }
        $citationChanged->setProcessingStatus(CitationProcessingStatus::CROSSREF->value);
        Repo::citation()->edit($citationChanged, []);
    }
}
