<?php
/**
 * @file classes/funder/maps/Schema.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Schema
 *
 * @brief Map Funder data to the properties defined in the Funder schema.
 */

namespace PKP\funder\maps;

use APP\facades\Repo;
use Illuminate\Support\Enumerable;
use PKP\funder\Funder;
use PKP\services\PKPSchemaService;

class Schema extends \PKP\core\maps\Schema
{
    /** @copydoc \PKP\core\maps\Schema::$collection */
    public Enumerable $collection;

    /** @copydoc \PKP\core\maps\Schema::$schema */
    public string $schema = PKPSchemaService::SCHEMA_FUNDER;

    /**
     * Map a Funder
     *
     * Includes all properties in the Funder schema.
     */
    public function map(Funder $item): array
    {
        return $this->mapByProperties($this->getProps(), $item);
    }

    /**
     * Summarize a Funder
     *
     * Includes properties with the apiSummary flag in the Funder schema.
     */
    public function summarize(Funder $item): array
    {
        return $this->mapByProperties($this->getSummaryProps(), $item);
    }

    /**
     * Map a collection of Funder
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
     * Summarize a collection of Funder
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
     * Map schema properties of a Funder to an assoc array
     */
    protected function mapByProperties(array $props, Funder $item): array
    {
        $grantModel = $this->getGrantDataModel();
        $output = [];

        foreach ($props as $prop) {
            switch ($prop) {
                case 'grants':
                    $grants = [];
                    foreach (is_array($item->getAttribute($prop)) ? $item->getAttribute($prop) : [] as $grant) {
                        $grants[] = array_merge($grantModel, $grant);
                    }
                    $output[$prop] = $grants;
                    break;

                case 'rorObject':
                    $rorObject = null;
                    if ($item->ror) {
                        $rorObject = Repo::ror()
                            ->getCollector()
                            ->filterByRor($item->ror)
                            ->getMany()
                            ->first();
                    }
                    $output[$prop] = $rorObject
                        ? Repo::ror()->getSchemaMap()->summarize($rorObject)
                        : null;
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
     * Get a funder grant data model as defined in schemas/funder.json.
     */
    public function getGrantDataModel(): array
    {
        $schemaService = new PKPSchemaService();
        $schema = $schemaService->get($this->schema);
        $grantModel = [];
        foreach (array_keys((array)$schema->properties->grants->items->properties) as $property) {
            $grantModel[$property] = '';
        }
        return $grantModel;
    }
}