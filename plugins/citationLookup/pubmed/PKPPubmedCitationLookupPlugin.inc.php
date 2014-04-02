<?php

/**
 * @defgroup plugins_citationLookup_pubmed
 */

/**
 * @file plugins/citationLookup/pubmed/PKPPubmedCitationLookupPlugin.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
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
		return 'PubmedCitationLookupPlugin';
	}

	/**
	 * @see PKPPlugin::getDisplayName()
	 */
	function getDisplayName() {
		return __('plugins.citationLookup.pubmed.displayName');
	}

	/**
	 * @see PKPPlugin::getDescription()
	 */
	function getDescription() {
		return __('plugins.citationLookup.pubmed.description');
	}
}

?>
