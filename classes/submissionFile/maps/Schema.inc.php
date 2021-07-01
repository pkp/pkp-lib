<?php
/**
 * @file classes/submissionFile/maps/Schema.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class submissionFile
 *
 * @brief Map submissionFiles to the properties defined in the submission file schema
 */

namespace PKP\submissionFile\maps;

use Illuminate\Support\Enumerable;
use PKP\core\maps\Schema as BaseSchema;
use PKP\services\PKPSchemaService;
use PKP\submissionFile\SubmissionFile;

class Schema extends BaseSchema
{
    /** @var Enumerable */
    public $collection;

    /** @var string */
    public $schema = PKPSchemaService::SCHEMA_SUBMISSION_FILE;

    /**
     * Map a submission file
     *
     * Includes all properties in the submission file schema.
     */
    public function map(SubmissionFile $item): array
    {
        return $this->mapByProperties($this->getProps(), $item);
    }

    /**
     * Summarize a submission file
     *
     * Includes properties with the apiSummary flag in the submission file schema.
     */
    public function summarize(SubmissionFile $item): array
    {
        return $this->mapByProperties($this->getSummaryProps(), $item);
    }

    /**
     * Map a collection of submission files
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
     * Summarize a collection of submission files
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
     * Map schema properties of a submission file to an assoc array
     */
    protected function mapByProperties(array $props, SubmissionFile $item): array
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
