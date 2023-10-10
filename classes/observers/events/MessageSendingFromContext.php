<?php

/**
 * @file classes/observers/events/MessageSendingFromContext.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class MessageSendingFromContext
 *
 * @ingroup observers_events
 *
 * @brief overrides Illuminate event which is fired just before sending email message from the journal/press
 */

namespace PKP\observers\events;

use Illuminate\Mail\Events\MessageSending as IlluminateMessageSending;
use PKP\context\Context;
use Symfony\Component\Mime\Email as SymfonyEmail;

class MessageSendingFromContext extends IlluminateMessageSending
{
    public Context $context;

    public function __construct(Context $context, SymfonyEmail $message, array $data = [])
    {
        parent::__construct($message, $data);
        $this->context = $context;
    }
}
