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
use PKP\i18n\PKPLocale;
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
    protected function values(): array
    {
        return
        [
            self::SENDER_NAME => $this->getUserFullName(),
            self::SENDER_EMAIL => $this->getUserEmail(),
            self::SENDER_CONTACT_SIGNATURE => $this->getUserContactSignature(),
        ];
    }

    /**
     * Array of sender's full name in supported locales
     */
    protected function getUserFullName() : array
    {
        $fullNameLocalized = [];
        $supportedLocales = PKPLocale::getSupportedLocales();
        foreach ($supportedLocales as $localeKey => $localeValue) {
            $fullNameLocalized[$localeKey] = $this->sender->getFullName(true, false, $localeKey);
        }
        return $fullNameLocalized;
    }

    /**
     * Sender's email
     */
    protected function getUserEmail() : string
    {
        return $this->sender->getData('email');
    }

    /**
     * Sender's contact signature
     */
    protected function getUserContactSignature() : array
    {
        $supportedLocales = PKPLocale::getSupportedLocales();
        PKPLocale::requireComponents(LOCALE_COMPONENT_PKP_USER);
        $signatureLocalized = [];
        foreach ($supportedLocales as $localeKey => $localeValue) {
            $signature = $this->sender->getSignature($localeKey);
            if (!$signature) {
                $signature = '';
            } else {
                $signature = PKPString::stripUnsafeHtml($signature);
            }
            $signatureLocalized[$localeKey] = $signature;
        }

        return $signatureLocalized;
    }
}
