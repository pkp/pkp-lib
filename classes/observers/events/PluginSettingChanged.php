<?php

declare(strict_types=1);

/**
 * @file classes/observers/events/PluginSettingChanged.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PluginSettingChanged
 * @ingroup observers_events
 *
 * @brief Event fired when a plugin's setting is changed, including whether
 *   it is enabled or disabled.
 */

namespace PKP\observers\events;

use Illuminate\Foundation\Events\Dispatchable;
use PKP\plugins\Plugin;

class PluginSettingChanged
{
    use Dispatchable;

    public Plugin $plugin;
    public string $settingName;
    public mixed $newValue;
    public ?int $contextId;

    public function __construct(
        Plugin $plugin,
        string $settingName,
        $newValue,
        ?int $contextId = null
    )
    {
        $this->plugin = $plugin;
        $this->settingName = $settingName;
        $this->newValue = $newValue;
        $this->contextId = $contextId;
    }
}
