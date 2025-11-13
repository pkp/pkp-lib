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

use APP\core\Request;
use PKP\author\contributorRole\ContributorRoleIdentifier;
use PKP\context\Context;
use PKP\plugins\Hook;
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
        $primaryLocale = $context->getPrimaryLocale();
        $schema = ContributorRole::getSchemaName();
        $validator = ValidatorFactory::make($props, $this->schemaService->getValidationRules($schema, $locales));

        ValidatorFactory::required(
            $validator,
            $role,
            $this->schemaService->getRequiredProps($schema),
            $this->schemaService->getMultilingualProps($schema),
            $locales,
            $primaryLocale,
        );
        ValidatorFactory::allowedLocales($validator, $this->schemaService->getMultilingualProps($schema), $locales);

        $errors = [];
        if ($validator->fails()) {
            $errors = $this->schemaService->formatValidationErrors($validator->errors());
        }

        Hook::call('ContributorRole::validate', [&$errors, $role, $props, $locales, $primaryLocale]);

        return $errors;
    }
}
