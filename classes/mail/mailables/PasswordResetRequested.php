<?php

/**
 * @file classes/mail/mailables/PasswordResetRequested.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PasswordResetRequested
 * @ingroup mail_mailables
 *
 * @brief Email sent automatically when user requests to reset a password
 */

namespace PKP\mail\mailables;

use PKP\mail\traits\Configurable;
use PKP\mail\Mailable;
use PKP\mail\traits\PasswordResetUrl;
use PKP\mail\traits\Recipient;
use PKP\security\Role;
use PKP\site\Site;
use PKP\user\User;

class PasswordResetRequested extends Mailable
{
    use Recipient {
        recipients as recipientsTrait;
    }
    use Configurable;
    use PasswordResetUrl;

    protected static ?string $name = 'mailable.passwordResetRequested.name';
    protected static ?string $description = 'mailable.passwordResetRequested.description';
    protected static ?string $emailTemplateKey = 'PASSWORD_RESET_CONFIRM';
    protected static array $groupIds = [self::GROUP_OTHER];
    protected static array $fromRoleIds = [self::FROM_SYSTEM];
    protected static array $toRoleIds = [
        Role::ROLE_ID_SUB_EDITOR,
        Role::ROLE_ID_ASSISTANT,
        Role::ROLE_ID_AUTHOR,
        Role::ROLE_ID_READER,
        Role::ROLE_ID_REVIEWER,
        Role::ROLE_ID_SUBSCRIPTION_MANAGER,
    ];

    public function __construct(Site $site)
    {
        parent::__construct(func_get_args());
    }

    public function recipients(User $recipient, ?string $locale = null): Mailable
    {
        $this->recipientsTrait([$recipient], $locale);
        $this->setPasswordResetUrl($recipient);

        return $this;
    }
}
