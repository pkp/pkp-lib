<?php

/**
 * @file classes/filter/EmailFilterSetting.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EmailFilterSetting
 *
 * @ingroup classes_filter
 *
 * @brief Class that describes a configurable filter setting which
 *  must be an email.
 */

namespace PKP\filter;

class EmailFilterSetting extends FilterSetting
{
    //
    // Implement abstract template methods from FilterSetting
    //
    /**
     * @see FilterSetting::getCheck()
     */
    public function &getCheck(&$form)
    {
        $check = new \PKP\form\validation\FormValidatorEmail($form, $this->getName(), $this->getRequired(), $this->getValidationMessage());
        return $check;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\filter\EmailFilterSetting', '\EmailFilterSetting');
}
