<?php

declare(strict_types=1);

/**
 * @file classes/observers/events/PluginEnabledChanged.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PluginEnabledChanged
 * @ingroup observers_events
 *
 * @brief Event fired when a plugin is enabled/disabled
 */

namespace PKP\observers\events;

use Illuminate\Foundation\Events\Dispatchable;
use PKP\plugins\LazyLoadPlugin;

class PluginEnabledChanged
{
    use Dispatchable;

    public function __construct(public LazyLoadPlugin $plugin) {
    }
}
