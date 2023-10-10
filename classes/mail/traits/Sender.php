<?php

/**
 * @file classes/mail/traits/Sender.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Sender
 *
 * @ingroup mail_traits
 *
 * @brief Mailable trait to set the sender to a User
 */

namespace PKP\mail\traits;

use BadMethodCallException;
use PKP\mail\Mailable;
use PKP\mail\variables\SenderEmailVariable;
use PKP\user\User;

trait Sender
{
    /**
     * User that sends mailable
     */
    protected User $sender;

    /**
     * @copydoc Illuminate\Mail\Mailable::setAddress()
     *
     * @param null|mixed $name
     */
    abstract protected function setAddress($address, $name = null, $property = 'to');

    /**
     * @copydoc Illuminate\Mail\Mailable::from()
     *
     * @param null|mixed $name
     */
    public function from($address, $name = null)
    {
        throw new BadMethodCallException(static::class . ' doesn\'t support ' . __FUNCTION__ . '(), use sender() instead');
    }

    /**
     * Set recipients of the email and set values for related template variables
     */
    public function sender(User $sender, ?string $defaultLocale = null): Mailable
    {
        $this->setAddress($sender->getEmail(), $sender->getFullName(true, false, $defaultLocale), 'from');
        $this->variables[] = new SenderEmailVariable($sender, $this);
        $this->sender = $sender;
        return $this;
    }

    public function getSenderUser(): User
    {
        return $this->sender;
    }
}
