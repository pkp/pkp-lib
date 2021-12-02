<?php

/**
 * @file mail/Sender.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Sender
 * @ingroup mail
 *
 * @brief mailable's trait to support User sender
 */

namespace PKP\mail;

use BadMethodCallException;
use PKP\mail\variables\SenderEmailVariable;
use PKP\user\User;

trait Sender
{
    /**
     * @copydoc Illuminate\Mail\Mailable::setAddress()
     */
    abstract protected function setAddress($address, $name = null, $property = 'to');

    /**
     * @copydoc Illuminate\Mail\Mailable::from()
     */
    public function from($address, $name = null) {
        throw new BadMethodCallException(static::class . ' doesn\'t support ' . __FUNCTION__ . '(), use sender() instead');
    }

    /**
     * Set recipients of the email and set values for related template variables
     * @param User $sender
     * @param string|null $defaultLocale
     * @return Mailable
     */
    public function sender(User $sender, ?string $defaultLocale = null) : Mailable
    {
        $this->setAddress($sender->getEmail(), $sender->getFullName($defaultLocale), 'from');
        $this->variables[] = new SenderEmailVariable($sender);
        return $this;
    }
}
