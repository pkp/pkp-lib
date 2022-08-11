<?php

/**
 * @file classes/mail/mailables/PublicationVersionNotify.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicationVersionNotify
 *
 * @brief Email is automatically sent to editors assigned to submission when new publication version is created
 */

namespace PKP\mail\mailables;

use APP\notification\Notification;
use APP\submission\Submission;
use PKP\context\Context;
use PKP\mail\Mailable;
use PKP\mail\traits\Configurable;
use PKP\mail\traits\Recipient;
use APP\mail\variables\ContextEmailVariable;
use PKP\security\Role;
use PKP\mail\traits\Notification as NotificationTrait;

class PublicationVersionNotify extends Mailable
{
    use Configurable;
    use Recipient;
    use NotificationTrait;

    protected static ?string $name = 'mailable.publicationVersionNotify.name';
    protected static ?string $description = 'mailable.publicationVersionNotify.description';
    protected static ?string $emailTemplateKey = 'NOTIFICATION';
    protected static array $groupIds = [self::GROUP_PRODUCTION];
    protected static array $toRoleIds = [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR];

    public function __construct(Context $context, Submission $submission, Notification $notification)
    {
        parent::__construct([$context, $submission]);
        $this->setupNotificationVariables($notification);
    }

    public static function getDataDescriptions(): array
    {
        $variables = parent::getDataDescriptions();
        return static::addNotificationVariablesDescription($variables);
    }
}
