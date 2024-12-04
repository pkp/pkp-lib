<?php

/**
 * @file classes/affiliation/maps/Schema.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Schema
 *
 * @brief Map affiliations to the properties defined in the affiliation schema
 */

namespace PKP\affiliation\maps;

use Illuminate\Support\Enumerable;
use PKP\affiliation\Affiliation;
use APP\facades\Repo;
use PKP\services\PKPSchemaService;

class Schema extends \PKP\core\maps\Schema
{
    /** @copydoc \PKP\core\maps\Schema::$collection */
    public Enumerable $collection;

    /** @copydoc \PKP\core\maps\Schema::$schema */
    public string $schema = PKPSchemaService::SCHEMA_AFFILIATION;

    /**
     * Map a affiliation
     *
     * Includes all properties in the affiliation schema.
     */
    public function map(Affiliation $item): array
    {
        return $this->mapByProperties($this->getProps(), $item);
    }

    /**
     * Summarize an affiliation
     *
     * Includes properties with the apiSummary flag in the affiliation schema.
     */
    public function summarize(Affiliation $item): array
    {
        return $this->mapByProperties($this->getSummaryProps(), $item);
    }

    /**
     * Map a collection of Affiliations
     *
     * @see self::map
     */
    public function mapMany(Enumerable $collection): Enumerable
    {
        $this->collection = $collection;
        return $collection->map(function ($item) {
            return $this->map($item);
        });
    }

    /**
     * Summarize a collection of Affiliations
     *
     * @see self::summarize
     */
    public function summarizeMany(Enumerable $collection): Enumerable
    {
        $this->collection = $collection;
        return $collection->map(function ($item) {
            return $this->summarize($item);
        });
    }

    /**
     * Map schema properties of an Affiliation to an assoc array
     */
    protected function mapByProperties(array $props, Affiliation $item): array
    {
        $output = [];
        foreach ($props as $prop) {
            switch ($prop) {
                default:
                    $output[$prop] = $item->getData($prop);
                    break;
            }
        }

        $author = Repo::author()->get($item->getAuthorId());
        $locales = Repo::submission()->get(Repo::publication()->get($author->getPublicationId())->getData('submissionId'))->getPublicationLanguages($this->context->getSupportedSubmissionMetadataLocales());
        $output = $this->schemaService->addMissingMultilingualValues($this->schema, $output, $locales);
        ksort($output);
        return $this->withExtensions($output, $item);
    }
}
