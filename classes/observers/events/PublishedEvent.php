<?php

declare(strict_types=1);

/**
 * @file classes/observers/events/PublishedEvent.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublishedEvent
 * @ingroup observers_events
 *
 * @brief Event fired when publication is published
 */

namespace PKP\observers\events;

use Illuminate\Foundation\Events\Dispatchable;
use PKP\observers\traits\Publishable;

class PublishedEvent
{
    use Dispatchable;
    use Publishable;
}
