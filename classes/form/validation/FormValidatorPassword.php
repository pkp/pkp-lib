<?php

/**
 * @file classes/form/validation/FormValidatorPassword.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorPassword
 *
 * @brief Form validation check of the password
 */

namespace PKP\form\validation;

use APP\core\Application;
use PKP\form\validation\FormValidator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class FormValidatorPassword extends FormValidator
{
    /**
     * The name of the password confirmation field
     */
    protected ?string $passwordComparisonFieldName = null;

    /**
     * The minimum password length
     */
    protected int $minPasswordLength;

    /**
     * @see \PKP\form\validation\FormValidator::__construct
     */
    public function __construct(&$form, $field, $type, $message = '', $passwordComparisonFieldName = null)
    {
        $this->passwordComparisonFieldName = $passwordComparisonFieldName;
        $this->minPasswordLength = Application::get()->getRequest()->getSite()->getMinPasswordLength();

        parent::__construct($form, $field, $type, $message);
    }

    /**
     * @see \PKP\form\validation\FormValidator::isValid()
     */
    public function isValid()
    {
        $data = array_merge(
            [$this->_field => $this->getFieldValue()],
            $this->passwordComparisonFieldName
                ? ["{$this->_field}_confirmation" => $this->getForm()->getData($this->passwordComparisonFieldName)]
                : []
        );
        
        $validator = Validator::make(
            data: $data, 
            rules: [
                "{$this->_field}" => $this->getValidationRules()
            ],
            messages: [
                'required' => 'user.profile.form.passwordRequired',
                'confirmed' => 'user.register.form.passwordsDoNotMatch',
                'min' => 'user.register.form.passwordLengthRestriction',
            ]
        );

        if ($validator->fails()) {
            $this->_message = $validator->errors()->first();
            return false;
        }

        return true;
    }

    /**
     * @see \PKP\form\validation\FormValidator::getMessage()
     */
    public function getMessage()
    {
        return __($this->_message, ['length' => $this->minPasswordLength]);
    }

    /**
     * Generate the password validation rules
     */
    protected function getValidationRules(): array
    {
        $rules = $this->getType() === 'required' ? ['required'] : ['sometimes', 'nullable'];

        if ($this->passwordComparisonFieldName) {
            array_push($rules, 'confirmed');
        }

        array_push($rules, "min:{$this->minPasswordLength}");

        array_push(
            $rules, 
            Password::min($this->minPasswordLength)
                ->uncompromised()
        );

        return $rules;
    }
}
