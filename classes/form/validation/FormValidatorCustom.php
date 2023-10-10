<?php

/**
 * @file classes/form/validation/FormValidatorCustom.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorCustom
 *
 * @ingroup form_validation
 *
 * @brief Form validation check with a custom user function performing the validation check.
 */

namespace PKP\form\validation;

class FormValidatorCustom extends FormValidator
{
    /** @var callable Custom validation function */
    public $_userFunction;

    /** @var array Additional arguments to pass to $userFunction */
    public $_additionalArguments;

    /** @var bool If true, field is considered valid if user function returns false instead of true */
    public $_complementReturn;

    /** @var mixed[] Arguments to pass to getMessage() */
    public $_messageArgs = [];

    /** @var array If present, additional arguments to pass to the getMessage translation function
     * The user function is passed the form data as its first argument and $additionalArguments, if set, as the remaining arguments. This function must return a boolean value.
     *
     * @param \PKP\form\Form $form the associated form
     * @param string $field the name of the associated field
     * @param string $type the type of check, either "required" or "optional"
     * @param string $message the error message for validation failures (i18n key)
     * @param callable $userFunction function the user function to use for validation
     * @param array $additionalArguments optional, a list of additional arguments to pass to $userFunction
     * @param bool $complementReturn optional, complement the value returned by $userFunction
     * @param array $messageArgs optional, arguments to pass to getMessage()
     */
    public function __construct(&$form, $field, $type, $message, $userFunction, $additionalArguments = [], $complementReturn = false, $messageArgs = [])
    {
        parent::__construct($form, $field, $type, $message);
        $this->_userFunction = $userFunction;
        $this->_additionalArguments = $additionalArguments;
        $this->_complementReturn = $complementReturn;
        $this->_messageArgs = $messageArgs;
    }


    //
    // Setters and Getters
    //
    /**
     * @see FormValidator::getMessage()
     *
     * @return string
     */
    public function getMessage()
    {
        return __($this->_message, $this->_messageArgs);
    }


    //
    // Public methods
    //
    /**
     * @see FormValidator::isValid()
     * Value is valid if it is empty and optional or validated by user-supplied function.
     *
     * @return bool
     */
    public function isValid()
    {
        if ($this->isEmptyAndOptional()) {
            return true;
        } else {
            $ret = call_user_func_array($this->_userFunction, array_merge([$this->getFieldValue()], $this->_additionalArguments));
            return $this->_complementReturn ? !$ret : $ret;
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\form\validation\FormValidatorCustom', '\FormValidatorCustom');
}
