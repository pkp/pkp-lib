<?php
/**
 * @file classes/category/maps/Schema.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Schema
 *
 * @brief Map categories to the properties defined in the category schema
 */

namespace PKP\category\maps;

use Illuminate\Support\Enumerable;
use PKP\category\Category;
use PKP\services\PKPSchemaService;

class Schema extends \PKP\core\maps\Schema
{
    public string $schema = PKPSchemaService::SCHEMA_CATEGORY;

    /**
     * Map a category
     *
     * Includes all properties in the category schema.
     */
    public function map(Category $category): array
    {
        return $this->mapByProperties($this->getProps(), $category);
    }

    /**
     * Summarize a category
     *
     * Includes properties with the apiSummary flag in the category schema.
     */
    public function summarize(Category $category): array
    {
        return $this->mapByProperties($this->getSummaryProps(), $category);
    }

    /**
     * Map a collection of Categories
     *
     * @see self::map
     */
    public function mapMany(Enumerable $collection): Enumerable
    {
        $this->collection = $collection;
        return $collection->map(function ($category) {
            return $this->map($category);
        });
    }

    /**
     * Summarize a collection of Categories
     *
     * @see self::summarize
     */
    public function summarizeMany(Enumerable $collection): Enumerable
    {
        $this->collection = $collection;
        return $collection->map(function ($category) {
            return $this->summarize($category);
        });
    }

    /**
     * Map schema properties of a Category to an assoc array
     */
    protected function mapByProperties(array $props, Category $category): array
    {
        $output = [];

        foreach ($props as $prop) {
            switch ($prop) {
                default:
                    $output[$prop] = $category->getData($prop);
                    break;
            }
        }

        return $output;
    }
}
