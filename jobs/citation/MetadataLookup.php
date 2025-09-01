<?php

/**
 * @file jobs/citation/MetadataLookup.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class MetadataLookup
 *
 * @ingroup jobs
 *
 * @brief Job for retrieving structured metadata for citations from external services.
 */

namespace PKP\jobs\citation;

use APP\facades\Repo;
use PKP\citation\job\externalServices\crossref\Inbound as CrossrefInbound;
use PKP\citation\job\externalServices\openAlex\Inbound as OpenAlexInbound;
use PKP\citation\job\externalServices\orcid\Inbound as OrcidInbound;
use PKP\citation\job\pid\ExtractPidsHelper;
use PKP\job\exceptions\JobException;
use PKP\jobs\BaseJob;

class MetadataLookup extends BaseJob
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

        $extractPids = new ExtractPidsHelper();
        $extractPidsStatus = $extractPids->execute($this->citationId);

        $crossref = new CrossrefInbound();
        $crossrefStatus = $crossref->execute($this->citationId);

        $openAlex = new OpenAlexInbound();
        $openAlexStatus = $openAlex->execute($this->citationId);

        $orcid = new OrcidInbound();
        $orcidStatus = $orcid->execute($this->citationId);

        if (
            $extractPidsStatus &&
            $crossrefStatus &&
            $openAlexStatus &&
            $orcidStatus
        ) {
            $citation = Repo::citation()->get($this->citationId);
            $citation->setData('isProcessed', true);
            Repo::citation()->edit($citation, []);
        } else {
            dispatch(new MetadataLookup($this->citationId))->delay(now()->addSeconds(5));
        }
    }
}
