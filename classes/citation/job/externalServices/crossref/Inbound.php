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
    /** @var int Threshold of score for accepting found item */
    public int $scoreThreshold = 100;

    /** @var string The base URL for API requests. */
    public string $url = 'https://api.crossref.org';

    private ?Citation $citation;

    /**
     * Executes for a given publication.
     * Return:
     *   - true: doi exists, isProcessed, match found
     *   - false: citation not found
     */
    public function execute(int $citationId): bool
    {
        $this->citation = Repo::citation()->get($citationId);

        if (empty($this->citation)) {
            return false;
        }

        if (!empty($this->citation->getData('isProcessed')) || $this->citation->getData('doi')) {
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
            $this->url . '/works/?query.bibliographic=' . $this->citation->getData('rawCitation'),
            []
        );

        if (empty($response)) {
            return false;
        }

        if (!$this->isMatched($this->citation->getData('rawCitation'), $response)) {
            return true;
        }

        foreach (Mapping::getWork() as $key => $mappedKey) {
            switch ($key) {
                case 'doi':
                    $newValue = Doi::addPrefix(ExternalServicesHelper::getValueFromArrayPath($response, $mappedKey));
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
