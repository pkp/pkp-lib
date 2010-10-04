<?php

/**
 * @defgroup plugins_citationLookup_worldcat
 */

/**
 * @file plugins/citationLookup/worldcat/PKPWorldcatCitationLookupPlugin.inc.php
 *
 * Copyright (c) 2003-2010 John Willinsky
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
	 * @see PKPPlugin::getName()
	 */
	function getName() {
		return 'WorldcatCitationLookupPlugin';
	}

	/**
	 * @see PKPPlugin::getDisplayName()
	 */
	function getDisplayName() {
		return Locale::translate('plugins.citationLookup.worldcat.displayName');
	}

	/**
	 * @see PKPPlugin::getDescription()
	 */
	function getDescription() {
		return Locale::translate('plugins.citationLookup.worldcat.description');
	}
}

?>
