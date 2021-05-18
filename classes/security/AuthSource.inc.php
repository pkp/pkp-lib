<?php

/**
 * @file classes/security/AuthSource.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AuthSource
 * @ingroup security
 *
 * @see AuthSourceDAO
 *
 * @brief Describes an authentication source.
 */

namespace PKP\security;

use PKP\plugins\AuthPlugin;

class AuthSource extends \PKP\core\DataObject
{
    //
    // Get/set methods
    //

    /**
     * Get ID of this source.
     *
     * @return int
     */
    public function getAuthId()
    {
        return $this->getData('authId');
    }

    /**
     * Set ID of this source.
     *
     * @param $authId int
     */
    public function setAuthId($authId)
    {
        $this->setData('authId', $authId);
    }

    /**
     * Get user-specified title of this source.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->getData('title');
    }

    /**
     * Set user-specified title of this source.
     *
     * @param $title string
     */
    public function setTitle($title)
    {
        $this->setData('title', $title);
    }

    /**
     * Get the authentication plugin associated with this source.
     *
     * @return string
     */
    public function getPlugin()
    {
        return $this->getData('plugin');
    }

    /**
     * Set the authentication plugin associated with this source.
     *
     * @param $plugin string
     */
    public function setPlugin($plugin)
    {
        $this->setData('plugin', $plugin);
    }

    /**
     * Get flag indicating this is the default authentication source.
     *
     * @return boolean
     */
    public function getDefault()
    {
        return $this->getData('authDefault');
    }

    /**
     * Set flag indicating this is the default authentication source.
     *
     * @param $authDefault boolean
     */
    public function setDefault($authDefault)
    {
        $this->setData('authDefault', $authDefault);
    }

    /**
     * Get array of plugin-specific settings for this source.
     *
     * @return array
     */
    public function getSettings()
    {
        return $this->getData('settings');
    }

    /**
     * Set array of plugin-specific settings for this source.
     *
     * @param $settings array
     */
    public function setSettings($settings)
    {
        $this->setData('settings', $settings);
    }

    /**
     * Get the authentication plugin object associated with this source.
     *
     * @return AuthPlugin
     */
    public function &getPluginClass()
    {
        $returner = & $this->getData('authPlugin');
        return $returner;
    }

    /**
     * Set authentication plugin object associated with this source.
     *
     * @param $authPlugin AuthPlugin
     */
    public function setPluginClass($authPlugin)
    {
        $this->setData('authPlugin', $authPlugin);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\AuthSource', '\AuthSource');
}
