<?php
/**
 * @defgroup plugins_citationLookup_crossref CrossRef Citation Lookup Plugin
 */

/**
 * @file plugins/citationLookup/crossref/PKPCrossrefCitationLookupPlugin.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
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
		return 'CrossrefCitationLookupPlugin';
	}

	/**
	 * @copydoc PKPPlugin::getDisplayName()
	 */
	function getDisplayName() {
		return __('plugins.citationLookup.crossref.displayName');
	}

	/**
	 * @copydoc PKPPlugin::getDescription()
	 */
	function getDescription() {
		return __('plugins.citationLookup.crossref.description');
	}
}

?>
