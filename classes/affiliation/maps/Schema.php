<?php

/**
 * @file classes/affiliation/maps/Schema.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Schema
 *
 * @brief Map affiliations to the properties defined in the affiliation schema
 */

namespace PKP\affiliation\maps;

use APP\facades\Repo;
use Illuminate\Support\Enumerable;
use PKP\affiliation\Affiliation;
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
        $author = Repo::author()->get($item->getAuthorId());
        $locales = Repo::submission()->get(Repo::publication()->get($author->getData('publicationId'))->getData('submissionId'))->getPublicationLanguages($this->context->getSupportedSubmissionMetadataLocales());

        $output = [];
        foreach ($props as $prop) {
            switch ($prop) {
                case 'name':
                    // Get all, also the mapped ROR names
                    $output[$prop] = $item->getAffiliationName(null, $locales);
                    break;
                case 'rorObject':
                    if ($rorObject = $item->getRorObject()) {
                        $retVal = Repo::ror()->getSchemaMap()->summarize($rorObject);
                    } else {
                        $retVal = null;
                    }
                    $output[$prop] = $retVal;
                    break;
                default:
                    $output[$prop] = $item->getData($prop);
                    break;
            }
        }

        $output = $this->schemaService->addMissingMultilingualValues($this->schema, $output, $locales);
        ksort($output);
        return $this->withExtensions($output, $item);
    }
}
