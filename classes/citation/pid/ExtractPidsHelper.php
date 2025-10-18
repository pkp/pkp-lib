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
        $raw = str_ireplace('http://', 'https://', $citation->getRawCitation());

        // doi
        $doi = Doi::extractFromString($raw);
        if (!empty($doi)) {
            $citation->setData('doi', $doi);
            $raw = str_replace(Doi::addUrlPrefix($doi), '', $raw);
        }

        // arxiv
        $arxiv = Arxiv::extractFromString($raw);
        if (!empty($arxiv)) {
            $citation->setData('arxiv', $arxiv);
            $raw = str_replace(Arxiv::addUrlPrefix($arxiv), '', $raw);
        }

        // handle
        $handle = Handle::extractFromString($raw);
        if (!empty($handle)) {
            $citation->setData('handle', $handle);
            $raw = str_replace(Handle::addUrlPrefix($handle), '', $raw);
        }

        // url
        $url = Url::extractFromString($raw);
        if (!empty($url)) {
            $citation->setData('url', $url);
        }
        $raw = str_replace($url, '', $raw);

        // urn
        $urn = Urn::extractFromString($raw);
        if (!empty($urn)) {
            $citation->setData('urn', Urn::extractFromString($raw));
        }

        return $citation;
    }
}
