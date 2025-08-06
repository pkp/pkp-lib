<?php

/**
 * @file classes/citation/job/externalServices/crossref/Inbound.php
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

namespace PKP\citation\job\externalServices\crossref;

use APP\facades\Repo;
use PKP\citation\Citation;
use PKP\citation\job\externalServices\ExternalServicesHelper;
use PKP\citation\job\pid\Doi;

class Inbound
{
    /** @var int Treshold of score for accepting found item */
    public int $scoreTreshold = 100;

    /** @var string The base URL for API requests. */
    public string $url = 'https://api.crossref.org';

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
            if (!empty($citation->getData('doi')) || empty($citation->getData('rawCitation'))) {
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
            'GET',
            $this->url . '/works/?query.bibliographic=' . $citation->getData('rawCitation'),
            []
        );

        if (empty($response)) {
            return $citation;
        }

        if (!$this->isMatched($citation->getData('rawCitation'), $response)) {
            return $citation;
        }

        foreach (Mapping::getWork() as $key => $value) {
            switch ($key) {
                case 'doi':
                    $citation->setData(
                        $key,
                        Doi::addPrefix(ExternalServicesHelper::getValueFromArrayPath($response, $value))
                    );
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
     * Check if there is a match.
     *   - Is score higher than given threshold
     *   - Is title found in raw citation
     *   - Are family names of authors in raw citation
     */
    private function isMatched(string $rawCitation, array $response): bool
    {
        $score = (float)ExternalServicesHelper::getValueFromArrayPath($response, ['message', 'items', 0, 'score']);
        if (empty($score) ||  $score < $this->scoreTreshold) {
            return false;
        }

        $title = ExternalServicesHelper::getValueFromArrayPath($response, ['message', 'items', 0, 'title', 0]);
        if(empty($title) || !str_contains(strtolower($rawCitation), strtolower($title))) {
            return false;
        }

        $authors = ExternalServicesHelper::getValueFromArrayPath($response, ['message', 'items', 0, 'author']);
        foreach($authors as $author) {
            if(empty($author['family']) || !str_contains(strtolower($rawCitation), strtolower($author['family']))) {
                return false;
            }
        }

        return true;
    }
}
