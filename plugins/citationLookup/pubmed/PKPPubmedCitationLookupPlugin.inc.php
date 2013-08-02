<?php

/**
 * @defgroup plugins_citationLookup_pubmed PubMed Citation Lookup Plugin
 */

/**
 * @file plugins/citationLookup/pubmed/PKPPubmedCitationLookupPlugin.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPPubmedCitationLookupPlugin
 * @ingroup plugins_citationLookup_pubmed
 *
 * @brief Cross-application PubMed citation lookup plugin
 */


import('classes.plugins.Plugin');

class PKPPubmedCitationLookupPlugin extends Plugin {
	/**
	 * Constructor
	 */
	function PKPPubmedCitationLookupPlugin() {
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
		return 'PubmedCitationLookupPlugin';
	}

	/**
	 * @copydoc PKPPlugin::getDisplayName()
	 */
	function getDisplayName() {
		return __('plugins.citationLookup.pubmed.displayName');
	}

	/**
	 * @copydoc PKPPlugin::getDescription()
	 */
	function getDescription() {
		return __('plugins.citationLookup.pubmed.description');
	}
}

?>
