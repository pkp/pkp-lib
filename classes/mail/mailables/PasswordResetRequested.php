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
