<?php

/**
 * @file classes/plugins/LazyLoadPlugin.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CachedPlugin
 * @ingroup plugins
 *
 * @brief Abstract class for plugins that optionally
 * support lazy load.
 */

import('lib.pkp.classes.plugins.Plugin');

abstract class LazyLoadPlugin extends Plugin {

	//
	// Override public methods from Plugin
	//
	/**
	 * @copydoc Plugin::register()
	 */
	function register($category, $path, $mainContextId = null) {
		if (!parent::register($category, $path, $mainContextId)) return false;
		$this->addLocaleData();
		return true;
	}


	//
	// Override protected methods from Plugin
	//
	/**
	 * @see Plugin::getName()
	 */
	function getName() {
		// Lazy load enabled plug-ins always use the plugin's class name
		// as plug-in name. Legacy plug-ins will override this method so
		// this implementation is backwards compatible.
		// NB: strtolower was required for PHP4 compatibility.
		return strtolower_codesafe(get_class($this));
	}


	//
	// Public methods required to support lazy load.
	//
	/**
	 * Determine whether or not this plugin is currently enabled.
	 * @param $contextId integer To identify if the plugin is enabled
	 *  we need a context. This context is usually taken from the
	 *  request but sometimes there is no context in the request
	 *  (e.g. when executing CLI commands). Then the main context
	 *  can be given as an explicit ID.
	 * @return boolean
	 */
	function getEnabled($contextId = null) {
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
	 * @param $enabled boolean
	 */
	function setEnabled($enabled) {
		$contextId = $this->getCurrentContextId();
		if ($this->isSitePlugin()) {
			$contextId = 0;
		}
		$this->updateSetting($contextId, 'enabled', $enabled, 'bool');
	}

	/**
	 * @copydoc Plugin::getCanEnable()
	 */
	function getCanEnable() {
		return true;
	}

	/**
	 * @copydoc Plugin::getCanDisable()
	 */
	function getCanDisable() {
		return true;
	}

	/**
	 * Get the current context ID or the site-wide context ID (0) if no context
	 * can be found.
	 */
	function getCurrentContextId() {
		$context = PKPApplication::getRequest()->getContext();
		return is_null($context) ? 0 : $context->getId();
	}
}

?>
