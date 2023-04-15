<?php
/**
 * @file classes/section/maps/Schema.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2003-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Schema
 *
 * @brief Map sections to the properties defined in the section schema
 */

namespace PKP\section\maps;

use APP\section\Section;
use Illuminate\Support\Enumerable;
use PKP\services\PKPSchemaService;

class Schema extends \PKP\core\maps\Schema
{
    /** @copydoc \PKP\core\maps\Schema::$collection */
    public Enumerable $collection;

    /** @copydoc \PKP\core\maps\Schema::$schema */
    public string $schema = PKPSchemaService::SCHEMA_SECTION;

    /**
     * Map a section
     *
     * Includes all properties in the section schema.
     */
    public function map(Section $item): array
    {
        return $this->mapByProperties($this->getProps(), $item);
    }

    /**
     * Summarize an section
     *
     * Includes properties with the apiSummary flag in the section schema.
     */
    public function summarize(Section $item): array
    {
        return $this->mapByProperties($this->getSummaryProps(), $item);
    }

    /**
     * Map a collection of Sections
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
     * Summarize a collection of Sections
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
     * Map schema properties of an Section to an assoc array
     */
    protected function mapByProperties(array $props, Section $item): array
    {
        $output = [];
        foreach ($props as $prop) {
            switch ($prop) {
                case '_href':
                    $output[$prop] = $this->getApiUrl('sections/' . $item->getId());
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
