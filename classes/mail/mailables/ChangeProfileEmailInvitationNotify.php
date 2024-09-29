<?php

/**
 * @file classes/mail/mailables/ChangeProfileEmailInvitationNotify.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ChangeProfileEmailInvitationNotify
 *
 * @ingroup mail_mailables
 *
 * @brief Email sent when a new user requests an email change
 */

namespace PKP\mail\mailables;

use PKP\mail\Mailable;
use PKP\mail\traits\Configurable;
use PKP\mail\traits\Recipient;
use PKP\mail\traits\Sender;
use PKP\security\Role;

class ChangeProfileEmailInvitationNotify extends Mailable
{
    use Recipient;
    use Configurable;
    use Sender;

    protected static ?string $name = 'mailable.changeProfileEmailInvitationNotify.name';
    protected static ?string $description = 'mailable.changeProfileEmailInvitationNotify.description';
    protected static ?string $emailTemplateKey = 'CHANGE_EMAIL';
    protected static array $groupIds = [self::GROUP_OTHER];
    protected static array $fromRoleIds = [
        self::FROM_SYSTEM,
    ];
    protected static array $toRoleIds = [
        Role::ROLE_ID_SUB_EDITOR,
        Role::ROLE_ID_ASSISTANT,
        Role::ROLE_ID_AUTHOR,
        Role::ROLE_ID_READER,
        Role::ROLE_ID_REVIEWER,
        Role::ROLE_ID_SUBSCRIPTION_MANAGER,
    ];

    /**
     * @copydoc Mailable::getDataDescriptions()
     */
    public static function getDataDescriptions(): array
    {
        $variables = parent::getDataDescriptions();
        return static::addPasswordVariable($variables);
    }
}
