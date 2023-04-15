<?php
/**
 * @file classes/doi/maps/Schema.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Schema
 *
 * @brief Map DOI to the properties defined in the Doi schema
 */

namespace PKP\doi\maps;

use Illuminate\Support\Enumerable;
use PKP\doi\Doi;
use PKP\services\PKPSchemaService;

class Schema extends \PKP\core\maps\Schema
{
    /**  */
    public Enumerable $collection;

    /**  */
    public string $schema = PKPSchemaService::SCHEMA_DOI;

    /**
     * Map a DOI
     *
     * Includes all properties in the Doi schema
     */
    public function map(Doi $item): array
    {
        return $this->mapByProperties($this->getProps(), $item);
    }

    /**
     * Summarize a DOI
     *
     * Includes properties with the apiSummary flag in the Doi schema
     */
    public function summarize(Doi $item): array
    {
        return $this->mapByProperties($this->getSummaryProps(), $item);
    }

    /**
     * Map a collection of DOIs
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
     * Summarize a collection of Dois
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
     * Map schema properties of a DOI to an assoc array
     */
    protected function mapByProperties(array $props, Doi $item): array
    {
        $output = [];
        foreach ($props as $prop) {
            switch ($prop) {
                case 'resolvingUrl':
                    $output[$prop] = $item->getResolvingUrl();
                    break;
                default:
                    $output[$prop] = $item->getData($prop);
                    break;
            }
        }
        ksort($output);

        return $this->withExtensions($output, $item);
    }
}
