<?php

/**
 * @file classes/mail/variables/RecipientEmailVariable.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SenderEmailVariable
 * @ingroup mail_variables
 *
 * @brief Represents variables that are associated with an email recipient
 */

namespace PKP\mail\variables;

use InvalidArgumentException;
use PKP\user\User;

class RecipientEmailVariable extends Variable
{
    const RECIPIENT_FULL_NAME = 'userFullName';
    const RECIPIENT_USERNAME = 'username';

    protected array $recipients;

    public function __construct(array $recipient)
    {
        foreach ($recipient as $user)
        {
            if (!is_a($user, User::class))
                throw new InvalidArgumentException('recipient array values should be an instances or ancestors of ' . User::class . ', ' . get_class($user) . ' is given');
        }

        $this->recipients = $recipient;
    }

    /**
     * @copydoc Variable::description()
     */
    protected static function description(): array
    {
        return
        [
            self::RECIPIENT_FULL_NAME => __('emailTemplate.variable.recipient.userFullName'),
            self::RECIPIENT_USERNAME => __('emailTemplate.variable.recipient.username'),
        ];
    }

    /**
     * @copydoc Variable::values()
     */
    public function values(string $locale): array
    {
        return
        [
            self::RECIPIENT_FULL_NAME => $this->getRecipientsFullName($locale),
            self::RECIPIENT_USERNAME => $this->getRecipientsUserName(),
        ];
    }

    /**
     * Array containing full names of recipients in all supported locales separated by a comma
     * @return array [localeKey => fullName]
     */
    protected function getRecipientsFullName(string $locale): string
    {
        $fullNames = array_map(function(User $user) use ($locale) {
            return $user->getFullName(true, false, $locale);
        }, $this->recipients);
        return join(__('common.commaListSeparator'), $fullNames);
    }

    /**
     * Usernames of recipients separated by a comma
     */
    protected function getRecipientsUserName(): string
    {
        $userNames = array_map(function (User $user) {
            return $user->getData('username');
        }, $this->recipients);
        return join(__('common.commaListSeparator'), $userNames);
    }
}
