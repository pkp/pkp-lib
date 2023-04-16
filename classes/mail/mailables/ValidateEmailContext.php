<?php

/**
 * @file classes/mail/mailables/ValidateEmailContext.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ValidateEmailContext
 *
 * @ingroup mail_mailables
 *
 * @brief Represents registration validation email
 */

namespace PKP\mail\mailables;

use PKP\context\Context;
use PKP\mail\Mailable;
use PKP\mail\traits\Configurable;
use PKP\mail\traits\Recipient;
use PKP\security\Role;

class ValidateEmailContext extends Mailable
{
    use Configurable;
    use Recipient;

    protected static ?string $name = 'mailable.validateEmailContext.name';
    protected static ?string $description = 'mailable.validateEmailContext.description';
    protected static ?string $emailTemplateKey = 'USER_VALIDATE_CONTEXT';
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

    public function __construct(Context $context)
    {
        parent::__construct(func_get_args());
    }
}
