<?php

/**
 * @file classes/citation/maps/Schema.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Schema
 *
 * @ingroup citation
 *
 * @brief Map citations to the properties defined in the citation schema
 */

namespace PKP\citation\maps;

use Illuminate\Support\Enumerable;
use PKP\citation\Citation;
use PKP\core\PKPString;
use PKP\services\PKPSchemaService;

class Schema extends \PKP\core\maps\Schema
{
    /** @copydoc \PKP\core\maps\Schema::$collection */
    public Enumerable $collection;

    /** @copydoc \PKP\core\maps\Schema::$schema */
    public string $schema = PKPSchemaService::SCHEMA_CITATION;

    /**
     * Map a citation
     *
     * Includes all properties in the citation schema.
     */
    public function map(Citation $item): array
    {
        return $this->mapByProperties($this->getProps(), $item);
    }

    /**
     * Summarize a citation
     *
     * Includes properties with the apiSummary flag in the citation schema.
     */
    public function summarize(Citation $item): array
    {
        return $this->mapByProperties($this->getSummaryProps(), $item);
    }

    /**
     * Map a collection of Citations
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
     * Summarize a collection of Citations
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
     * Map schema properties of a Citation to an assoc array
     */
    protected function mapByProperties(array $props, Citation $item): array
    {
        $authorModel = $this->getCitationAuthorDataModel();

        $output = [];
        foreach ($props as $prop) {
            switch ($prop) {
                case '_href':
                    $output[$prop] = $this->getApiUrl(
                        'citations/' . $item->getId(),
                        $this->context->getData('urlPath')
                    );
                    break;
                case 'authors':
                    $authors = [];
                    foreach (is_array($item->getData($prop)) ? $item->getData($prop) : [] as $author) {
                        $authors[] = array_merge($authorModel, $author);
                    }
                    $output[$prop] = $authors;
                    break;
                case 'date':
                    $output[$prop] = date('Y-m-d', strtotime($item->getData($prop)));
                    // This is only used to display in the citations list
                    $dateToDisplay = $item->getData($prop);
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateToDisplay)) {
                        $dateToDisplay = date(
                            PKPString::convertStrftimeFormat($this->context->getLocalizedDateFormatLong()),
                            strtotime($dateToDisplay));
                    } else if (preg_match('/^\d{4}$/', $dateToDisplay)) {
                        $dateToDisplay = date(
                            PKPString::convertStrftimeFormat($this->context->getLocalizedDateFormatShort()),
                            strtotime($dateToDisplay));
                    }
                    $output['dateToDisplay'] = $dateToDisplay;
                    break;
                case 'rawCitationWithLinks':
                    $output[$prop] = $item->getRawCitationWithLinks();
                    break;
                default:
                    $output[$prop] = $item->getData($prop);
                    break;
            }
        }
        ksort($output);
        return $this->withExtensions($output, $item);
    }

    /**
     * Get author data model as defined in schemas/citation.json.
     */
    public function getCitationAuthorDataModel(): array
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
