<?php

/**
 * @file classes/mail/variables/SenderEmailVariable.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SenderEmailVariable
 * @ingroup mail_variables
 *
 * @brief Represents variables that are associated with an email sender
 */

namespace PKP\mail\variables;

use PKP\core\PKPString;
use PKP\user\User;

class SenderEmailVariable extends Variable
{
    const SENDER_NAME = 'senderName';
    const SENDER_EMAIL = 'senderEmail';
    const SENDER_CONTACT_SIGNATURE = 'signature';

    protected User $sender;

    public function __construct(User $sender)
    {
        $this->sender = $sender;
    }

    /**
     * @copydoc Variable::description()
     */
    protected static function description(): array
    {
        return
        [
            self::SENDER_NAME => __('emailTemplate.variable.sender.senderName'),
            self::SENDER_EMAIL => __('emailTemplate.variable.sender.senderEmail'),
            self::SENDER_CONTACT_SIGNATURE => __('emailTemplate.variable.sender.signature'),
        ];
    }

    /**
     * @copydoc Variable::values()
     */
    public function values(string $locale): array
    {
        return
        [
            self::SENDER_NAME => $this->sender->getFullName(true, false, $locale),
            self::SENDER_EMAIL => $this->sender->getData('email'),
            self::SENDER_CONTACT_SIGNATURE => $this->getSignature($locale),
        ];
    }

    /**
     * Sender's contact signature
     */
    protected function getSignature(string $locale): string
    {
        $signature = $this->sender->getSignature($locale);
        return $signature
            ? PKPString::stripUnsafeHtml($signature)
            : '';
    }
}
