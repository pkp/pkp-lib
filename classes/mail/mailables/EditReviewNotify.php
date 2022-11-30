<?php

/**
 * @file classes/mail/mailables/EditReviewNotify.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditReviewNotify
 *
 * @brief An automatic email sent to the reviewer when the details of their review assignment have been changed
 */

namespace PKP\mail\mailables;

use APP\notification\Notification;
use APP\submission\Submission;
use PKP\context\Context;
use PKP\mail\Mailable;
use PKP\mail\traits\Configurable;
use PKP\mail\traits\Recipient;
use PKP\mail\traits\Notification as NotificationTrait;
use PKP\mail\traits\Sender;
use PKP\security\Role;
use PKP\submission\reviewAssignment\ReviewAssignment;

class EditReviewNotify extends Mailable
{
    use Configurable;
    use Recipient;
    use Sender;
    use NotificationTrait;

    protected static ?string $name = 'mailable.editReviewNotify.name';
    protected static ?string $description = 'mailable.editReviewNotify.description';
    protected static ?string $emailTemplateKey = 'NOTIFICATION';
    protected static array $groupIds = [self::GROUP_REVIEW];
    protected static array $fromRoleIds = [Role::ROLE_ID_SUB_EDITOR];
    protected static array $toRoleIds = [Role::ROLE_ID_REVIEWER];

    public function __construct(
        Context $context,
        Submission $submission,
        ReviewAssignment $reviewAssignment,
        Notification $notification
    )
    {
        parent::__construct(array_slice(func_get_args(), 0, -1));
        $this->setupNotificationVariables($notification);
    }

    public static function getDataDescriptions(): array
    {
        $variables = parent::getDataDescriptions();
        return static::addNotificationVariablesDescription($variables);
    }
}
