<?php
/**
 * @file classes/highlight/maps/Schema.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Schema
 *
 * @brief Map highlights to the properties defined in the highlight schema
 */

namespace PKP\highlight\maps;

use Illuminate\Support\Enumerable;
use PKP\highlight\Highlight;
use PKP\services\PKPSchemaService;

class Schema extends \PKP\core\maps\Schema
{
    public Enumerable $collection;

    public string $schema = PKPSchemaService::SCHEMA_HIGHLIGHT;

    /**
     * Map a highlight
     *
     * Includes all properties in the highlight schema.
     */
    public function map(Highlight $item): array
    {
        return $this->mapByProperties($this->getProps(), $item);
    }

    /**
     * Summarize a highlight
     *
     * Includes properties with the apiSummary flag in the highlight schema.
     */
    public function summarize(Highlight $item): array
    {
        return $this->mapByProperties($this->getSummaryProps(), $item);
    }

    /**
     * Map a collection of Highlights
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
     * Summarize a collection of Highlights
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
     * Map schema properties of an Highlight to an assoc array
     */
    protected function mapByProperties(array $props, Highlight $item): array
    {
        $output = [];
        foreach ($props as $prop) {
            switch ($prop) {
                case '_href':
                    $output[$prop] = $this->getApiUrl('highlights/' . $item->getId());
                    break;
                default:
                    $output[$prop] = $item->getData($prop);
                    break;
            }
        }

        $output = $this->schemaService->addMissingMultilingualValues($this->schema, $output, $this->getSupportedFormLocales());

        ksort($output);

        return $this->withExtensions($output, $item);
    }

    protected function getSupportedFormLocales(): array
    {
        return $this->context?->getSupportedFormLocales()
            ?? $this->request->getSite()->getSupportedLocales();
    }
}
