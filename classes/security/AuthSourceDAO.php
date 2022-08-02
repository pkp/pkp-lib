<?php

/**
 * @file classes/security/AuthSourceDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AuthSourceDAO
 * @ingroup security
 *
 * @see AuthSource
 *
 * @brief Operations for retrieving and modifying AuthSource objects.
 */

namespace PKP\security;

use PKP\db\DAOResultFactory;
use PKP\plugins\PluginRegistry;

class AuthSourceDAO extends \PKP\db\DAO
{
    public $plugins;

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->plugins = PluginRegistry::loadCategory('auth');
    }

    /**
     * Get plugin instance corresponding to the ID.
     *
     * @param int $authId
     *
     * @return AuthPlugin
     */
    public function getPlugin($authId)
    {
        $plugin = null;
        $auth = $this->getSource($authId);
        if ($auth != null) {
            $plugin = $auth->getPluginClass();
            if ($plugin != null) {
                $plugin = $plugin->getInstance($auth->getSettings(), $auth->getAuthId());
            }
        }
        return $plugin;
    }

    /**
     * Get plugin instance for the default authentication source.
     *
     * @return AuthPlugin
     */
    public function getDefaultPlugin()
    {
        $plugin = null;
        $auth = $this->getDefaultSource();
        if ($auth != null) {
            $plugin = $auth->getPluginClass();
            if ($plugin != null) {
                $plugin = $plugin->getInstance($auth->getSettings(), $auth->getAuthId());
            }
        }
        return $plugin;
    }

    /**
     * Retrieve a source.
     *
     * @param int $authId
     *
     * @return AuthSource
     */
    public function getSource($authId)
    {
        $result = $this->retrieve(
            'SELECT * FROM auth_sources WHERE auth_id = ?',
            [(int) $authId]
        );
        $row = $result->current();
        return $row ? $this->_fromRow((array) $row) : null;
    }

    /**
     * Retrieve the default authentication source.
     *
     * @return AuthSource
     */
    public function getDefaultSource()
    {
        $result = $this->retrieve(
            'SELECT * FROM auth_sources WHERE auth_default = 1'
        );
        $row = $result->current();
        return $row ? $this->_fromRow((array) $row) : null;
    }

    /**
     * Instantiate and return a new data object.
     *
     * @return AuthSource
     */
    public function newDataObject()
    {
        return new AuthSource();
    }

    /**
     * Internal function to return an AuthSource object from a row.
     *
     * @param array $row
     *
     * @return AuthSource
     */
    public function _fromRow($row)
    {
        $auth = $this->newDataObject();
        $auth->setAuthId($row['auth_id']);
        $auth->setTitle($row['title']);
        $auth->setPlugin($row['plugin']);
        $auth->setPluginClass(@$this->plugins[$row['plugin']]);
        $auth->setDefault($row['auth_default']);

        // pkp/pkp-lib#5091 Ensure that we can unserialize content with pre-FQCNs
        if (!class_exists('\AuthSourceDAO')) {
            class_alias('\PKP\security\AuthSourceDAO', '\AuthSourceDAO');
        }

        $auth->setSettings(unserialize($row['settings']));
        return $auth;
    }

    /**
     * Insert a new source.
     *
     * @param AuthSource $auth
     */
    public function insertObject($auth)
    {
        if (!isset($this->plugins[$auth->getPlugin()])) {
            return false;
        }
        if (!$auth->getTitle()) {
            $auth->setTitle($this->plugins[$auth->getPlugin()]->getDisplayName());
        }
        $this->update(
            'INSERT INTO auth_sources
				(title, plugin, settings)
				VALUES
				(?, ?, ?)',
            [
                $auth->getTitle(),
                $auth->getPlugin(),
                serialize($auth->getSettings() ? $auth->getSettings() : [])
            ]
        );

        $auth->setAuthId($this->_getInsertId('auth_sources', 'auth_id'));
        return $auth->getAuthId();
    }

    /**
     * Update a source.
     *
     * @param AuthSource $auth
     */
    public function updateObject($auth)
    {
        $this->update(
            'UPDATE auth_sources SET
				title = ?,
				settings = ?
			WHERE	auth_id = ?',
            [
                $auth->getTitle(),
                serialize($auth->getSettings() ? $auth->getSettings() : []),
                (int) $auth->getAuthId()
            ]
        );
    }

    /**
     * Delete a source.
     *
     * @param int $authId
     */
    public function deleteObject($authId)
    {
        $this->update(
            'DELETE FROM auth_sources WHERE auth_id = ?',
            [$authId]
        );
    }

    /**
     * Set the default authentication source.
     *
     * @param int $authId
     */
    public function setDefault($authId)
    {
        $this->update(
            'UPDATE auth_sources SET auth_default = 0'
        );
        $this->update(
            'UPDATE auth_sources SET auth_default = 1 WHERE auth_id = ?',
            [(int) $authId]
        );
    }

    /**
     * Retrieve a list of all auth sources for the site.
     *
     * @param null|mixed $rangeInfo
     *
     * @return array AuthSource
     */
    public function getSources($rangeInfo = null)
    {
        $result = $this->retrieveRange(
            'SELECT * FROM auth_sources ORDER BY auth_id',
            [],
            $rangeInfo
        );

        return new DAOResultFactory($result, $this, '_fromRow');
    }
}

if (!PKP_STRICT_MODE) {
    if (!class_exists('\AuthSourceDAO')) {
        class_alias('\PKP\security\AuthSourceDAO', '\AuthSourceDAO');
    }
}
