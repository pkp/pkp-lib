<?php

/**
 * @file classes/form/validation/FormValidatorEmail.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorEmail
 * @ingroup form_validation
 *
 * @see FormValidator
 *
 * @brief Form validation check for email addresses.
 */

namespace PKP\form\validation;

use PKP\validation\ValidatorEmail;

class FormValidatorEmail extends FormValidator
{
    /**
     * Constructor.
     *
     * @param $form Form the associated form
     * @param $field string the name of the associated field
     * @param $type string the type of check, either "required" or "optional"
     * @param $message string the error message for validation failures (i18n key)
     */
    public function __construct(&$form, $field, $type = 'optional', $message = 'email.invalid')
    {
        $validator = new ValidatorEmail();
        parent::__construct($form, $field, $type, $message, $validator);
        array_push($form->cssValidation[$field], 'email');
    }

    public function getMessage()
    {
        return __($this->_message, ['email' => $this->getFieldValue()]);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\form\validation\FormValidatorEmail', '\FormValidatorEmail');
}
