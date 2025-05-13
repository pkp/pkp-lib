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
use PKP\citation\job\externalServices\ExternalServicesHelper;
use PKP\citation\job\pid\Orcid;

class Inbound
{
    /** @var string The base URL for API requests. */
    public string $url = 'https://pub.orcid.org/v2.1';

    protected int $publicationId = 0;

    public function __construct(int $publicationId)
    {
        $this->publicationId = $publicationId;
    }

    /**
     * Executes this external service
     */
    public function execute(): bool
    {
        $citations = Repo::citation()->getByPublicationId($this->publicationId);

        if (empty($citations)) {
            return true;
        }

        foreach ($citations as $citation) {
            $authors = [];
            if (empty($citation->getData('authors'))) {
                continue;
            }

            foreach ($citation->getData('authors') as $author) {
                if (empty($author['orcid'])) {
                    continue;
                }

                $authors[] = $this->getAuthor($author);
            }

            $citation->setData('authors', $authors);

            Repo::citation()->edit($citation, []);
        }

        return true;
    }

    /**
     * Convert to Author with mappings
     */
    public function getAuthor(array $author): array
    {
        $response = ExternalServicesHelper::apiRequest(
            'GET',
            $this->url . '/' . Orcid::removePrefix($author['orcid']),
            []
        );

        if (empty($response)) {
            return $author;
        }

        foreach (Mapping::getAuthor() as $key => $value) {
            if (is_array($value)) {
                $author[$key] = ExternalServicesHelper::getValueFromArrayPath($response, $value);
            } else {
                $author[$key] = $response[$value];
            }

            if (str_contains(strtolower($author[$key]), 'deactivated'))
                $author[$key] = '';
        }
        return $author;
    }
}
