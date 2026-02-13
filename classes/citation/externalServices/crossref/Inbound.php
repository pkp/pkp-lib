<?php

/**
 * @file classes/citation/externalServices/crossref/Inbound.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CrossrefInbound
 *
 * @ingroup citation
 *
 * @brief Inbound class for Crossref
 */

namespace PKP\citation\externalServices\crossref;

use PKP\citation\Citation;
use PKP\citation\externalServices\ExternalServicesHelper;

class Inbound
{
    /** @var string The base URL for API requests. */
    public string $url = 'https://api.crossref.org';

    /** @var int Status code of external service response. */
    public int $statusCode = 200;

    /** @var int Threshold of score for accepting found item */
    public int $scoreThreshold = 100;

    /** @var string Email address of journal contact. */
    public string $contactEmail = '';

    public function __construct(string $contactEmail)
    {
        $this->contactEmail = $contactEmail;
    }

    /**
     * Get citation (work) from external service
     */
    public function getWork(Citation $citation): ?Citation
    {
        $response = ExternalServicesHelper::apiRequest(
            $this->url . '/works/?query.bibliographic=' . urlencode(strip_tags($citation->getData('rawCitation'))),
            ['headers' => ['mailto' => $this->contactEmail]]
        );

        if (is_int($response)) {
            $this->statusCode = $response;
            return null;
        }

        if (empty($response)) {
            return null;
        }

        if (!$this->isMatched(strip_tags($citation->getData('rawCitation')), $response)) {
            return $citation;
        }

        foreach (Mapping::getWork() as $key => $mappedKey) {
            switch ($key) {
                case 'doi':
                    $newValue = ExternalServicesHelper::getValueFromArrayPath($response, $mappedKey);
                    break;
                default:
                    if (is_array($mappedKey)) {
                        $newValue = ExternalServicesHelper::getValueFromArrayPath($response, $mappedKey);
                    } else {
                        $newValue = $response[$mappedKey];
                    }
                    break;
            }
            $citation->setData($key, $newValue);
        }

        return $citation;
    }

    /**
     * Check if there is a match.
     *   - Is score higher than given threshold
     *   - Is title found in raw citation
     *   - Are family names of authors in raw citation
     */
    private function isMatched(string $rawCitation, array $response): bool
    {
        $score = (float)ExternalServicesHelper::getValueFromArrayPath($response, ['message', 'items', 0, 'score']);
        if (empty($score) || $score < $this->scoreThreshold) {
            return false;
        }

        $title = ExternalServicesHelper::getValueFromArrayPath($response, ['message', 'items', 0, 'title', 0]);
        if (empty($title) || !str_contains(strtolower($rawCitation), strtolower($title))) {
            return false;
        }

        $authors = ExternalServicesHelper::getValueFromArrayPath($response, ['message', 'items', 0, 'author']);
        foreach ($authors as $author) {
            if (empty($author['family']) || !str_contains(strtolower($rawCitation), strtolower($author['family']))) {
                return false;
            }
        }

        return true;
    }
}
