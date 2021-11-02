<?php

/**
 * @file mail/mailables/Recipient.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Recipient
 * @ingroup mail_mailables
 *
 * @brief mailable's trait to support User recipients
 */

namespace PKP\mail\mailables;

use BadMethodCallException;
use InvalidArgumentException;
use PKP\mail\Mailable;
use PKP\mail\variables\RecipientEmailVariable;
use PKP\user\User;

trait Recipient {

    /**
     * @copydoc Illuminate\Mail\Mailable::setAddress()
     */
    abstract protected function setAddress($address, $name = null, $property = 'to');

    /**
     * @copydoc PKP\mail\Mailable::addVariables()
     */
    abstract public function addVariables(array $variables) : Mailable;

    /**
     * @copydoc Illuminate\Mail\Mailable::to()
     */
    public function to($address, $name = null)
    {
        throw new BadMethodCallException(static::class . ' doesn\'t support ' . __FUNCTION__ . '(), use recipients() instead');
    }

    /**
     * Set recipients of the email and set values for related template variables
     * @param User[] $recipients
     * @param string|null $defaultLocale
     * @return Mailable
     */
    public function recipients(array $recipients) : Mailable
    {
        $to = [];
        foreach ($recipients as $recipient) {
            if (!is_a($recipient, User::class)) {
                throw new InvalidArgumentException('Expecting an array consisting of instances of ' . User::class . ' to be passed to ' . static::class . '::' . __FUNCTION__);
            }

            $to[] = [
                'email' => $recipient->getEmail(),
                'name' => $recipient->getFullName(),
            ];
        }
        $this->setAddress($to);

        $recipientVars = new RecipientEmailVariable($recipients);
        return $this->addVariables($recipientVars->getValue());
    }
}
