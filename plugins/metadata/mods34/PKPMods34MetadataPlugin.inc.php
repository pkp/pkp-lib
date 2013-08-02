<?php
/**
 * @defgroup plugins_metadata_mods34 MODS 3.4 Metadata Plugin
 */

/**
 * @file plugins/metadata/mods34/PKPMods34MetadataPlugin.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPMods34MetadataPlugin
 * @ingroup plugins_metadata_mods34
 *
 * @brief Abstract base class for MODS metadata plugins
 */


import('lib.pkp.classes.plugins.MetadataPlugin');

class PKPMods34MetadataPlugin extends MetadataPlugin {
	/**
	 * Constructor
	 */
	function PKPMods34MetadataPlugin() {
		parent::MetadataPlugin();
	}


	//
	// Override protected template methods from PKPPlugin
	//
	/**
	 * @copydoc PKPPlugin::getName()
	 */
	function getName() {
		return 'Mods34MetadataPlugin';
	}

	/**
	 * @copydoc PKPPlugin::getDisplayName()
	 */
	function getDisplayName() {
		return __('plugins.metadata.mods34.displayName');
	}

	/**
	 * @copydoc PKPPlugin::getDescription()
	 */
	function getDescription() {
		return __('plugins.metadata.mods34.description');
	}
}

?>
