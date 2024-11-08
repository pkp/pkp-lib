<?php

/**
 * @file classes/components/form/FieldEmailTemplateUnrestricted.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FieldEmailTemplateUnrestricted
 *
 * @ingroup classes_controllers_form
 *
 * @brief A component to indicate if an email template is unrestricted, i.e accessible to all user groups within the associated mailable
 */

namespace PKP\components\forms;

class FieldEmailTemplateUnrestricted extends Field
{
    /** @copydoc Field::$component */
    public $component = 'field-email-template-unrestricted';

    /**
     * @copydoc Field::getConfig()
     */
    public function getConfig()
    {
        $config = parent::getConfig();
        $config['label'] = $this->label;
        return $config;
    }
}
