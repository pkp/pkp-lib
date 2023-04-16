<?php

/**
 * @file classes/form/validation/FormValidatorArrayCustom.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorArrayCustom
 *
 * @ingroup form_validation
 *
 * @brief Form validation check with a custom user function performing the validation check of an array of fields.
 */

namespace PKP\form\validation;

class FormValidatorArrayCustom extends FormValidator
{
    /** @var array Array of fields to check */
    public $_fields;

    /** @var array Array of field names where an error occurred */
    public $_errorFields;

    /** @var bool is the field a multilingual-capable field */
    public $_isLocaleField;

    /** @var callable Custom validation function */
    public $_userFunction;

    /** @var array Additional arguments to pass to $userFunction */
    public $_additionalArguments;

    /** @var bool If true, field is considered valid if user function returns false instead of true */
    public $_complementReturn;

    /**
     * Constructor.
     *
     * @param Form $form the associated form
     * @param string $field the name of the associated field
     * @param string $type the type of check, either "required" or "optional"
     * @param string $message the error message for validation failures (i18n key)
     * @param callable $userFunction the user function to use for validation
     * @param array $additionalArguments optional, a list of additional arguments to pass to $userFunction
     * @param bool $complementReturn optional, complement the value returned by $userFunction
     * @param array $fields all subfields for each item in the array, i.e. name[][foo]. If empty it is assumed that name[] is a data field
     * @param bool $isLocaleField
     */
    public function __construct(&$form, $field, $type, $message, $userFunction, $additionalArguments = [], $complementReturn = false, $fields = [], $isLocaleField = false)
    {
        parent::__construct($form, $field, $type, $message);
        $this->_fields = $fields;
        $this->_errorFields = [];
        $this->_isLocaleField = $isLocaleField;
        $this->_userFunction = $userFunction;
        $this->_additionalArguments = $additionalArguments;
        $this->_complementReturn = $complementReturn;
    }

    //
    // Setters and Getters
    //
    /**
     * Get array of fields where an error occurred.
     *
     * @return array
     */
    public function getErrorFields()
    {
        return $this->_errorFields;
    }

    /**
     * Is it a multilingual-capable field.
     *
     * @return bool
     */
    public function isLocaleField()
    {
        return $this->_isLocaleField;
    }


    //
    // Public methods
    //
    /**
     * @see FormValidator::isValid()
     *
     * @return bool
     */
    public function isValid()
    {
        if ($this->isEmptyAndOptional()) {
            return true;
        }

        $data = $this->getFieldValue();
        if (!is_array($data)) {
            return false;
        }

        $isValid = true;
        foreach ($data as $key => $value) {
            // Bypass check for empty sub-fields if validation type is "optional"
            if ($this->getType() == FormValidator::FORM_VALIDATOR_OPTIONAL_VALUE && ($value == [] || $value == '')) {
                continue;
            }

            if (count($this->_fields) == 0) {
                if ($this->isLocaleField()) {
                    $ret = call_user_func_array($this->_userFunction, array_merge([$value, $key], $this->_additionalArguments));
                } else {
                    $ret = call_user_func_array($this->_userFunction, array_merge([$value], $this->_additionalArguments));
                }
                $ret = $this->_complementReturn ? !$ret : $ret;
                if (!$ret) {
                    $isValid = false;
                    if ($this->isLocaleField()) {
                        $this->_errorFields[$key] = $this->getField() . "[{$key}]";
                    } else {
                        array_push($this->_errorFields, $this->getField() . "[{$key}]");
                    }
                }
            } else {
                // In the two-dimensional case we always expect a value array.
                if (!is_array($value)) {
                    $isValid = false;
                    if ($this->isLocaleField()) {
                        $this->_errorFields[$key] = $this->getField() . "[{$key}]";
                    } else {
                        array_push($this->_errorFields, $this->getField() . "[{$key}]");
                    }
                    continue;
                }

                foreach ($this->_fields as $field) {
                    // Bypass check for empty sub-sub-fields if validation type is "optional"
                    if ($this->getType() == FormValidator::FORM_VALIDATOR_OPTIONAL_VALUE) {
                        if (!isset($value[$field]) || $value[$field] == [] or $value[$field] == '') {
                            continue;
                        }
                    } else {
                        // Make sure that we pass in 'null' to the user function
                        // if the expected field doesn't exist in the value array.
                        if (!array_key_exists($field, $value)) {
                            $value[$field] = null;
                        }
                    }

                    if ($this->isLocaleField()) {
                        $ret = call_user_func_array($this->_userFunction, array_merge([$value[$field], $key], $this->_additionalArguments));
                    } else {
                        $ret = call_user_func_array($this->_userFunction, array_merge([$value[$field]], $this->_additionalArguments));
                    }
                    $ret = $this->_complementReturn ? !$ret : $ret;
                    if (!$ret) {
                        $isValid = false;
                        if ($this->isLocaleField()) {
                            if (!isset($this->_errorFields[$key])) {
                                $this->_errorFields[$key] = [];
                            }
                            array_push($this->_errorFields[$key], $this->getField() . "[{$key}][{$field}]");
                        } else {
                            array_push($this->_errorFields, $this->getField() . "[{$key}][{$field}]");
                        }
                    }
                }
            }
        }
        return $isValid;
    }

    /**
     * Is the field an array.
     *
     * @return bool
     */
    public function isArray()
    {
        return is_array($this->getFieldValue());
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\form\validation\FormValidatorArrayCustom', '\FormValidatorArrayCustom');
}
