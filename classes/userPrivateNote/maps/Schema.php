<?php
/**
 * @file classes/userPrivateNote/maps/Schema.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class \PKP\userPrivateNote\maps\Schema
 *
 * @brief Map UserPrivateNote to the properties defined in the userPrivateNote schema
 */

namespace PKP\userPrivateNote\maps;

use Illuminate\Support\Enumerable;
use PKP\context\Context;
use PKP\core\PKPRequest;
use PKP\services\PKPSchemaService;
use PKP\userPrivateNote\UserPrivateNote;

class Schema extends \PKP\core\maps\Schema
{
    public Enumerable $collection;

    public string $schema = PKPSchemaService::SCHEMA_USER_PRIVATE_NOTE;

    public function __construct(PKPRequest $request, Context $context, PKPSchemaService $schemaService)
    {
        parent::__construct($request, $context, $schemaService);
    }

    /**
     * Map a user private note
     *
     * Includes all properties in the announcement schema.
     */
    public function map(UserPrivateNote $item): array
    {
        return $this->mapByProperties($this->getProps(), $item);
    }

    /**
     * Summarize a user private note
     *
     * Includes properties with the apiSummary flag in the user private note schema.
     */
    public function summarize(UserPrivateNote $item): array
    {
        return $this->mapByProperties($this->getSummaryProps(), $item);
    }

    /**
     * Map a collection of user private notes
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
     * Summarize a collection of user private notes
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
     * Map schema properties of a user private note to an assoc array
     */
    protected function mapByProperties(array $props, UserPrivateNote $item): array
    {
        $output = [];
        foreach ($props as $prop) {
            $output[$prop] = $item->getData($prop);
        }

        $output = $this->schemaService->addMissingMultilingualValues($this->schema, $output, $this->context->getSupportedSubmissionLocales());

        ksort($output);

        return $this->withExtensions($output, $item);
    }
}
