<?php

/**
 * @defgroup plugins_citationLookup_pubmed
 */

/**
 * @file plugins/citationLookup/pubmed/PKPPubmedCitationLookupPlugin.inc.php
 *
 * Copyright (c) 2003-2010 John Willinsky
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
	 * @see PKPPlugin::getName()
	 */
	function getName() {
		return 'PubmedCitationLookupPlugin';
	}

	/**
	 * @see PKPPlugin::getDisplayName()
	 */
	function getDisplayName() {
		return Locale::translate('plugins.citationLookup.pubmed.displayName');
	}

	/**
	 * @see PKPPlugin::getDescription()
	 */
	function getDescription() {
		return Locale::translate('plugins.citationLookup.pubmed.description');
	}
}

?>
