<?php

/**
 * @file classes/mail/variables/RecipientEmailVariable.php
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
    public const RECIPIENT_FULL_NAME = 'recipientName';
    public const RECIPIENT_USERNAME = 'recipientUsername';

    /** @var iterable<User> */
    protected iterable $recipients;

    public function __construct(iterable $recipients)
    {
        foreach ($recipients as $recipient) {
            if (!is_a($recipient, User::class)) {
                throw new InvalidArgumentException('recipient array values should be an instances or ancestors of ' . User::class . ', ' . get_class($recipient) . ' is given');
            }
        }

        $this->recipients = $recipients;
    }

    /**
     * @copydoc Variable::descriptions()
     */
    public static function descriptions(): array
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
     *
     * @return array [localeKey => fullName]
     */
    protected function getRecipientsFullName(string $locale): string
    {
        $names = [];
        foreach ($this->recipients as $recipient) {
            $names[] = $recipient->getFullName(true, false, $locale);
        }

        return join(__('common.commaListSeparator'), $names);
    }

    /**
     * Usernames of recipients separated by a comma
     */
    protected function getRecipientsUserName(): string
    {
        $userNames = [];
        foreach ($this->recipients as $recipient) {
            $userNames[] = $recipient->getData('userName');
        }
        return join(__('common.commaListSeparator'), $userNames);
    }
}
