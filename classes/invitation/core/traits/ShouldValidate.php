<?php

/**
 * @file classes/invitation/core/traits/ShouldValidate.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ShouldValidate
 *
 * @brief Trait that have all the invitations that define validation rules on their payload properties
 */

namespace PKP\invitation\core\traits;

use Illuminate\Support\MessageBag;
use Illuminate\Validation\Validator;
use PKP\invitation\core\Invitation;
use PKP\validation\ValidatorFactory;

trait ShouldValidate
{
    private ?Validator $validator = null;

    private array $globalTraitValidation = [
        Invitation::VALIDATION_RULE_GENERIC => true
    ];

    /**
     * Declares an array of validation rules to be applied to provided data.
     */
    abstract public function getValidationRules(string $context = Invitation::VALIDATION_CONTEXT_DEFAULT): array;

    protected function globalTraitValidationData(array $data, string $context): array {
        $data = array_merge($data, $this->globalTraitValidation);

        return $data;
    }

    /**
     * Optionally allows subclasses to modify or add more keys to the data array.
     * This method can be overridden in classes using this trait.
     */
    protected function prepareValidationData(array $data, string $context = Invitation::VALIDATION_CONTEXT_DEFAULT): array
    {
        return $this->globalTraitValidationData($data, $context);
    }

    /**
     * Checks the validity of the data provided against the provided rules.
     * Returns true if everything is valid. 
     */
    public function validate(array $data = [], string $context = Invitation::VALIDATION_CONTEXT_DEFAULT): bool
    {
        $data = $this->prepareValidationData($data, $context);

        // Check if $data contains any keys not present in $globalTraitValidation
        $otherFields = array_diff(array_keys($data), array_keys($this->globalTraitValidation));

        if (empty($otherFields)) {
            $data = array_merge($data, get_object_vars($this->getSpecificPayload())); // Populate $data with all the properties of the current object
        }

        $rules = $this->getValidationRules($context);

        $this->validator = ValidatorFactory::make(
            $data,
            $rules,
            []
        );

        return $this->isValid();
    }

    public function isValid(): bool
    {
        return !$this->validator->fails();
    }

    public function getErrors(): MessageBag
    {
        return $this->validator->errors();
    }

    public function getValidator(): Validator
    {
        return $this->validator;
    }
}
