<?php

/**
 * @file classes/mail/traits/PasswordResetUrl.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PasswordResetUrl
 *
 * @ingroup mail_traits
 *
 * @brief Mailable trait to ensure compatibility of `passwordResetUrl` variable for templates:
 * REVIEW_REMIND, REVIEW_REMIND_AUTO, REVIEW_REMIND_OVERDUE_AUTO, PASSWORD_RESET_CONFIRM
 */

namespace PKP\mail\traits;

use APP\core\Application;
use PKP\security\Validation;
use PKP\user\User;

trait PasswordResetUrl
{
    protected static string $passwordResetUrl = 'passwordResetUrl';

    /**
     * Adds passwordResetUrl variable's value to the Mailable
     */
    protected function setPasswordResetUrl(User $user, ?string $contextUrlPath = null): void
    {
        $request = Application::get()->getRequest();
        $dispatcher = $request->getDispatcher();

        $this->addData([self::$passwordResetUrl =>
            $dispatcher->url(
                $request,
                Application::ROUTE_PAGE,
                $contextUrlPath,
                'login',
                'resetPassword',
                $user->getUsername(),
                ['confirm' => Validation::generatePasswordResetHash($user->getId())]
            )
        ]);
    }

    /**
     * Adds passwordResetUrl variable's description to the Mailable
     */
    public static function addPasswordResetUrlDescription(array $variables): array
    {
        $variables[self::$passwordResetUrl] = __('emailTemplate.variable.passwordResetUrl');
        return $variables;
    }
}
