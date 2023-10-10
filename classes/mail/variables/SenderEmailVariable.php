<?php

/**
 * @file classes/mail/variables/SenderEmailVariable.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SenderEmailVariable
 *
 * @ingroup mail_variables
 *
 * @brief Represents variables that are associated with an email sender
 */

namespace PKP\mail\variables;

use PKP\core\PKPString;
use PKP\mail\Mailable;
use PKP\user\User;

class SenderEmailVariable extends Variable
{
    public const SENDER_NAME = 'senderName';
    public const SENDER_EMAIL = 'senderEmail';
    public const SENDER_CONTACT_SIGNATURE = 'signature';

    protected User $sender;

    public function __construct(User $sender, Mailable $mailable)
    {
        parent::__construct($mailable);

        $this->sender = $sender;
    }

    /**
     * @copydoc Variable::descriptions()
     */
    public static function descriptions(): array
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
            self::SENDER_NAME => htmlspecialchars($this->sender->getFullName(true, false, $locale)),
            self::SENDER_EMAIL => htmlspecialchars($this->sender->getData('email')),
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
            : '<p>' . htmlspecialchars($this->sender->getFullName(true, false, $locale)) . '</p>';
    }
}
