<?php
/**
 * @file classes/dataCitation/maps/Schema.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Schema
 *
 * @brief Map Data Citations to the properties defined in the Data Citation schema.
 * 
 */

namespace PKP\dataCitation\maps;

use Illuminate\Support\Enumerable;
use PKP\dataCitation\DataCitation;
use PKP\services\PKPSchemaService;

class Schema extends \PKP\core\maps\Schema
{
     /** @copydoc \PKP\core\maps\Schema::$collection */
    public Enumerable $collection;

    /** @copydoc \PKP\core\maps\Schema::$schema */
    public string $schema = PKPSchemaService::SCHEMA_DATA_CITATION;

    /**
     * Map a Data Citation
     *
     * Includes all properties in the Data Citation schema.
     */
    public function map(DataCitation $item): array
    {
        return $this->mapByProperties($this->getProps(), $item);
    }

    /**
     * Summarize a Data Citation
     *
     * Includes properties with the apiSummary flag in the Data Citation schema.
     */
    public function summarize(DataCitation $item): array
    {
        return $this->mapByProperties($this->getSummaryProps(), $item);
    }

    /**
     * Map a collection of Data Citations
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
     * Summarize a collection of Data Citations
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
     * Map schema properties of a Data Citation to an assoc array
     */
    protected function mapByProperties(array $props, DataCitation $item): array
    {
        $authorModel = $this->getDataCitationAuthorDataModel();
        $output = [];
        foreach ($props as $prop) {
            switch ($prop) {
                case 'authors':
                    $authors = [];
                    foreach (is_array($item->getAttribute($prop)) ? $item->getAttribute($prop) : [] as $author) {
                        $authors[] = array_merge($authorModel, $author);
                    }
                    $output[$prop] = $authors;
                    break;
                default:
                    $output[$prop] = $item->getAttribute($prop);
                    break;
            }
        }
        ksort($output);
        return $this->withExtensions($output, $item);
    }

    /**
     * Get author data model as defined in schemas/dataCitation.json.
     */
    public function getDataCitationAuthorDataModel(): array
    {
        $schemaService = new PKPSchemaService();
        $schema = $schemaService->get($this->schema);
        $authorModel = [];
        foreach (array_keys((array)$schema->properties->authors->items->properties) as $property) {
            $authorModel[$property] = '';
        }
        return $authorModel;
    }

}
