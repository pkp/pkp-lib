<?php
/**
 * @file classes/announcement/Repository.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A helper class to handle operations with annoucnements
 */

namespace PKP\announcement;

use APP\core\Application;
use APP\core\Request;
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
     * Validate properties for an announcement
     *
     * Perform validation checks on data used to add or edit an announcement.
     *
     * @param array $props A key/value array with the new data to validate
     * @param array $allowedLocales The context's supported locales
     * @param string $primaryLocale The context's primary locale
     *
     * @return array A key/value array with validation errors. Empty if no errors
     *
     * @hook Announcement::validate [[&$errors, $object, $props, $allowedLocales, $primaryLocale]]
     */
    public function validate(?Announcement $object, array $props, array $allowedLocales, string $primaryLocale): array
    {
        $schema = Announcement::getSchemaName();

        $validator = ValidatorFactory::make(
            $props,
            $this->schemaService->getValidationRules($schema, $allowedLocales),
            [
                'dateExpire.date_format' => __('stats.dateRange.invalidDate'),
            ]
        );

        // Check required fields
        ValidatorFactory::required(
            $validator,
            $object,
            $this->schemaService->getRequiredProps($schema),
            $this->schemaService->getMultilingualProps($schema),
            $allowedLocales,
            $primaryLocale
        );

        // Check for input from disallowed locales
        ValidatorFactory::allowedLocales($validator, $this->schemaService->getMultilingualProps($schema), $allowedLocales);

        $errors = [];

        if ($validator->fails()) {
            $errors = $this->schemaService->formatValidationErrors($validator->errors());
        }

        Hook::call('Announcement::validate', [&$errors, $object, $props, $allowedLocales, $primaryLocale]);

        return $errors;
    }

    /**
     * Get an instance of the map class for mapping
     * announcements to their schema
     */
    public function getSchemaMap(): maps\Schema
    {
        return app('maps')->withExtensions($this->schemaMap);
    }

    /**
     * Get the base URL for announcement file uploads
     */
    public static function getFileUploadBaseUrl(?Context $context = null): string
    {
        return join('/', [
            Application::get()->getRequest()->getPublicFilesUrl($context),
            Announcement::IMAGE_SUBDIRECTORY,
        ]);
    }
}
