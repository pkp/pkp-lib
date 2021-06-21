<?php

/**
 * @file classes/observers/events/UserRegisteredContext.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserRegisteredContext
 * @ingroup observers_events
 *
 * @brief An event fired when a user registers with a context
 */

namespace PKP\observers\events;

use Illuminate\Foundation\Events\Dispatchable;
use PKP\context\Context;
use PKP\user\User;

class UserRegisteredContext
{
    use Dispatchable;

    public User $recipient;

    public Context $context;

    public function __construct(User $recipient, Context $context)
    {
        $this->recipient = $recipient;
        $this->context = $context;
    }
}
