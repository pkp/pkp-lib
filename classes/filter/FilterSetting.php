<?php

/**
 * @file classes/filter/FilterSetting.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FilterSetting
 * @ingroup classes_filter
 *
 * @brief Class that describes a configurable filter setting.
 */

namespace PKP\filter;

use PKP\form\validation\FormValidator;

class FilterSetting
{
    /** @var string the (internal) name of the setting */
    public $_name;

    /** @var string the supported transformation */
    public $_displayName;

    /** @var string */
    public $_validationMessage;

    /** @var bool */
    public $_required;

    /** @var bool */
    public $_isLocalized;

    /**
     * Constructor
     *
     * @param string $name
     * @param string $displayName
     * @param string $validationMessage
     * @param string $required
     * @param bool $isLocalized
     */
    public function __construct($name, $displayName, $validationMessage, $required = FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, $isLocalized = false)
    {
        $this->setName($name);
        $this->setDisplayName($displayName);
        $this->setValidationMessage($validationMessage);
        $this->setRequired($required);
        $this->setIsLocalized($isLocalized);
    }

    //
    // Setters and Getters
    //
    /**
     * Set the setting name
     *
     * @param string $name
     */
    public function setName($name)
    {
        $this->_name = $name;
    }

    /**
     * Get the setting name
     *
     * @return string
     */
    public function getName()
    {
        return $this->_name;
    }

    /**
     * Set the display name
     *
     * @param string $displayName
     */
    public function setDisplayName($displayName)
    {
        $this->_displayName = $displayName;
    }

    /**
     * Get the display name
     *
     * @return string
     */
    public function getDisplayName()
    {
        return $this->_displayName;
    }

    /**
     * Set the validation message
     *
     * @param string $validationMessage
     */
    public function setValidationMessage($validationMessage)
    {
        $this->_validationMessage = $validationMessage;
    }

    /**
     * Get the validation message
     *
     * @return string
     */
    public function getValidationMessage()
    {
        return $this->_validationMessage;
    }

    /**
     * Set the required flag
     *
     * @param string $required
     */
    public function setRequired($required)
    {
        $this->_required = $required;
    }

    /**
     * Get the required flag
     *
     * @return string
     */
    public function getRequired()
    {
        return $this->_required;
    }

    /**
     * Set the localization flag
     *
     * @param bool $isLocalized
     */
    public function setIsLocalized($isLocalized)
    {
        $this->_isLocalized = $isLocalized;
    }

    /**
     * Get the localization flag
     *
     * @return bool
     */
    public function getIsLocalized()
    {
        return $this->_isLocalized;
    }


    //
    // Protected Template Methods
    //
    /**
     * Get the form validation check
     *
     * @return FormValidator
     */
    public function &getCheck(&$form)
    {
        // A validator is only required if this setting is mandatory.
        if ($this->getRequired() == FormValidator::FORM_VALIDATOR_OPTIONAL_VALUE) {
            $nullVar = null;
            return $nullVar;
        }

        // Instantiate a simple form validator.
        $check = new \PKP\form\validation\FormValidator($form, $this->getName(), $this->getRequired(), $this->getValidationMessage());
        return $check;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\filter\FilterSetting', '\FilterSetting');
}
