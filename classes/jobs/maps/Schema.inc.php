<?php
/**
 * @file classes/jobs/maps/Schema.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class jobs
 *
 * @brief Map jobs to the properties defined in the jobs file schema
 */

namespace PKP\jobs\maps;

use Illuminate\Support\Enumerable;
use PKP\core\maps\Schema as BaseSchema;
use PKP\jobs\Job;
use PKP\services\PKPSchemaService;

class Schema extends BaseSchema
{
    /**  */
    public Enumerable $collection;

    /**  */
    public string $schema = PKPSchemaService::SCHEMA_JOBS;

    /**
     * Map a Job
     *
     * Includes all properties in the Job schema.
     */
    public function map(Job $item): array
    {
        return $this->mapByProperties($this->getProps(), $item);
    }

    /**
     * Summarize a Job
     *
     * Includes properties with the apiSummary flag in the Job schema.
     */
    public function summarize(Job $item): array
    {
        return $this->mapByProperties($this->getSummaryProps(), $item);
    }

    /**
     * Map a collection of jobs
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
     * Summarize a collection of jobs
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
     * Map schema properties of a Job to an assoc array
     */
    protected function mapByProperties(array $props, Job $item): array
    {
        $output = [];
        foreach ($props as $prop) {
            $output[$prop] = $item->getData($prop);
        }

        $output = $this->schemaService->addMissingMultilingualValues(
            $this->schema,
            $output,
            $this->context->getSupportedFormLocales()
        );

        ksort($output);

        return $this->withExtensions($output, $item);
    }
}
