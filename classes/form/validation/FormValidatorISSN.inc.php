<?php

/**
 * @file classes/form/validation/FormValidatorISSN.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorISSN
 * @ingroup form_validation
 *
 * @brief Form validation check for ISSNs.
 */

namespace PKP\form\validation;

use PKP\validation\ValidatorISSN;

class FormValidatorISSN extends FormValidator
{
    /**
     * Constructor.
     *
     * @param $form Form the associated form
     * @param $field string the name of the associated field
     * @param $type string the type of check, either "required" or "optional"
     * @param $message string the error message for validation failures (i18n key)
     */
    public function __construct($form, $field, $type, $message)
    {
        $validator = new ValidatorISSN();
        parent::__construct($form, $field, $type, $message, $validator);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\form\validation\FormValidatorISSN', '\FormValidatorISSN');
}
