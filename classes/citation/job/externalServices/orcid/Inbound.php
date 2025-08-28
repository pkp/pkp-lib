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

use APP\facades\Repo;
use PKP\citation\Citation;
use PKP\citation\job\externalServices\ExternalServicesHelper;
use PKP\citation\job\pid\Orcid;

class Inbound
{
    /** @var string The base URL for API requests. */
    public string $url = 'https://pub.orcid.org/v2.1';

    private ?Citation $citation;

    /**
     * Executes this external service
     */
    public function execute(int $citationId): bool
    {
        $this->citation = Repo::citation()->get($citationId);

        if (empty($this->citation)) {
            return false;
        }

        $authors = $this->citation->getData('authors');

        if (empty($authors)) {
            return true;
        }

        $changedAuthors = [];
        foreach ($authors as $author) {
            $changedAuthors[] = !empty($author['orcid'])
                ? $this->getAuthor($author)
                : $author;
        }
        $this->citation->setData('authors', $changedAuthors);

        Repo::citation()->edit($this->citation, []);

        return true;
    }

    /**
     * Convert to Author with mappings
     */
    private function getAuthor(array $author): array
    {
        $response = ExternalServicesHelper::apiRequest(
            'GET',
            $this->url . '/' . Orcid::removePrefix($author['orcid']),
            []
        );

        if (empty($response)) {
            return $author;
        }

        foreach (Mapping::getAuthor() as $key => $mappedKey) {
            if (is_array($mappedKey)) {
                $newValue = ExternalServicesHelper::getValueFromArrayPath($response, $mappedKey);
            } else {
                $newValue = $response[$key];
            }

            if (empty($author[$key])) {
                $author[$key] = $newValue;
            }

            if (str_contains(strtolower($author[$key]), 'deactivated'))
                $author[$key] = '';
        }

        return $author;
    }
}
