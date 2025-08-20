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
use PKP\jobs\citation\externalServices\crossref\Inbound as CrossrefInbound;
use PKP\jobs\citation\externalServices\openAlex\Inbound as OpenAlexInbound;
use PKP\jobs\citation\externalServices\orcid\Inbound as OrcidInbound;
use PKP\jobs\citation\pid\ExtractPidsHelper;
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
        if (!$this->citationId) {
            throw new JobException(JobException::INVALID_PAYLOAD);
        }

        $isProcessed = true;

        $extractPidsHelper = new ExtractPidsHelper();
        if(!$extractPidsHelper->execute($this->citationId)) {
            $isProcessed = false;
        }

        $crossref = new CrossrefInbound();
        if(!$crossref->execute($this->citationId)) {
            $isProcessed = false;
        }

        $openAlex = new OpenAlexInbound();
        if(!$openAlex->execute($this->citationId)) {
            $isProcessed = false;
        }

        $orcid = new OrcidInbound();
        if(!$orcid->execute($this->citationId)) {
            $isProcessed = false;
        }

        $citation = Repo::citation()->get($this->citationId);
        $citation->setData('isProcessed', $isProcessed);
        Repo::citation()->edit($citation, []);
    }
}
