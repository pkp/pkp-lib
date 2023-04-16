<?php

/**
 * @file classes/observers/events/UserRegisteredSite.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserRegisteredSite
 *
 * @ingroup observers_events
 *
 * @brief An event fired when a user registers from the site-wide registration form
 */

namespace PKP\observers\events;

use Illuminate\Foundation\Events\Dispatchable;
use PKP\site\Site;
use PKP\user\User;

class UserRegisteredSite
{
    use Dispatchable;

    public User $recipient;

    public Site $site;

    public function __construct(User $recipient, Site $site)
    {
        $this->recipient = $recipient;
        $this->site = $site;
    }
}
