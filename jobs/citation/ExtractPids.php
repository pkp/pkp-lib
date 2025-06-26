<?php

/**
 * @file jobs/citation/ExtractPids.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ExtractPids
 *
 * @ingroup jobs
 *
 * @brief Job for extracting PIDs from citations.
 */

namespace PKP\jobs\citation;

use APP\facades\Repo;
use PKP\citation\Citation;
use PKP\citation\job\pid\Arxiv;
use PKP\citation\job\pid\Doi;
use PKP\citation\job\pid\Handle;
use PKP\citation\job\pid\Url;
use PKP\citation\job\pid\Urn;
use PKP\job\exceptions\JobException;
use PKP\jobs\BaseJob;

class ExtractPids extends BaseJob
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

        $citations = Repo::citation()->getByPublicationId($this->publicationId);

        if (empty($citations)) {
            return;
        }

        foreach ($citations as $citation) {
            Repo::citation()->edit($this->extractPIDs($citation), []);
        }
    }

    /**
     * Extract PIDs
     */
    private function extractPIDs(Citation $citation): Citation
    {
        $rowRaw = $citation->cleanCitationString($citation->getRawCitation());

        // extract doi
        $doi = Doi::extractFromString($rowRaw);
        if (!empty($doi)) {
            $citation->setData('doi', Doi::addPrefix($doi));
        }

        // remove doi from raw
        $rowRaw = str_replace(Doi::addPrefix($doi), '', Doi::normalize($rowRaw));

        // parse url (after parsing doi)
        $url = Url::extractFromString($rowRaw);
        $handle = Handle::extractFromString($rowRaw);
        $arxiv = Arxiv::extractFromString($rowRaw);

        if (!empty($handle)) {
            $citation->setData('handle', $handle);
        } else if (!empty($arxiv)) {
            $citation->setData('arxiv', $arxiv);
        } else if (!empty($url)) {
            $citation->setData('url', $url);
        }

        // urn
        $urn = Urn::extractFromString($rowRaw);
        if (!empty($urn)) {
            $citation->setData('urn', Urn::extractFromString($rowRaw));
        }

        return $citation;
    }
}
