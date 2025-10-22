<?php

/**
 * @file classes/author/contributorRole/Repository.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A repository to manage actions related to contributor roles
 */

namespace PKP\author\contributorRole;

use APP\core\Application;
use APP\core\Request;
use Illuminate\Validation\Rule;
use PKP\author\contributorRole\ContributorRoleIdentifier;
use PKP\context\Context;
use PKP\facades\Locale;
use PKP\services\PKPSchemaService;
use PKP\validation\ValidatorFactory;

class Repository
{
    /** @var string $schemaMap The name of the class to map this entity to its schema */
    public string $schemaMap = maps\Schema::class;

    /**  */
    protected Request $request;

    /** @var PKPSchemaService<Announcement> $schemaService */
    protected PKPSchemaService $schemaService;

    public function __construct(Request $request, PKPSchemaService $schemaService)
    {
        $this->request = $request;
        $this->schemaService = $schemaService;
    }

    /**
     * Get an instance of the map class for mapping
     * announcements to their schema
     */
    public function getSchemaMap(): maps\Schema
    {
        return app('maps')->withExtensions($this->schemaMap);
    }

    public function validate(?ContributorRole $role, array $props, Context $context): array
    {
        $locales = $context->getSupportedFormLocales();
        $identifiers = ContributorRoleIdentifier::getRoles();
        $schema = ContributorRole::getSchemaName();
        // Identifier in enum
        $rules = array_merge_recursive($this->schemaService->getValidationRules($schema, $locales), [
            'identifier' => [
                Rule::in($identifiers),
            ],
        ]);
        $validator = ValidatorFactory::make($props, $rules);

        ValidatorFactory::required(
            $validator,
            $role,
            $this->schemaService->getRequiredProps($schema),
            $this->schemaService->getMultilingualProps($schema),
            $locales,
            Locale::getPrimaryLocale()
        );
        ValidatorFactory::allowedLocales($validator, $this->schemaService->getMultilingualProps(PKPSchemaService::SCHEMA_CONTRIBUTOR_ROLE), $locales);

        return app()->get('schema')->formatValidationErrors($validator->errors());
    }

    public function getByContextIdAndIdentifiers(array $identifiers, int $contextId): array
    {
        return ContributorRole::query()
            ->withContextId($contextId)
            ->withIdentifiers($identifiers)
            ->get()
            ->all();
    }
}
