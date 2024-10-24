<?php

/**
 * @file classes/mail/mailables/UserRoleEndNotify.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserRoleEndNotify
 *
 * @brief Email sent when a user is removed from a particular role
 */

namespace PKP\mail\mailables;

use PKP\context\Context;
use PKP\core\Core;
use PKP\facades\Locale;
use PKP\mail\Mailable;
use PKP\mail\traits\Configurable;
use PKP\mail\traits\Recipient;
use PKP\mail\traits\Sender;
use PKP\security\Role;
use PKP\userGroup\UserGroup;

class UserRoleEndNotify extends Mailable
{
    use Configurable;
    use Recipient;
    use Sender;

    protected static ?string $name = 'mailable.userRoleEndNotify.name';
    protected static ?string $description = 'mailable.userRoleEndNotify.description';
    protected static ?string $emailTemplateKey = 'USER_ROLE_END';

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

    protected static string $roleRemoved = 'roleRemoved';

    private UserGroup $userGroup;

    public function __construct(Context $context, UserGroup $userGroup)
    {
        parent::__construct(array_slice(func_get_args(), 0, -1));
        $this->userGroup = $userGroup;
    }

    /**
     * Add description to a new email template variables
     */
    public static function getDataDescriptions(): array
    {
        $variables = parent::getDataDescriptions();
        $variables[static::$roleRemoved] = __('emailTemplate.variable.userRoleEnd.roleRemoved');

        return $variables;
    }

    public function setData(?string $locale = null): void
    {
        parent::setData($locale);
        if (is_null($locale)) {
            $locale = Locale::getLocale();
        }

        $targetPath = Core::getBaseDir() . '/lib/pkp/styles/mailables/style.css';
        $emailTemplateStyle = file_get_contents($targetPath);

        $role = $this->userGroup->getName($locale);

        // Set view data for the template
        $this->viewData = array_merge(
            $this->viewData,
            [
                static::$roleRemoved => $role,
                static::EMAIL_TEMPLATE_STYLE_PROPERTY => $emailTemplateStyle,
            ]
        );
    }
}
