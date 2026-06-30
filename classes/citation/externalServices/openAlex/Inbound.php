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
use PKP\citation\enum\CitationType;
use PKP\citation\externalServices\ExternalServicesHelper;
use PKP\config\Config;

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
        $apiKey = Config::getVar('features', 'openalex_api_key');
        if ($apiKey) {
            $options = ['query' => ['api_key' => $apiKey]];
        } else {
            $options = ['headers' => ['mailto' => $this->contactEmail]];
        }
        $response = ExternalServicesHelper::apiRequest(
            $this->url . '/works/doi:' . urlencode($citation->getData('doi')),
            $options
        );

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
                    $authorships = ExternalServicesHelper::getValueFromArrayPath($response, $mappedKey);
                    foreach ($authorships as $index => $authorship) {
                        $newValue[] = $this->getAuthor($authorship);
                    }
                    break;
                case 'sourceName':
                    $newValue = ExternalServicesHelper::getValueFromArrayPath($response, $mappedKey);
                    if (empty($newValue)) {
                        $newValue = ExternalServicesHelper::getValueFromArrayPath($response, ['locations', 0, 'raw_source_name']);
                    }
                    break;
                case 'type':
                    $foundType = !empty($response[$mappedKey]) ? $response[$mappedKey] : $response['type'];
                    $newValue = $this->getTypeMapping($foundType);
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
    private function getAuthor(array $authorship): array
    {
        $author = [];

        foreach (Mapping::getAuthor() as $key => $mappedKey) {
            if (is_array($mappedKey)) {
                $author[$key] = ExternalServicesHelper::getValueFromArrayPath($authorship, $mappedKey);
            } else {
                $author[$key] = $authorship[$key];
            }
        }

        $displayName = $authorship['author']['display_name'];
        if (empty($displayName)) {
            $displayName = $authorship['raw_author_name'];
        }


        $firstNameFirst = explode(', ', trim($displayName));
        if (count($firstNameFirst) > 1) {
            $author['givenName'] = array_pop($firstNameFirst);
            $author['familyName'] = trim($firstNameFirst[0]);
            return $author;
        }

        $authorDisplayNameParts = explode(' ', trim($displayName));
        if (count($authorDisplayNameParts) > 1) {
            $author['familyName'] = array_pop($authorDisplayNameParts);
            $author['givenName'] = implode(' ', $authorDisplayNameParts);
        }

        return $author;
    }

    /**
     * Get internal type from OpenAlex type
     */
    private function getTypeMapping(string $openAlexType): string
    {
        return match($openAlexType) {
            'article' => CitationType::JOURNAL_ARTICLE->value,
            default => $openAlexType,
        };
    }
}
