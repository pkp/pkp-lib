<?php

/**
 * @file mail/mailables/Sender.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Sender
 * @ingroup mail_mailables
 *
 * @brief mailable's trait to support User sender
 */

namespace PKP\mail\mailables;

use BadMethodCallException;
use PKP\mail\Mailable;
use PKP\mail\variables\SenderEmailVariable;
use PKP\user\User;

trait Sender
{
    /**
     * @copydoc Illuminate\Mail\Mailable::setAddress()
     */
    abstract protected function setAddress($address, $name = null, $property = 'to');

    /**
     * @copydoc PKP\mail\Mailable::addVariables()
     */
    abstract public function addVariables(array $variables) : Mailable;

    /**
     * @copydoc Illuminate\Mail\Mailable::from()
     */
    public function from($address, $name = null) {
        throw new BadMethodCallException(static::class . ' doesn\'t support ' . __FUNCTION__ . '(), use setSender() instead');
    }

    /**
     * Set recipients of the email and set values for related template variables
     * @param User $sender
     * @param string|null $defaultLocale
     * @return Mailable
     */
    public function setSender(User $sender, ?string $defaultLocale = null) : Mailable
    {
        $this->setAddress($sender->getEmail(), $sender->getFullName($defaultLocale), 'from');

        $senderVars = new SenderEmailVariable($sender);
        return $this->addVariables($senderVars->getValue());
    }
}
