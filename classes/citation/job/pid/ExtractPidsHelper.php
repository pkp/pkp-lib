<?php

/**
 * @file classes/citation/job/pid/ExtractPidsHelper.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ExtractPidsHelper
 *
 * @ingroup citation
 *
 * @brief Helper class for extracting PIDs from strings.
 */

namespace PKP\citation\job\pid;

use APP\facades\Repo;

class ExtractPidsHelper
{
    public function execute(int $citationId): bool
    {
        $citation = Repo::citation()->get($citationId);

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

        Repo::citation()->edit($citation, []);

        return true;
    }
}
