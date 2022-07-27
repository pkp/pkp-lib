<?php

/**
 * @file classes/plugins/AuthPlugin.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AuthPlugin
 * @ingroup plugins
 *
 * @brief Abstract class for authentication plugins.
 *
 * TODO: Error reporting when updating remote source fails.
 * TODO: Support importing user accounts from the authentication source into OJS/OMP.
 */

namespace PKP\plugins;

abstract class AuthPlugin extends Plugin
{
    /** @var array $settings settings for this plugin instance */
    public $settings;

    /** @var int $authId auth source ID for this plugin instance */
    public $authId;

    /**
     * Constructor.
     *
     * @param array $settings
     * @param int $authId ID for this instance
     */
    public function __construct($settings = [], $authId = null)
    {
        parent::__construct();
        $this->settings = $settings;
        $this->authId = $authId;
    }


    //
    // General Plugin Functions
    //
    /**
     * Return the path to a template for plugin settings.
     * Can return null if there are no plugin-specific settings.
     *
     * @return string
     */
    public function getSettingsTemplate()
    {
        return $this->getTemplateResource('settings.tpl');
    }


    //
    // Wrapper Functions
    //
    /**
     * Update local user profile from the remote source, if enabled.
     *
     * @param User $user
     *
     * @return bool true if successful
     */
    public function doGetUserInfo($user)
    {
        if (isset($this->settings['syncProfiles'])) {
            return $this->getUserInfo($user);
        }
        return false;
    }

    /**
     * Update remote user profile, if enabled.
     *
     * @param User $user
     *
     * @return bool true if successful
     */
    public function doSetUserInfo($user)
    {
        if (isset($this->settings['syncProfiles'])) {
            return $this->setUserInfo($user);
        }
        return false;
    }

    /**
     * Update remote user password, if enabled.
     *
     * @param string $username
     * @param string $password
     *
     * @return bool true if successful
     */
    public function doSetUserPassword($username, $password)
    {
        if (isset($this->settings['syncPasswords'])) {
            return $this->setUserPassword($username, $password);
        }
        return false;
    }

    /**
     * Create remote user account, if enabled.
     *
     * @param User $user User to create
     *
     * @return bool true if successful
     */
    public function doCreateUser($user)
    {
        if (isset($this->settings['createUsers'])) {
            return $this->createUser($user);
        }
        return false;
    }


    //
    // Core Plugin Functions
    // (Must be implemented by every authentication plugin)
    //
    /**
     * Returns an instance of the authentication plugin
     *
     * @param array $settings settings specific to this instance
     * @param int $authId identifier for this instance
     *
     * @return AuthPlugin
     */
    abstract public function getInstance($settings, $authId);

    /**
     * Authenticate a username and password.
     *
     * @param string $username
     * @param string $password
     *
     * @return bool true if authentication is successful
     */
    abstract public function authenticate($username, $password);


    //
    // Optional Plugin Functions
    // (Required for extended functionality but not for authentication-only plugins)
    //
    /**
     * Check if a username exists.
     *
     * @param string $username
     *
     * @return bool
     */
    public function userExists($username)
    {
        return false;
    }

    /**
     * Retrieve user profile information from the remote source.
     * Any unsupported fields (e.g., OJS- or OMP-specific ones) should not be modified.
     *
     * @param User $user User to update
     *
     * @return bool true if successful
     */
    public function getUserInfo($user)
    {
        return false;
    }

    /**
     * Store user profile information on the remote source.
     *
     * @param User $user User to store
     *
     * @return bool true if successful
     */
    public function setUserInfo($user)
    {
        return false;
    }

    /**
     * Change a user's password on the remote source.
     *
     * @param string $username user to update
     * @param string $password the new password
     *
     * @return bool true if successful
     */
    public function setUserPassword($username, $password)
    {
        return false;
    }

    /**
     * Create a user on the remote source.
     *
     * @param User $user User to create
     *
     * @return bool true if successful
     */
    public function createUser($user)
    {
        return false;
    }

    /**
     * Delete a user from the remote source.
     * This function is currently not used within OJS or OMP,
     * but is reserved for future use.
     *
     * @param string $username user to delete
     *
     * @return bool true if successful
     */
    public function deleteUser($username)
    {
        return false;
    }

    /**
     * Return true iff this is a site-wide plugin.
     */
    public function isSitePlugin()
    {
        return true;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\plugins\AuthPlugin', '\AuthPlugin');
}
