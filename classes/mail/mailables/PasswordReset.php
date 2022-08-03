<?php

/**
 * @file classes/mail/mailables/PasswordReset.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PasswordReset
 * @ingroup mail_mailables
 *
 * @brief Email sent automatically when user resets a password
 */

namespace PKP\mail\mailables;

use PKP\mail\traits\Configurable;
use PKP\mail\Mailable;
use PKP\mail\traits\Recipient;
use PKP\site\Site;

class PasswordReset extends Mailable
{
    use Recipient {
        recipients as traitRecipients;
    }
    use Configurable;

    protected static ?string $name = 'mailable.passwordReset.name';
    protected static ?string $description = 'mailable.passwordReset.description';
    protected static ?string $emailTemplateKey = 'PASSWORD_RESET';
    protected static array $groupIds = [self::GROUP_OTHER];
    protected static ?string $variablePassword = 'password';

    public function __construct(Site $site, string $newPassword)
    {
        parent::__construct([$site]);
        $this->setupPasswordVariable($newPassword);
    }

    /**
     * @copydoc Mailable::getDataDescriptions()
     */
    public static function getDataDescriptions(): array
    {
        $variables = parent::getDataDescriptions();
        return static::addPasswordVariable($variables);
    }

    /**
     * Add a description to a new password variable
     */
    protected static function addPasswordVariable(array $variables): array
    {
        $variables[static::$variablePassword] = __('emailTemplate.variable.password');
        return $variables;
    }

    protected function setupPasswordVariable(string $newPassword): void
    {
        $this->addData([
            static::$variablePassword => $newPassword,
        ]);
    }
}
