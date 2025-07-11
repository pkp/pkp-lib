<?php

/**
 * @file classes/form/validation/FormValidatorInSet.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorInSet
 *
 * @ingroup form_validation
 *
 * @brief Form validation check that checks if value is within a certain set.
 */

namespace PKP\form\validation;

class FormValidatorInSet extends FormValidator
{
    /** @var array of all values accepted as valid */
    public $_acceptedValues;

    /**
     * Constructor.
     *
     * @param \PKP\form\Form $form the associated form
     * @param string $field the name of the associated field
     * @param string $type the type of check, either "required" or "optional"
     * @param string $message the error message for validation failures (i18n key)
     * @param array $acceptedValues all possible accepted values
     */
    public function __construct(&$form, $field, $type, $message, $acceptedValues)
    {
        parent::__construct($form, $field, $type, $message);
        $this->_acceptedValues = $acceptedValues;
    }


    //
    // Public methods
    //
    /**
     * Value is valid if it is empty and optional or is in the set of accepted values.
     *
     * @see FormValidator::isValid()
     *
     * @return bool
     */
    public function isValid()
    {
        return $this->isEmptyAndOptional() || in_array($this->getFieldValue(), $this->_acceptedValues);
    }
}
