<?php

/**
 * @file jobs/citation/OpenAlexJob.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OpenAlexJob
 *
 * @ingroup jobs
 *
 * @brief Job for retrieving structured metadata for citations from external services.
 */

namespace PKP\jobs\citation;

use APP\core\Application;
use APP\facades\Repo;
use PKP\citation\job\externalServices\openAlex\Inbound;
use PKP\job\exceptions\JobException;
use PKP\jobs\BaseJob;

class OpenAlexJob extends BaseJob
{
    protected int $citationId;

    public function __construct(int $citationId)
    {
        parent::__construct();

        $this->citationId = $citationId;
    }

    public function handle(): void
    {
        $citation = Repo::citation()->get($this->citationId);

        if (!$citation) {
            throw new JobException(JobException::INVALID_PAYLOAD);
        }

        if ($citation->getIsProcessed()) {
            return;
        }

        $publication = Repo::publication()->get($citation->getData('publicationId'));
        $submission = Repo::submission()->get($publication->getData('submissionId'));
        $context = Application::getContextDAO()->getById($submission->getData('contextId'));
        $contactEmail = $context->getContactEmail();

        $service = new Inbound($contactEmail);

        $citationChanged = $service->getWork($citation);

        if (empty($citationChanged)) {
            switch ($service->statusCode) {
                case '408':
                case '504':
                    throw new JobException(__('admin.job.failed.connection.externalService', [
                        'statusCode' => $service->statusCode]));
                case '404':
                default:
                    return;
            }
        }

        Repo::citation()->edit($citationChanged, []);
    }
}
