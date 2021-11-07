<?php

/**
 * @file classes/plugins/LazyLoadPlugin.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CachedPlugin
 * @ingroup plugins
 *
 * @brief Abstract class for plugins that optionally
 * support lazy load.
 */

namespace PKP\plugins;

use APP\core\Application;

abstract class LazyLoadPlugin extends Plugin
{
    //
    // Override public methods from Plugin
    //
    /**
     * @copydoc Plugin::register()
     *
     * @param null|mixed $mainContextId
     */
    public function register($category, $path, $mainContextId = null)
    {
        if (!parent::register($category, $path, $mainContextId)) {
            return false;
        }
        $this->addLocaleData();
        return true;
    }


    //
    // Override protected methods from Plugin
    //
    /**
     * @see Plugin::getName()
     */
    public function getName()
    {
        // Lazy load enabled plug-ins always use the plugin's class name
        // as plug-in name. Legacy plug-ins will override this method so
        // this implementation is backwards compatible.
        // NB: strtolower was required for PHP4 compatibility.
        $classNameParts = explode('\\', get_class($this)); // Separate namespace info from class name
        return strtolower_codesafe(end($classNameParts));
    }


    //
    // Public methods required to support lazy load.
    //
    /**
     * Determine whether or not this plugin is currently enabled.
     *
     * @param int $contextId To identify if the plugin is enabled
     *  we need a context. This context is usually taken from the
     *  request but sometimes there is no context in the request
     *  (e.g. when executing CLI commands). Then the main context
     *  can be given as an explicit ID.
     *
     * @return bool
     */
    public function getEnabled($contextId = null)
    {
        if ($contextId == null) {
            $contextId = $this->getCurrentContextId();
            if ($this->isSitePlugin()) {
                $contextId = 0;
            }
        }
        return $this->getSetting($contextId, 'enabled');
    }

    /**
     * Set whether or not this plugin is currently enabled.
     *
     * @param bool $enabled
     */
    public function setEnabled($enabled)
    {
        $contextId = $this->getCurrentContextId();
        if ($this->isSitePlugin()) {
            $contextId = 0;
        }
        $this->updateSetting($contextId, 'enabled', $enabled, 'bool');
    }

    /**
     * @copydoc Plugin::getCanEnable()
     */
    public function getCanEnable()
    {
        return true;
    }

    /**
     * @copydoc Plugin::getCanDisable()
     */
    public function getCanDisable()
    {
        return true;
    }

    /**
     * Get the current context ID or the site-wide context ID (0) if no context
     * can be found.
     */
    public function getCurrentContextId()
    {
        $context = Application::get()->getRequest()->getContext();
        return is_null($context) ? 0 : $context->getId();
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\plugins\LazyLoadPlugin', '\LazyLoadPlugin');
}
