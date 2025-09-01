<?php

/**
 * @file classes/citation/job/MetadataLookup.php
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

namespace PKP\citation\job;

use APP\facades\Repo;
use PKP\citation\job\externalServices\crossref\Inbound as CrossrefInbound;
use PKP\citation\job\externalServices\openAlex\Inbound as OpenAlexInbound;
use PKP\citation\job\externalServices\orcid\Inbound as OrcidInbound;
use PKP\citation\job\pid\ExtractPidsHelper;

class MetadataLookupHandler
{
    protected int $citationId;

    public function __construct(int $citationId)
    {
        $this->citationId = $citationId;
        $this->execute();
    }

    public function execute(): void
    {
        $citation = Repo::citation()->get($this->citationId);
        if ($citation->getData('isProcessed')) {
            return;
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
            Repo::citation()->addJobForCitation($this->citationId);
        }
    }
}
