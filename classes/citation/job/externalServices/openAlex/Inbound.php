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
            if (empty($citation->getData('doi')) || !empty($citation->getData('openAlex'))) {
                continue;
            }

            Repo::citation()->edit($this->getWork($citation), []);
        }

        return true;
    }

    /**
     * Get citation (work) from external service
     */
    public function getWork(Citation $citation): Citation
    {
        $response = ExternalServicesHelper::apiRequest(
            'GET', $this->url .
            '/works/doi:' . $citation->getData('doi'),
            []
        );

        if (empty($response)) {
            return $citation;
        }

        foreach (Mapping::getWork() as $key => $value) {
            switch ($key) {
                case 'authors':
                    $authors = [];
                    foreach (ExternalServicesHelper::getValueFromArrayPath($response, $value) as $index => $author) {
                        $authors[] = $this->getAuthor($author);
                    }
                    $citation->setData('authors', $authors);
                    break;
                default:
                    if (is_array($value)) {
                        $citation->setData($key, ExternalServicesHelper::getValueFromArrayPath($response, $value));
                    } else {
                        $citation->setData($key, $response[$value]);
                    }
                    break;
            }
        }

        return $citation;
    }

    /**
     * Convert to Author with mappings
     */
    private function getAuthor(array $authorShip): array
    {
        $author = [];
        $mapping = Mapping::getAuthor();

        foreach ($mapping as $key => $val) {
            if (is_array($val)) {
                $author[$key] = ExternalServicesHelper::getValueFromArrayPath($authorShip, $val);
            } else {
                $author[$key] = $authorShip[$key];
            }
        }

        $author['displayName'] = trim(str_replace('null', '', $author['displayName']));
        if (empty($author['displayName'])) {
            $author['displayName'] = $authorShip['raw_author_name'];
        }

        $authorDisplayNameParts = explode(' ', trim($author['displayName']));
        if (count($authorDisplayNameParts) > 1) {
            $author['familyName'] = array_pop($authorDisplayNameParts);
            $author['givenName'] = implode(' ', $authorDisplayNameParts);
        }

        return $author;
    }
}
