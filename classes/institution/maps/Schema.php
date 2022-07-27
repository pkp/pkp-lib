<?php
/**
 * @file classes/institution/maps/Schema.inc.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class institution
 *
 * @brief Map institutions to the properties defined in the institution schema
 */

namespace PKP\institution\maps;

use Illuminate\Support\Enumerable;
use PKP\institution\Institution;
use PKP\services\PKPSchemaService;

class Schema extends \PKP\core\maps\Schema
{
    /** @copydoc \PKP\core\maps\Schema::$collection */
    public Enumerable $collection;

    /** @copydoc \PKP\core\maps\Schema::$schema */
    public string $schema = PKPSchemaService::SCHEMA_INSTITUTION;

    /**
     * Map an institution
     *
     * Includes all properties in the institution schema.
     */
    public function map(Institution $item): array
    {
        return $this->mapByProperties($this->getProps(), $item);
    }

    /**
     * Summarize an institution
     *
     * Includes properties with the apiSummary flag in the institution schema.
     */
    public function summarize(Institution $item): array
    {
        return $this->mapByProperties($this->getSummaryProps(), $item);
    }

    /**
     * Map a collection of Institutions
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
     * Summarize a collection of Institutions
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
     * Map schema properties of an Institution to an assoc array
     */
    protected function mapByProperties(array $props, Institution $item): array
    {
        $output = [];
        foreach ($props as $prop) {
            switch ($prop) {
                case '_href':
                    $output[$prop] = $this->getApiUrl('institutions/' . $item->getId());
                    break;
                default:
                    $output[$prop] = $item->getData($prop);
                    break;
            }
        }
        $output = $this->schemaService->addMissingMultilingualValues($this->schema, $output, $this->context->getSupportedFormLocales());
        ksort($output);
        return $this->withExtensions($output, $item);
    }
}
