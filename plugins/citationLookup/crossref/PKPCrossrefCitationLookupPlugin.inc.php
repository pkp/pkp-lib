<?php

/**
 * @defgroup plugins_citationLookup_crossref
 */

/**
 * @file plugins/citationLookup/crossref/PKPCrossrefCitationLookupPlugin.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPCrossrefCitationLookupPlugin
 * @ingroup plugins_citationLookup_crossref
 *
 * @brief Cross-application CrossRef citation lookup plugin
 */


import('classes.plugins.Plugin');

class PKPCrossrefCitationLookupPlugin extends Plugin {
	/**
	 * Constructor
	 */
	function PKPCrossrefCitationLookupPlugin() {
		parent::Plugin();
	}


	//
	// Override protected template methods from PKPPlugin
	//
	/**
	 * @see PKPPlugin::register()
	 */
	function register($category, $path) {
		if (!parent::register($category, $path)) return false;
		$this->addLocaleData();
		return true;
	}

	/**
	 * @see PKPPlugin::getName()
	 */
	function getName() {
		return 'CrossrefCitationLookupPlugin';
	}

	/**
	 * @see PKPPlugin::getDisplayName()
	 */
	function getDisplayName() {
		return __('plugins.citationLookup.crossref.displayName');
	}

	/**
	 * @see PKPPlugin::getDescription()
	 */
	function getDescription() {
		return __('plugins.citationLookup.crossref.description');
	}
}

?>
