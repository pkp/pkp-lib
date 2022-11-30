<?php

/**
 * @file classes/observers/events/MessageSendingContext.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class MessageSendingContext
 * @ingroup observers_events
 *
 * @brief overrides Illuminate event which is fired just before sending email message from the journal/press
 */

namespace PKP\observers\events;

use Illuminate\Mail\Events\MessageSending as IlluminateMessageSending;
use PKP\context\Context;
use PKP\mail\Mailer;
use Symfony\Component\Mime\Email as SymfonyEmail;

class MessageSendingContext extends IlluminateMessageSending
{
    public Context $context;

    /** @var Mailer which sends email */
    public Mailer $mailer;

    public function __construct(Context $context, SymfonyEmail $message, Mailer $mailer, array $data = [])
    {
        parent::__construct($message, $data);
        $this->context = $context;
        $this->mailer = $mailer;
    }
}
