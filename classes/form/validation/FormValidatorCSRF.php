<?php

/**
 * @file classes/form/validation/FormValidatorCSRF.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorCSRF
 *
 * @ingroup form_validation
 *
 * @brief Form validation check to make sure the CSRF token is correct.
 */

namespace PKP\form\validation;

use APP\core\Application;

class FormValidatorCSRF extends FormValidator
{
    /**
     * Constructor.
     *
     * @param Form $form
     * @param string $message the locale key to use (optional)
     */
    public function __construct(&$form, $message = 'form.csrfInvalid')
    {
        parent::__construct($form, 'dummy', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, $message);
    }


    //
    // Public methods
    //
    /**
     * Check if the CSRF token is correct.
     * overrides FormValidator::isValid()
     *
     * @return bool
     */
    public function isValid()
    {
        $request = Application::get()->getRequest();
        return $request->checkCSRF();
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\form\validation\FormValidatorCSRF', '\FormValidatorCSRF');
}
