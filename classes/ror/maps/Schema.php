<?php
/**
 * @file classes/ror/maps/Schema.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Schema
 *
 * @brief Map rors to the properties defined in the ror schema
 */

namespace PKP\ror\maps;

use Illuminate\Support\Enumerable;
use PKP\ror\Ror;
use PKP\services\PKPSchemaService;

class Schema extends \PKP\core\maps\Schema
{
    /** @copydoc \PKP\core\maps\Schema::$collection */
    public Enumerable $collection;

    /** @copydoc \PKP\core\maps\Schema::$schema */
    public string $schema = PKPSchemaService::SCHEMA_ROR;

    /**
     * Map a ror
     *
     * Includes all properties in the ror schema.
     */
    public function map(Ror $item): array
    {
        return $this->mapByProperties($this->getProps(), $item);
    }

    /**
     * Summarize a ror
     *
     * Includes properties with the apiSummary flag in the ror schema.
     */
    public function summarize(Ror $item): array
    {
        return $this->mapByProperties($this->getSummaryProps(), $item);
    }

    /**
     * Map a collection of Rors
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
     * Summarize a collection of Rors
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
     * Map schema properties of a Ror to an assoc array
     */
    protected function mapByProperties(array $props, Ror $item): array
    {
        $output = [];
        foreach ($props as $prop) {
            switch ($prop) {
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
