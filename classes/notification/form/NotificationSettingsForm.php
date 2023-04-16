<?php

/**
 * @file classes/notification/form/NotificationSettingsForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NotificationSettingsForm
 *
 * @ingroup notification_form
 *
 * @brief Form to edit notification settings.
 */

namespace APP\notification\form;

use PKP\notification\form\PKPNotificationSettingsForm;

class NotificationSettingsForm extends PKPNotificationSettingsForm
{
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\notification\form\NotificationSettingsForm', '\NotificationSettingsForm');
}
