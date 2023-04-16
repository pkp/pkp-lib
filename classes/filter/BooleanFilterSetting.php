<?php

/**
 * @file classes/filter/BooleanFilterSetting.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class BooleanFilterSetting
 *
 * @ingroup classes_filter
 *
 * @brief Class that describes a configurable filter setting which must
 *  be either true or false.
 */

namespace PKP\filter;

use PKP\form\validation\FormValidator;

class BooleanFilterSetting extends FilterSetting
{
    /**
     * Constructor
     *
     * @param string $name
     * @param string $displayName
     * @param string $validationMessage
     */
    public function __construct($name, $displayName, $validationMessage)
    {
        parent::__construct($name, $displayName, $validationMessage, FormValidator::FORM_VALIDATOR_OPTIONAL_VALUE);
    }


    //
    // Implement abstract template methods from FilterSetting
    //
    /**
     * @see FilterSetting::getCheck()
     */
    public function &getCheck(&$form)
    {
        $check = new \PKP\form\validation\FormValidatorBoolean($form, $this->getName(), $this->getValidationMessage());
        return $check;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\filter\BooleanFilterSetting', '\BooleanFilterSetting');
}
