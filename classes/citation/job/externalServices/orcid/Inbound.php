<?php

/**
 * @file classes/citation/job/externalServices/orcid/Inbound.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Inbound
 *
 * @ingroup citation
 *
 * @brief Inbound class for Orcid
 */

namespace PKP\citation\job\externalServices\orcid;

use PKP\citation\job\externalServices\ExternalServicesHelper;
use PKP\citation\job\pid\Orcid;

class Inbound
{
    /** @var string The base URL for API requests. */
    public string $url = 'https://pub.orcid.org/v2.1';

    /** @var int Status code of external service response. */
    public int $statusCode = 200;


    /**
     * Convert to Author with mappings
     */
    public function getAuthor(array $author): ?array
    {
        $response = ExternalServicesHelper::apiRequest(
            $this->url . '/' . Orcid::removePrefix($author['orcid']));

        if (is_int($response)) {
            $this->statusCode = $response;
            return null;
        }

        if (empty($response)) {
            return null;
        }

        foreach (Mapping::getAuthor() as $key => $mappedKey) {
            if (is_array($mappedKey)) {
                $newValue = ExternalServicesHelper::getValueFromArrayPath($response, $mappedKey);
            } else {
                $newValue = $response[$key];
            }
            $author[$key] = $newValue;
        }

        return $author;
    }
}
