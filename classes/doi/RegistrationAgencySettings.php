<?php

/**
 * @file classes/doi/RegistrationAgencySettings.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RegistrationAgencySettings
 * @ingroup doi
 *
 * @brief Base class for registration agency plugin settings management
 */

namespace PKP\doi;

use APP\core\Services;
use APP\plugins\IDoiRegistrationAgency;
use Illuminate\Validation\Validator;
use PKP\components\forms\Field;
use PKP\context\Context;
use PKP\plugins\Hook;
use PKP\services\interfaces\EntityWriteInterface;
use PKP\services\PKPSchemaService;
use PKP\validation\ValidatorFactory;

abstract class RegistrationAgencySettings
{
    protected IDoiRegistrationAgency $agencyPlugin;

    public function __construct(IDoiRegistrationAgency $agencyPlugin)
    {
        $this->agencyPlugin = $agencyPlugin;
        Hook::add('Schema::get::' . $this::class, [$this, 'addToSchema']);
    }

    public function validate(array $props): array
    {
        /** @var PKPSchemaService $schemaService */
        $schemaService = Services::get('schema');

        $validator = ValidatorFactory::make(
            $props,
            $schemaService->getValidationRules($this::class, []),
        );

        // Check required
        ValidatorFactory::required(
            $validator,
            EntityWriteInterface::VALIDATE_ACTION_EDIT,
            $schemaService->getRequiredProps($this::class),
            $schemaService->getMultilingualProps($this::class),
            [],
            '',
        );

        $this->addValidationChecks($validator, $props);

        if ($validator->fails()) {
            $errors = $schemaService->formatValidationErrors($validator->errors());
        }

        return $errors ?? [];
    }

    abstract public function getSchema(): \stdClass;

    public function addToSchema($hookname, $args): bool
    {
        $schema = &$args[0];
        $schema = $this->getSchema();

        return Hook::CONTINUE;
    }

    /**
     * Gets plugin-specific settings field to inject into DOI registration settings form.
     *
     * @return Field[]
     */
    abstract public function getFields(Context $context): array;

    /**
     * Overwrite to add additional, plugin-specific validation checks
     */
    protected function addValidationChecks(Validator &$validator, array $props): void
    {
    }
}
