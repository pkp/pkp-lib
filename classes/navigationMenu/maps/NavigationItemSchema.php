<?php

/**
 * @file classes/navigationMenu/maps/NavigationItemSchema.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NavigationItemSchema
 *
 * @brief Map navigation menu items to the properties defined in the navigationItem schema
 */

namespace PKP\navigationMenu\maps;

use Illuminate\Support\Enumerable;
use PKP\navigationMenu\NavigationMenuItem;
use PKP\services\PKPSchemaService;

class NavigationItemSchema extends \PKP\core\maps\Schema
{
    public Enumerable $collection;

    public string $schema = PKPSchemaService::SCHEMA_NAVIGATION_MENU_ITEM;

    /**
     * Map a NavigationMenuItem
     *
     * Includes all properties in the navigationItem schema.
     */
    public function map(NavigationMenuItem $item): array
    {
        return $this->mapByProperties($this->getProps(), $item);
    }

    /**
     * Summarize a NavigationMenuItem
     *
     * Includes properties with the apiSummary flag in the navigationItem schema.
     */
    public function summarize(NavigationMenuItem $item): array
    {
        return $this->mapByProperties($this->getSummaryProps(), $item);
    }

    /**
     * Map a collection of NavigationMenuItems
     *
     * @see self::map
     */
    public function mapMany(Enumerable $collection): Enumerable
    {
        $this->collection = $collection;
        return $collection->map([$this, 'map']);
    }

    /**
     * Summarize a collection of NavigationMenuItems
     *
     * @see self::summarize
     */
    public function summarizeMany(Enumerable $collection): Enumerable
    {
        $this->collection = $collection;
        return $collection->map([$this, 'summarize']);
    }

    /**
     * Map schema properties of a NavigationMenuItem to an assoc array
     */
    protected function mapByProperties(array $props, NavigationMenuItem $navigationItem): array
    {
        $output = [];

        foreach ($props as $prop) {
            switch ($prop) {
                case 'id':
                    $output[$prop] = $navigationItem->getId();
                    break;
                case 'title':
                    $output[$prop] = $navigationItem->getData('titleLocaleKey');
                    break;
                case 'path':
                    $output[$prop] = $navigationItem->getPath();
                    break;
                case 'type':
                    $output[$prop] = $navigationItem->getType();
                    break;
                case 'sequence':
                    $output[$prop] = 0;
                    break;
                case 'children':
                    $output[$prop] = [];
                    break;
            }
        }

        $locales = $this->context ? $this->context->getSupportedLocales() : [];

        $output = $this->schemaService->addMissingMultilingualValues($this->schema, $output, $locales);

        ksort($output);

        return $this->withExtensions($output, $navigationItem);
    }
}
