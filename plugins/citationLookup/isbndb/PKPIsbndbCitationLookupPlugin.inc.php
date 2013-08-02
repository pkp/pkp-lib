<?php

/**
 * @defgroup plugins_citationLookup_isbndb ISBNDB Citation Lookup Plugin
 */

/**
 * @file plugins/citationLookup/isbndb/PKPIsbndbCitationLookupPlugin.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPIsbndbCitationLookupPlugin
 * @ingroup plugins_citationLookup_isbndb
 *
 * @brief Cross-application ISBNdb citation lookup plugin
 */


import('classes.plugins.Plugin');

class PKPIsbndbCitationLookupPlugin extends Plugin {
	/**
	 * Constructor
	 */
	function PKPIsbndbCitationLookupPlugin() {
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
		return 'IsbndbCitationLookupPlugin';
	}

	/**
	 * @copydoc PKPPlugin::getDisplayName()
	 */
	function getDisplayName() {
		return __('plugins.citationLookup.isbndb.displayName');
	}

	/**
	 * @copydoc PKPPlugin::getDescription()
	 */
	function getDescription() {
		return __('plugins.citationLookup.isbndb.description');
	}
}

?>
