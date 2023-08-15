<?php

/**
 * @file classes/mail/mailables/ReviewerRegister.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerRegister
 *
 * @ingroup mail_mailables
 *
 * @brief Email is sent automatically to a newly registered reviewer (see Create Reviewer Form)
 */

namespace PKP\mail\mailables;

use PKP\context\Context;
use PKP\mail\Mailable;
use PKP\mail\traits\Configurable;
use PKP\mail\traits\Recipient;
use PKP\mail\traits\Sender;
use PKP\security\Role;
use PKP\user\User;

class ReviewerRegister extends Mailable
{
    use Recipient {
        recipients as traitRecipients;
    }
    use Sender;
    use Configurable;

    protected static ?string $name = 'mailable.reviewerRegister.name';
    protected static ?string $description = 'mailable.reviewerRegister.description';
    protected static ?string $emailTemplateKey = 'REVIEWER_REGISTER';
    protected static array $groupIds = [self::GROUP_REVIEW];
    protected static array $fromRoleIds = [
        Role::ROLE_ID_SUB_EDITOR,
        Role::ROLE_ID_ASSISTANT,
    ];
    protected static array $toRoleIds = [
        Role::ROLE_ID_REVIEWER,
    ];
    protected static ?string $variablePassword = 'password';

    protected string $password;

    public function __construct(Context $context, string $password)
    {
        parent::__construct([$context]);
        $this->password = $password;
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

    /**
     * Override trait's method to include user password variable
     */
    public function recipients(User $recipient, ?string $locale = null): Mailable
    {
        $this->traitRecipients([$recipient], $locale);
        $this->addData([
            static::$variablePassword => htmlspecialchars($this->password)
        ]);

        return $this;
    }
}
