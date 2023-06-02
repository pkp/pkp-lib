<?php
/**
 * @file classes/log/event/maps/Schema.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Schema
 *
 * @brief Map event log entries to the properties defined in the event log schema
 */

namespace PKP\log\event\maps;

use Illuminate\Support\Enumerable;
use PKP\log\event\EventLogEntry;
use PKP\services\PKPSchemaService;

class Schema extends \PKP\core\maps\Schema
{
    public Enumerable $collection;

    public string $schema = PKPSchemaService::SCHEMA_EVENT_LOG;

    /**
     * Map an event log
     *
     * Includes all properties in the event log entry schema.
     */
    public function map(EventLogEntry $item): array
    {
        return $this->mapByProperties($this->getProps(), $item);
    }

    /**
     * Summarize an event log
     *
     * Includes properties with the apiSummary flag in the event log entry schema.
     */
    public function summarize(EventLogEntry $entry): array
    {
        return $this->mapByProperties($this->getSummaryProps(), $entry);
    }

    /**
     * Map a collection of event log entries
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
     * Summarize a collection of event log entries
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
     * Map schema properties of an event log entry to an assoc array
     */
    protected function mapByProperties(array $props, EventLogEntry $entry): array
    {
        $output = [];

        foreach ($props as $prop) {
            switch ($prop) {
                default:
                    $output[$prop] = $entry->getData($prop);
                    break;
            }
        }

        return $output;
    }
}
