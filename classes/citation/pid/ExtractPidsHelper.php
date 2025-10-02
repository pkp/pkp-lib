<?php

/**
 * @file classes/citation/pid/ExtractPidsHelper.php
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

namespace PKP\citation\pid;

use PKP\citation\Citation;

class ExtractPidsHelper
{
    public function execute(Citation $citation): Citation
    {
        $raw = $citation->getRawCitation();

        // extract doi
        $doi = Doi::extractFromString($raw);
        if (!empty($doi)) {
            $citation->setData('doi', $doi);
        }

        // remove doi from raw
        $raw = str_replace(Doi::addPrefix($doi), '', Doi::normalize($raw));

        // parse url (after parsing doi)
        $url = Url::extractFromString($raw);
        $handle = Handle::extractFromString($raw);
        $arxiv = Arxiv::extractFromString($raw);

        if (!empty($handle)) {
            $citation->setData('handle', $handle);
        } else if (!empty($arxiv)) {
            $citation->setData('arxiv', $arxiv);
        } else if (!empty($url)) {
            $citation->setData('url', $url);
        }

        // urn
        $urn = Urn::extractFromString($raw);
        if (!empty($urn)) {
            $citation->setData('urn', Urn::extractFromString($raw));
        }

        return $citation;
    }
}
