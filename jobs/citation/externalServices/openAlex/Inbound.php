<?php

/**
 * @file jobs/citation/externalServices/openalex/Inbound.php
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

namespace PKP\jobs\citation\externalServices\openAlex;

use APP\facades\Repo;
use PKP\citation\Citation;
use PKP\citation\job\externalServices\ExternalServicesHelper;

class Inbound
{
    /** @var string The base URL for API requests. */
    public string $url = 'https://api.openalex.org';

    /**
     * Executes this external service
     */
    public function execute(int $citationId): bool
    {
        $citation = Repo::citation()->get($citationId);

        if (empty($citation)) {
            return true;
        }

        if ($citation->getData('isProcessed') ||
            !empty($citation->getData('doi')) ||
            empty($citation->getData('openAlex'))
        ) {
            return true;
        }

        Repo::citation()->edit($this->getWork($citation), []);

        return true;
    }

    /**
     * Get citation (work) from external service
     */
    private function getWork(Citation $citation): Citation
    {
        $response = ExternalServicesHelper::apiRequest(
            'GET',
            $this->url . '/works/doi:' . $citation->getData('doi'),
            []
        );

        if (empty($response)) {
            return $citation;
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

        foreach (Mapping::getAuthor() as $internalKey => $externalKey) {
            if (is_array($externalKey)) {
                $newValue = ExternalServicesHelper::getValueFromArrayPath($externalAuthor, $externalKey);
            } else {
                $newValue = $externalAuthor[$internalKey];
            }

            if (empty($author[$internalKey])) {
                $author[$internalKey] = $newValue;
            }
        }

        $author['displayName'] = trim(str_replace('null', '', $author['displayName']));
        if (empty($author['displayName'])) {
            $author['displayName'] = $externalAuthor['raw_author_name'];
        }

        $authorDisplayNameParts = explode(' ', trim($author['displayName']));
        if (count($authorDisplayNameParts) > 1) {
            $author['familyName'] = array_pop($authorDisplayNameParts);
            $author['givenName'] = implode(' ', $authorDisplayNameParts);
        }

        return $author;
    }
}
