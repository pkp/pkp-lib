<?php

/**
 * @file jobs/citation/OrcidJob.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OrcidJob
 *
 * @ingroup jobs
 *
 * @brief Job for retrieving structured metadata for citations from external services.
 */

namespace PKP\jobs\citation;

use APP\facades\Repo;
use PKP\citation\externalServices\orcid\Inbound;
use PKP\job\exceptions\JobException;
use PKP\jobs\BaseJob;

class OrcidJob extends BaseJob
{
    protected int $citationId;
    protected string $contactEmail = '';

    public function __construct(int $citationId, string $contactEmail)
    {
        parent::__construct();
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

        if ($citation->getIsProcessed()) {
            return;
        }

        $authors = $citation->getData('authors');
        if (empty($authors)) {
            return;
        }

        $service = new Inbound($this->contactEmail);

        $authorsChanged = [];

        foreach ($authors as $author) {
            if (empty($author['orcid'])) {
                $authorsChanged[] = $author;
                continue;
            }

            $authorChanged = $service->getAuthor($author);

            if (empty($authorChanged)) {
                switch ($service->statusCode) {
                    case '404':
                        $author['orcid'] = '';
                        break;
                    case '408':
                    case '504':
                        throw new JobException(__('admin.job.failed.connection.externalService', [
                            'statusCode' => $service->statusCode]));
                }
                $authorsChanged[] = $author;
                continue;
            }

            $authorsChanged[] = $authorChanged;
        }

        $citation->setData('authors', $authorsChanged);
        Repo::citation()->edit($citation, []);
    }
}
