<?php

/**
 * @file classes/navigationMenu/maps/Schema.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Schema
 *
 * @brief Map navigation menus to the properties defined in the navigation schema
 */

namespace PKP\navigationMenu\maps;

use Illuminate\Support\Enumerable;
use PKP\db\DAORegistry;
use PKP\navigationMenu\NavigationMenu;
use PKP\navigationMenu\NavigationMenuItem;
use PKP\navigationMenu\NavigationMenuItemDAO;
use PKP\services\PKPSchemaService;

class Schema extends \PKP\core\maps\Schema
{
    public Enumerable $collection;

    public string $schema = PKPSchemaService::SCHEMA_NAVIGATION_MENU;

    /**
     * Map a NavigationMenu
     *
     * Includes all properties in the navigation schema.
     */
    public function map(NavigationMenu $item, bool $isPublic = false): array
    {
        return $this->mapByProperties($this->getProps($isPublic), $item);
    }

    /**
     * Summarize a NavigationMenu
     *
     * Includes properties with the apiSummary flag in the navigation schema.
     */
    public function summarize(NavigationMenu $item): array
    {
        return $this->mapByProperties($this->getSummaryProps(), $item);
    }

    /**
     * Map a collection of NavigationMenus
     *
     * @see self::map
     */
    public function mapMany(Enumerable $collection): Enumerable
    {
        $this->collection = $collection;
        return $collection->map([$this, 'map']);
    }

    /**
     * Summarize a collection of NavigationMenus
     *
     * @see self::summarize
     */
    public function summarizeMany(Enumerable $collection): Enumerable
    {
        $this->collection = $collection;
        return $collection->map([$this, 'summarize']);
    }

    /**
     * Map schema properties of a NavigationMenu to an assoc array
     */
    protected function mapByProperties(array $props, NavigationMenu $navigationMenu): array
    {
        $output = [];

        foreach ($props as $prop) {
            switch ($prop) {
                case '_href':
                    $output[$prop] = $this->getApiUrl(
                        'navigations/' . $navigationMenu->getId() . '/public'
                    );
                    break;
                case 'id':
                    $output[$prop] = $navigationMenu->getId();
                    break;
                case 'title':
                    $output[$prop] = $navigationMenu->getTitle();
                    break;
                case 'area_name':
                    $output[$prop] = $navigationMenu->getAreaName();
                    break;
                case 'context_id':
                    $output[$prop] = $navigationMenu->getContextId();
                    break;
                case 'items':
                    $navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO'); /** @var NavigationMenuItemDAO $navigationMenuItemDao */
                    $navigationMenuItems = $navigationMenuItemDao->getByMenuId($navigationMenu->getId());
                    $items = [];
                    while ($navigationMenuItem = $navigationMenuItems->next()) {
                        /** @var NavigationMenuItem $navigationMenuItem */
                        $navigationItemSchema = new NavigationItemSchema($this->request, $this->context, $this->schemaService);
                        $items[] = $navigationItemSchema->summarize($navigationMenuItem);
                    }
                    $output[$prop] = $items;
                    break;
            }
        }

        $locales = $this->context ? $this->context->getSupportedLocales() : [];
        $output = $this->schemaService->addMissingMultilingualValues($this->schema, $output, $locales);

        ksort($output);

        return $this->withExtensions($output, $navigationMenu);
    }
}
