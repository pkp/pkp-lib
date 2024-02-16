<?php

/**
 * @file classes/form/validation/FormValidatorDateCompare.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorDateCompare
 *
 * @ingroup form_validation
 *
 * @see FormValidator
 *
 * @brief Form validation to validation comparison rule for a date field
 */

namespace PKP\form\validation;

use PKP\validation\ValidatorDateComparison;
use Carbon\Carbon;
use DateTimeInterface;

class FormValidatorDateCompare extends FormValidator
{
    /**
     * Constructor.
     *
     * @param \PKP\form\Form            $form           the associated form
     * @param string                    $field          the name of the associated field
     * @param DateTimeInterface|Carbon  $comparingDate  the comparing date
     * @param string                    $comparingRule  the comparing rule e.g. equal/greater/lesser/...
     * @param string                    $type           the type of check, either "required" or "optional"
     * @param string                    $message        the error message for validation failures (i18n key)
     */
    public function __construct(&$form, $field, $comparingDate, $comparingRule,  $type = 'optional', $message = 'validator.date.comparison')
    {
        $validator = new ValidatorDateComparison($comparingDate, $comparingRule);
        parent::__construct($form, $field, $type, $message, $validator);
    }
}
