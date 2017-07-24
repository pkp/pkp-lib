<?php
/**
 * @defgroup plugins_metadata_mods34 MODS 3.4 Metadata Plugin
 */

/**
 * @file plugins/metadata/mods34/PKPMods34MetadataPlugin.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPMods34MetadataPlugin
 * @ingroup plugins_metadata_mods34
 *
 * @brief Abstract base class for MODS metadata plugins
 */


import('lib.pkp.classes.plugins.MetadataPlugin');

class PKPMods34MetadataPlugin extends MetadataPlugin {

	//
	// Override protected template methods from Plugin
	//
	/**
	 * @copydoc Plugin::getName()
	 */
	function getName() {
		return 'Mods34MetadataPlugin';
	}

	/**
	 * @copydoc Plugin::getDisplayName()
	 */
	function getDisplayName() {
		return __('plugins.metadata.mods34.displayName');
	}

	/**
	 * @copydoc Plugin::getDescription()
	 */
	function getDescription() {
		return __('plugins.metadata.mods34.description');
	}

	/**
	 * Get a unique id for this metadata format
	 *
	 * @return string
	 */
	public function getFormatId() {
		return 'mods34';
	}

	/**
	 * Instantiate and return the schema object for this metadata format
	 *
	 * @return mixed
	 */
	public function getSchemaObject() {
		import('plugins.metadata.mods34.schema.Mods34Schema');
		return new Mods34Schema();
	}
}

?>
