<?php

/**
 * @file jobs/citation/RetrieveStructured.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RetrieveStructured
 *
 * @ingroup jobs
 *
 * @brief Job for retrieving structured citations from external services.
 */

namespace PKP\jobs\citation;

use APP\facades\Repo;
use PKP\citation\job\externalServices\crossref\Inbound as CrossrefInbound;
use PKP\citation\job\externalServices\openAlex\Inbound as OpenAlexInbound;
use PKP\citation\job\externalServices\orcid\Inbound as OrcidInbound;
use PKP\job\exceptions\JobException;
use PKP\jobs\BaseJob;

class RetrieveStructured extends BaseJob
{
    protected int $publicationId;

    public function __construct(int $publicationId)
    {
        parent::__construct();

        $this->publicationId = $publicationId;
    }

    public function handle(): void
    {
        $publication = Repo::publication()->get($this->publicationId);

        if (!$this->publicationId || !$publication) {
            throw new JobException(JobException::INVALID_PAYLOAD);
        }

        $crossref = new CrossrefInbound($this->publicationId);
        $crossref->execute();

        $openAlex = new OpenAlexInbound($this->publicationId);
        $openAlex->execute();

        $orcid = new OrcidInbound($this->publicationId);
        $orcid->execute();
    }
}
