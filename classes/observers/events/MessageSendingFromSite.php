<?php

/**
 * @file classes/observers/events/MessageSendingFromSite.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class MessageSendingFromSite
 *
 * @ingroup observers_events
 *
 * @brief overrides Illuminate event which is fired just before sending email message from the site
 */

namespace PKP\observers\events;

use Illuminate\Mail\Events\MessageSending as IlluminateMessageSending;
use PKP\site\Site;
use Symfony\Component\Mime\Email as SymfonyEmail;

class MessageSendingFromSite extends IlluminateMessageSending
{
    public Site $site;

    public function __construct(Site $site, SymfonyEmail $message, array $data = [])
    {
        parent::__construct($message, $data);
        $this->site = $site;
    }
}
