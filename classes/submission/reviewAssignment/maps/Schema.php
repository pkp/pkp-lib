<?php
/**
 * @file classes/announcement/maps/Schema.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Schema
 *
 * @brief Map announcements to the properties defined in the announcement schema
 */

namespace PKP\submission\reviewAssignment\maps;

use Illuminate\Support\Enumerable;
use PKP\services\PKPSchemaService;
use PKP\submission\reviewAssignment\ReviewAssignment;

class Schema extends \PKP\core\maps\Schema
{
    public Enumerable $collection;

    public string $schema = PKPSchemaService::SCHEMA_REVIEW_ASSIGNMENT;

    /**
     * Map an announcement
     *
     * Includes all properties in the announcement schema.
     */
    public function map(ReviewAssignment $item): array
    {
        return $this->mapByProperties($this->getProps(), $item);
    }

    /**
     * Summarize an announcement
     *
     * Includes properties with the apiSummary flag in the review assignment schema.
     */
    public function summarize(ReviewAssignment $item): array
    {
        return $this->mapByProperties($this->getSummaryProps(), $item);
    }

    /**
     * Map a collection of Review Assignments
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
     * Summarize a collection of Review Assignments
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
     * Map schema properties of an Announcement to an assoc array
     */
    protected function mapByProperties(array $props, ReviewAssignment $item): array
    {
        $output = [];
        foreach ($props as $prop) {
            switch ($prop) {
                case '_href':
                    $output[$prop] = $this->getApiUrl('_submissions/reviewAssignments/' . $item->getId());
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
