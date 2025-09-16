<?php

/**
 * @file classes/citation/externalServices/openalex/Inbound.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OpenAlexInbound
 *
 * @ingroup citation
 *
 * @brief Inbound class for OpenAlex
 */

namespace PKP\citation\externalServices\openAlex;

use PKP\citation\Citation;
use PKP\citation\externalServices\ExternalServicesHelper;

class Inbound
{
    /** @var string The base URL for API requests. */
    public string $url = 'https://api.openalex.org';

    /** @var int Status code of external service response. */
    public int $statusCode = 200;

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
            $this->url . '/works/doi:' . urlencode($citation->getData('doi')),
            ['headers' => ['mailto' => $this->contactEmail]]);

        if (is_int($response)) {
            $this->statusCode = $response;
            return null;
        }

        if (empty($response)) {
            return null;
        }

        foreach (Mapping::getWork() as $key => $mappedKey) {
            switch ($key) {
                case 'authors':
                    $newValue = [];
                    $externalAuthors = ExternalServicesHelper::getValueFromArrayPath($response, $mappedKey);
                    foreach ($externalAuthors as $index => $authorShip) {
                        $newValue[] = $this->getAuthor($authorShip);
                    }
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
     * Convert to Author with mappings
     */
    private function getAuthor(array $externalAuthor): array
    {
        $author = [];

        foreach (Mapping::getAuthor() as $key => $mappedKey) {
            if (is_array($mappedKey)) {
                $author[$key] = ExternalServicesHelper::getValueFromArrayPath($externalAuthor, $mappedKey);
            } else {
                $author[$key] = $externalAuthor[$key];
            }
        }

        $displayName = $externalAuthor['author']['display_name'];
        if (empty($displayName)) {
            $displayName = $externalAuthor['raw_author_name'];
        }

        $authorDisplayNameParts = explode(' ', trim($displayName));
        if (count($authorDisplayNameParts) > 1) {
            $author['familyName'] = array_pop($authorDisplayNameParts);
            $author['givenName'] = implode(' ', $authorDisplayNameParts);
        }

        return $author;
    }
}
