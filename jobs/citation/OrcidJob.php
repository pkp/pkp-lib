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
 * @brief Job for queueing one ORCID lookup per citation author carrying an iD. Makes no request
 * of its own, so it extends BaseJob rather than CitationLookupJob. The queued jobs run in the
 * chain, so a citation's author writes stay ordered and IsProcessedJob still waits for them.
 */

namespace PKP\jobs\citation;

use APP\facades\Repo;
use PKP\citation\enum\CitationProcessingStatus;
use PKP\jobs\BaseJob;

class OrcidJob extends BaseJob
{
    protected int $contextId;
    protected int $citationId;
    protected string $contactEmail;

    public function __construct(int $contextId, int $citationId, string $contactEmail)
    {
        parent::__construct();
        $this->contextId = $contextId;
        $this->citationId = $citationId;
        $this->contactEmail = $contactEmail;
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

        if ($citation->getProcessingStatus() >= CitationProcessingStatus::ORCID->value) {
            return;
        }

        $authorJobs = [];

        foreach ($citation->getData('authors') ?: [] as $author) {
            if (!empty($author['orcid'])) {
                $authorJobs[] = new OrcidAuthorJob($this->contextId, $this->citationId, $author['orcid'], $this->contactEmail);
            }
        }

        if (empty($authorJobs)) {
            return;
        }

        $this->prependToChain($authorJobs);

        $citation->setProcessingStatus(CitationProcessingStatus::ORCID->value);
        Repo::citation()->edit($citation, []);
    }
}
