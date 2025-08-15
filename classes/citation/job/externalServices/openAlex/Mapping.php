<?php

/**
 * @file classes/citation/job/externalServices/openalex/Mapping.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Mapping
 *
 * @ingroup citation
 *
 * @brief Mapping of internal data models and external
 *
 * @see https://api.openalex.org/works/doi:https://doi.org/10.3897/rio.4.e31656
 * @see https://api.openalex.org/works/W2900747520
 * @see https://docs.openalex.org/api-entities/works
 * @see https://docs.openalex.org/api-entities/authors
 * @see https://docs.openalex.org/api-entities/sources
 */

namespace PKP\citation\job\externalServices\openAlex;

final class Mapping
{
    /**
     * Works are scholarly documents like journal articles, books, datasets, and theses.
     *
     * @see https://docs.openalex.org/api-entities/works
     */
    public static function getWork(): array
    {
        return [
            'title' => 'title',
            'date' => 'publication_date',
            'type' => 'type_crossref',
            'volume' => ['biblio', 'volume'],
            'issue' => ['biblio', 'issue'],
            'firstPage' => ['biblio', 'first_page'],
            'lastPage' => ['biblio', 'last_page'],
            'sourceName' => ['locations', 0, 'source', 'display_name'],
            'sourceIssn' => ['locations', 0, 'source', 'issn_l'],
            'sourceHost' => ['locations', 0, 'source', 'host_organization_name'],
            'sourceType' => ['locations', 0, 'source', 'type'],
            'authors' => ['authorships'], // see getAuthor()
            'wikidata' => ['ids', 'wikidata'],
            'openAlex' => 'id',
        ];
    }

    /**
     * Authors are people who create works.
     *
     * @see https://docs.openalex.org/api-entities/authors
     */
    public static function getAuthor(): array
    {
        return [
            'displayName' => ['author', 'display_name'],
            'givenName' => ['author', 'display_name'],
            'familyName' => ['author', 'display_name'],
            'orcid' => ['author', 'orcid'],
            'openAlex' => ['author', 'id']
        ];
    }
}
