<?php

/**
 * @file classes/plugins/GatewayPlugin.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GatewayPlugin
 *
 * @ingroup plugins
 *
 * @brief Abstract class for gateway plugins
 */

namespace PKP\plugins;

use APP\core\Application;
use PKP\core\PKPRequest;

abstract class GatewayPlugin extends Plugin
{
    /**
     * Handle fetch requests for this plugin.
     *
     * @param array $args
     * @param object $request
     */
    abstract public function fetch($args, $request);

    /**
     * Determine whether the plugin can be enabled.
     *
     * @return bool
     */
    public function getCanEnable()
    {
        return true;
    }

    /**
     * Determine whether the plugin can be disabled.
     *
     * @return bool
     */
    public function getCanDisable()
    {
        return true;
    }

    /**
     * Determine whether or not this plugin is currently enabled.
     *
     * @return bool
     */
    public function getEnabled()
    {
        return $this->getSetting($this->getCurrentContextId(), 'enabled');
    }

    /**
     * Set whether or not this plugin is currently enabled.
     *
     * @param bool $enabled
     */
    public function setEnabled($enabled)
    {
        $this->updateSetting($this->getCurrentContextId(), 'enabled', $enabled, 'bool');
    }

    /**
     * Get the current context ID or the site-wide context ID (Application::SITE_CONTEXT_ID) if no context
     * can be found.
     */
    public function getCurrentContextId()
    {
        return Application::get()->getRequest()->getContext()?->getId() ?? Application::SITE_CONTEXT_ID;
    }

    /**
     * Get policies to the authorization process
     *
     * @param PKPRequest $request
     *
     * @return array Set of authorization policies
     */

    public function getPolicies($request)
    {
        return [];
    }
}
