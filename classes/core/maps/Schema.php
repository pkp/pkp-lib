<?php
/**
 * @file classes/core/maps/Schema.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class schema
 *
 * @brief A base class for mapping objects to their schema properties
 */

namespace PKP\core\maps;

use Illuminate\Support\Enumerable;
use PKP\context\Context;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\services\PKPSchemaService;

abstract class Schema extends Base
{
    /**
     * ATTRIBUTE_* constants refer to type of attributes according to the Eloquent Model
     *
     * @var string Primary attribute of the Model derived from the main table
     */
    public const ATTRIBUTE_ORIGIN_MAIN = 'primary';

    /** @var string Model's attribute derived from settings table */
    public const ATTRIBUTE_ORIGIN_SETTINGS = 'setting';

    /** @var string The value for this attribute is composed with Eloquent's Mutators */
    public const ATTRIBUTE_ORIGIN_COMPOSED = 'composed';

    public PKPRequest $request;

    public ?Context $context;

    public PKPSchemaService $schemaService;

    /** The collection of objects being mapped. Null if only one item is being mapped. */
    public Enumerable $collection;

    /** The property names for a summary of this entity according to its schema */
    public array $summaryProps;

    /** The property names of this entity according to its schema */
    public array $props;

    /** The name of the schema for this entity. One of the \PKP\services\PKPSchemaService::SCHEMA_... constants */
    public string $schema;

    public function __construct(PKPRequest $request, ?Context $context, PKPSchemaService $schemaService)
    {
        $this->request = $request;
        $this->context = $context;
        $this->schemaService = $schemaService;
    }

    /**
     * Get the property names of this entity according to its schema
     */
    protected function getProps(): array
    {
        return $this->props ?? $this->schemaService->getFullProps($this->schema);
    }

    /**
     * Get the property names for a summary of this entity according to its schema
     */
    protected function getSummaryProps(): array
    {
        return $this->summaryProps ?? $this->schemaService->getSummaryProps($this->schema);
    }

    /**
     * Get the URL to an object in the REST API
     */
    protected function getApiUrl(string $route, $contextPath = PKPApplication::SITE_CONTEXT_PATH): string
    {
        return $this->request->getDispatcher()->url(
            $this->request,
            PKPApplication::ROUTE_API,
            $contextPath,
            $route,
        );
    }
}
