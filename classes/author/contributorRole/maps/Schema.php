<?php

/**
 * @file classes/author/contributorRole/maps/Schema.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Schema
 *
 * @brief Map contributor roles to the properties defined in the contributorRole schema
 */

namespace PKP\author\contributorRole\maps;

use Illuminate\Support\Enumerable;
use PKP\author\contributorRole\ContributorRole;
use PKP\core\PKPRequest;
use PKP\services\PKPSchemaService;

class Schema extends \PKP\core\maps\Schema
{
    /** @copydoc \PKP\core\maps\Schema::$collection */
    public Enumerable $collection;

    /** @copydoc \PKP\core\maps\Schema::$schema */
    public string $schema = PKPSchemaService::SCHEMA_CONTRIBUTOR_ROLE;

    public function __construct(PKPRequest $request, \PKP\context\Context $context, PKPSchemaService $schemaService)
    {
        parent::__construct($request, $context, $schemaService);
    }

    /**
     * Map a contributor role
     *
     * Includes all properties in the contributorRole schema.
     */
    public function map(ContributorRole $item): array
    {
        return $this->mapByProperties($this->getProps(), $item);
    }

    /**
     * Summarize an contributor role
     *
     * Includes properties with the apiSummary flag in the contributorRole schema.
     */
    public function summarize(ContributorRole $item): array
    {
        return $this->mapByProperties($this->getSummaryProps(), $item);
    }

    /**
     * Map a collection of ContributorRoles
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
     * Summarize a collection of ContributorRoles
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
     * Map schema properties of an ContributorRole to an assoc array
     */
    protected function mapByProperties(array $props, ContributorRole $item): array
    {
        $output = [];
        foreach ($props as $prop) {
            switch ($prop) {
                case '_href':
                    $output[$prop] = $this->getApiUrl(
                        'contributorRoles/' . $item->getKey(),
                        $this->context->getData('urlPath')
                    );
                    break;
                case 'id':
                    $output[$prop] = $item->getKey();
                    break;
                default:
                    $output[$prop] = $item->getAttribute($prop);
                    break;
            }
        }

        $output = $this->schemaService->addMissingMultilingualValues($this->schema, $output, $this->context->getSupportedFormLocales());
        ksort($output);

        return $this->withExtensions($output, $item);
    }
}
