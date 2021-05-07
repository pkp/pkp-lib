<?php

/**
 * @file classes/form/validation/FormValidatorORCID.inc.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorORCID
 * @ingroup form_validation
 *
 * @brief Form validation check for ORCID iDs.
 */

namespace PKP\form\validation;

;
use PKP\validation\ValidatorORCID;

class FormValidatorORCID extends FormValidator
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
        $validator = new ValidatorORCID();
        parent::__construct($form, $field, $type, $message, $validator);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\form\validation\FormValidatorORCID', '\FormValidatorORCID');
}
