<?php

/**
 * @defgroup plugins_citationLookup_worldcat WorldCat Citation Lookup Plugin
 */

/**
 * @file plugins/citationLookup/worldcat/PKPWorldcatCitationLookupPlugin.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPWorldcatCitationLookupPlugin
 * @ingroup plugins_citationLookup_worldcat
 *
 * @brief Cross-application WorldCat citation lookup plugin
 */


import('classes.plugins.Plugin');

class PKPWorldcatCitationLookupPlugin extends Plugin {
	/**
	 * Constructor
	 */
	function PKPWorldcatCitationLookupPlugin() {
		parent::Plugin();
	}


	//
	// Override protected template methods from PKPPlugin
	//
	/**
	 * @copydoc PKPPlugin::register()
	 */
	function register($category, $path) {
		if (!parent::register($category, $path)) return false;
		$this->addLocaleData();
		return true;
	}

	/**
	 * @copydoc PKPPlugin::getName()
	 */
	function getName() {
		return 'WorldcatCitationLookupPlugin';
	}

	/**
	 * @copydoc PKPPlugin::getDisplayName()
	 */
	function getDisplayName() {
		return __('plugins.citationLookup.worldcat.displayName');
	}

	/**
	 * @copydoc PKPPlugin::getDescription()
	 */
	function getDescription() {
		return __('plugins.citationLookup.worldcat.description');
	}
}

?>
