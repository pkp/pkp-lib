<?php

/**
 * @file classes/citation/job/externalServices/openalex/Inbound.php
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

namespace PKP\citation\job\externalServices\openAlex;

use APP\facades\Repo;
use PKP\citation\Citation;
use PKP\citation\job\externalServices\ExternalServicesHelper;

class Inbound
{
    /** @var string The base URL for API requests. */
    public string $url = 'https://api.openalex.org';

    private ?Citation $citation;

    /**
     * Executes for a given publication.
     * Return:
     *   - true: doi empty, isProcessed, openAlex found
     *   - false: citation not found
     */
    public function execute(int $citationId): bool
    {
        $this->citation = Repo::citation()->get($citationId);

        if (empty($this->citation)) {
            return false;
        }

        if ($this->citation->getData('isProcessed') ||
            empty($this->citation->getData('doi')) ||
            !empty($this->citation->getData('openAlex'))
        ) {
            return true;
        }

        if ($this->getWork()) {
            Repo::citation()->edit($this->citation, []);
            return true;
        }

        return false;
    }

    /**
     * Get citation (work) from external service
     */
    private function getWork(): bool
    {
        $response = ExternalServicesHelper::apiRequest(
            'GET',
            $this->url . '/works/doi:' . $this->citation->getData('doi'),
            []
        );

        if (empty($response)) {
            return false;
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
            $this->citation->setData($key, $newValue);
        }

        return true;
    }

    /**
     * Convert to Author with mappings
     */
    private function getAuthor(array $externalAuthor): array
    {
        $author = [];

        foreach (Mapping::getAuthor() as $key => $mappedKey) {
            if (is_array($mappedKey)) {
                $newValue = ExternalServicesHelper::getValueFromArrayPath($externalAuthor, $mappedKey);
            } else {
                $newValue = $externalAuthor[$key];
            }

            if (empty($author[$key])) {
                $author[$key] = $newValue;
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
