<?php

/**
 * @defgroup plugins_metadata_dc11
 */

/**
 * @file plugins/metadata/dc11/PKPDc11MetadataPlugin.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPDc11MetadataPlugin
 * @ingroup plugins_metadata_dc11
 *
 * @brief Abstract base class for Dublin Core version 1.1 metadata plugins
 */


import('lib.pkp.classes.plugins.MetadataPlugin');

class PKPDc11MetadataPlugin extends MetadataPlugin {
	/**
	 * Constructor
	 */
	function PKPDc11MetadataPlugin() {
		parent::MetadataPlugin();
	}


	//
	// Override protected template methods from PKPPlugin
	//
	/**
	 * @see PKPPlugin::getName()
	 */
	function getName() {
		return 'Dc11MetadataPlugin';
	}

	/**
	 * @see PKPPlugin::getDisplayName()
	 */
	function getDisplayName() {
		return __('plugins.metadata.dc11.displayName');
	}

	/**
	 * @see PKPPlugin::getDescription()
	 */
	function getDescription() {
		return __('plugins.metadata.dc11.description');
	}
}

?>
