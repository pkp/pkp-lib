<?php

/**
 * @file classes/form/validation/FormValidatorLocaleUrl.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorLocaleUrl
 * @ingroup form_validation
 *
 * @see FormValidatorLocale
 *
 * @brief Form validation check for URL addresses.
 */

namespace PKP\form\validation;

;
use PKP\validation\ValidatorUrl;

class FormValidatorLocaleUrl extends FormValidatorLocale
{
    /**
     * Constructor.
     *
     * @param Form $form the associated form
     * @param string $field the name of the associated field
     * @param string $type the type of check, either "required" or "optional"
     * @param string $message the error message for validation failures (i18n key)
     * @param string $requiredLocale The symbolic name of the required locale
     */
    public function __construct(&$form, $field, $type, $message, $requiredLocale = null)
    {
        $validator = new ValidatorUrl();
        parent::__construct($form, $field, $type, $message, $requiredLocale, $validator);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\form\validation\FormValidatorLocaleUrl', '\FormValidatorLocaleUrl');
}
