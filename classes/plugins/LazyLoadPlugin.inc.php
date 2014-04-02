<?php

/**
 * @file classes/plugins/CachedPlugin.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CachedPlugin
 * @ingroup plugins
 *
 * @brief Abstract class for plugins that optionally
 * support lazy load.
 */

import('classes.plugins.Plugin');

class LazyLoadPlugin extends Plugin {
	/**
	 * Constructor
	 */
	function LazyLoadPlugin() {
		parent::Plugin();
	}

	/*
	 * Override public methods from PKPPlugin
	 */
	/**
	 * Extends the definition of PKPPlugin's register()
	 * method to support lazy load.
	 *
	 * @see PKPPlugin::register()
	 *
	 * @param lazyLoad
	 */
	function register($category, $path) {
		$success = parent::register($category, $path);
		if ($success) {
			$this->addLocaleData();
		}
		return $success;
	}

	/*
	 * Override protected methods from PKPPlugin
	 */
	/**
	 * @see PKPPlugin::getName()
	 */
	function getName() {
		// Lazy load enabled plug-ins always use the plugin's class name
		// as plug-in name. Legacy plug-ins will override this method so
		// this implementation is backwards compatible.
		// NB: strtolower is required for PHP4 compatibility.
		return strtolower_codesafe(get_class($this));
	}

	/*
	 * Protected methods required to support lazy load.
	 */
	/**
	 * Determine whether or not this plugin is currently enabled.
	 *
	 * @return boolean
	 */
	function getEnabled() {
		return $this->getContextSpecificSetting($this->getSettingMainContext(), 'enabled');
	}

	/**
	 * Set whether or not this plugin is currently enabled.
	 *
	 * @param $enabled boolean
	 */
	function setEnabled($enabled) {
		return $this->updateContextSpecificSetting($this->getSettingMainContext(), 'enabled', $enabled, 'bool');
	}
}

?>
