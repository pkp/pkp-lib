<?php

/**
 * @file jobs/citation/externalServices/orcid/Inbound.php
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

namespace PKP\jobs\citation\externalServices\orcid;

use APP\facades\Repo;
use PKP\citation\job\externalServices\ExternalServicesHelper;
use PKP\citation\job\pid\Orcid;

class Inbound
{
    /** @var string The base URL for API requests. */
    public string $url = 'https://pub.orcid.org/v2.1';

    /**
     * Executes this external service
     */
    public function execute(int $citationId): bool
    {
        $citation = Repo::citation()->get($citationId);

        if (empty($citation)) {
            return true;
        }

        $authors = $citation->getData('authors');
        if (empty($authors)) {
            return true;
        }

        $newAuthors = [];
        foreach ($authors as $author) {
            $newAuthors[] = empty($author['orcid'])
                ? $author
                : $this->getAuthor($author);
        }
        $citation->setData('authors', $newAuthors);

        Repo::citation()->edit($citation, []);

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
