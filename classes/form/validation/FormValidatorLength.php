<?php

/**
 * @file classes/form/validation/FormValidatorLength.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorLength
 *
 * @ingroup form_validation
 *
 * @brief Form validation check that checks if a field's length meets certain requirements.
 */

namespace PKP\form\validation;

use Illuminate\Support\Str;

class FormValidatorLength extends FormValidator
{
    /** @var string comparator to use (== | != | < | > | <= | >= ) */
    public $_comparator;

    /** @var int length to compare with */
    public $_length;

    /**
     * Constructor.
     *
     * @param \PKP\form\Form $form the associated form
     * @param string $field the name of the associated field
     * @param string $type the type of check, either "required" or "optional"
     * @param string $message the error message for validation failures (i18n key)
     * @param string $comparator
     * @param int $length
     */
    public function __construct(&$form, $field, $type, $message, $comparator, $length)
    {
        parent::__construct($form, $field, $type, $message);
        $this->_comparator = $comparator;
        $this->_length = $length;
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
        return __($this->_message, ['length' => $this->_length]);
    }


    //
    // Public methods
    //
    /**
     * @see FormValidator::isValid()
     * Value is valid if it is empty and optional or meets the specified length requirements.
     *
     * @return bool
     */
    public function isValid()
    {
        if ($this->isEmptyAndOptional()) {
            return true;
        } else {
            $length = Str::length($this->getFieldValue());
            switch ($this->_comparator) {
                case '==':
                    return $length == $this->_length;
                case '!=':
                    return $length != $this->_length;
                case '<':
                    return $length < $this->_length;
                case '>':
                    return $length > $this->_length;
                case '<=':
                    return $length <= $this->_length;
                case '>=':
                    return $length >= $this->_length;
            }
            return false;
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\form\validation\FormValidatorLength', '\FormValidatorLength');
}
