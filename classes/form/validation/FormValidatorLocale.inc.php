<?php

/**
 * @file classes/form/validation/FormValidatorLocale.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorLocale
 * @ingroup form_validation
 *
 * @brief Class to represent a form validation check for localized fields.
 */

namespace PKP\form\validation;

use PKP\facades\Locale;

class FormValidatorLocale extends FormValidator
{
    /** @var string Symbolic name of the locale to require */
    public $_requiredLocale;

    /**
     * Constructor.
     *
     * @param Form $form the associated form
     * @param string $field the name of the associated field
     * @param string $type the type of check, either "required" or "optional"
     * @param string $message the error message for validation failures (i18n key)
     * @param Validator $validator the validator used to validate this form field (optional)
     * @param string $requiredLocale The name of the required locale, i.e. en_US
     */
    public function __construct(&$form, $field, $type, $message, $requiredLocale = null, $validator = null)
    {
        parent::__construct($form, $field, $type, $message, $validator);
        if ($requiredLocale === null) {
            $requiredLocale = Locale::getPrimaryLocale();
        }
        $this->_requiredLocale = $requiredLocale;
    }

    //
    // Getters and Setters
    //
    /**
     * Get the error message associated with a failed validation check.
     *
     * @see FormValidator::getMessage()
     *
     * @return string
     */
    public function getMessage()
    {
        $allLocales = Locale::getAllLocales();
        return parent::getMessage() . ' (' . $allLocales[$this->_requiredLocale] . ')';
    }

    //
    // Protected helper methods
    //
    /**
     * @see FormValidator::getFieldValue()
     */
    public function getFieldValue()
    {
        $form = & $this->getForm();
        $data = $form->getData($this->getField());

        $fieldValue = '';
        if (is_array($data) && isset($data[$this->_requiredLocale])) {
            $fieldValue = $data[$this->_requiredLocale];
            if (is_scalar($fieldValue)) {
                $fieldValue = trim((string)$fieldValue);
            }
        }
        return $fieldValue;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\form\validation\FormValidatorLocale', '\FormValidatorLocale');
}
