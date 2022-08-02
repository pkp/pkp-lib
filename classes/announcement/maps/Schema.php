<?php
/**
 * @file classes/announcement/maps/Schema.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class announcement
 *
 * @brief Map announcements to the properties defined in the announcement schema
 */

namespace PKP\announcement\maps;

use Illuminate\Support\Enumerable;
use PKP\announcement\Announcement;
use PKP\core\PKPApplication;
use PKP\services\PKPSchemaService;

class Schema extends \PKP\core\maps\Schema
{
    public Enumerable $collection;

    public string $schema = PKPSchemaService::SCHEMA_ANNOUNCEMENT;

    /**
     * Map an announcement
     *
     * Includes all properties in the announcement schema.
     */
    public function map(Announcement $item): array
    {
        return $this->mapByProperties($this->getProps(), $item);
    }

    /**
     * Summarize an announcement
     *
     * Includes properties with the apiSummary flag in the announcement schema.
     */
    public function summarize(Announcement $item): array
    {
        return $this->mapByProperties($this->getSummaryProps(), $item);
    }

    /**
     * Map a collection of Announcements
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
     * Summarize a collection of Announcements
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
    protected function mapByProperties(array $props, Announcement $item): array
    {
        $output = [];
        foreach ($props as $prop) {
            switch ($prop) {
                case '_href':
                    $output[$prop] = $this->getApiUrl('announcements/' . $item->getId());
                    break;
                case 'url':
                    $output[$prop] = $this->request->getDispatcher()->url(
                        $this->request,
                        PKPApplication::ROUTE_PAGE,
                        $this->context->getData('urlPath'),
                        'announcement',
                        'view',
                        $item->getId()
                    );
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
