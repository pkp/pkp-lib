<?php

/**
 * @file classes/components/form/FieldEmailTemplateUserGroupSettings.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FieldEmailTemplateUserGroupSettings
 *
 * @ingroup classes_controllers_form
 *
 * @brief A component managing user groups assignable to an email template
 */

namespace PKP\components\forms;

class FieldEmailTemplateUserGroupSettings extends Field
{
    /** @copydoc Field::$component */
    public $component = 'field-email-template-user-group-settings';

    /**
     * @copydoc Field::getConfig()
     */
    public function getConfig()
    {
        $config = parent::getConfig();

        return $config;
    }
}
