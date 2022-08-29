<?php
/**
 * @file classes/components/form/context/UserAccessForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserAccessForm
 * @ingroup classes_controllers_form
 *
 * @brief Add OPS-specific fields to the users and roles access settings form.
 */

namespace APP\components\forms\context;

use PKP\components\forms\context\PKPUserAccessForm;
use PKP\components\forms\FieldOptions;

class UserAccessForm extends PKPUserAccessForm
{
    /**
     * @copydoc PKPUserAccessForm::__construct()
     */
    public function __construct($action, $context)
    {
        parent::__construct($action, $context);

        $this->addField(new FieldOptions('restrictPreprintAccess', [
            'label' => __('manager.setup.siteAccess.viewContent'),
            'value' => (bool) $context->getData('restrictPreprintAccess'),
            'options' => [
                ['value' => true, 'label' => __('manager.setup.restrictPreprintAccess')],
            ],
        ]), [FIELD_POSITION_AFTER, 'restrictSiteAccess']);
    }
}
