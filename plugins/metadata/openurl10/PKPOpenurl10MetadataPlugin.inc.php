<?php
/**
 * @defgroup plugins_metadata_openurl10 OpenURL 1.0 Metadata Plugin
 */

/**
 * @file plugins/metadata/openurl10/PKPOpenurl10MetadataPlugin.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPOpenurl10MetadataPlugin
 * @ingroup plugins_metadata_openurl10
 *
 * @brief Abstract base class for OpenURL 1.0 metadata plugins
 */


import('lib.pkp.classes.plugins.MetadataPlugin');

class PKPOpenurl10MetadataPlugin extends MetadataPlugin {

	//
	// Override protected template methods from Plugin
	//
	/**
	 * @copydoc Plugin::getName()
	 */
	function getName() {
		return 'Openurl10MetadataPlugin';
	}

	/**
	 * @copydoc Plugin::getDisplayName()
	 */
	function getDisplayName() {
		return __('plugins.metadata.openurl10.displayName');
	}

	/**
	 * @copydoc Plugin::getDescription()
	 */
	function getDescription() {
		return __('plugins.metadata.openurl10.description');
	}

	/**
	 * @copydoc MetadataPlugin::supportsFormat()
	 */
	public function supportsFormat($format) {
		return $format === 'openurl10book' || $format === 'openurl10dissertation' || $format === 'openurl10journal';
	}

	/**
	 * @copydoc MetadataPlugin::getSchemaObject()
	 */
	public function getSchemaObject($format) {
		assert($this->supportsFormat($format));
		if ($format === 'openurl10book') {
			import('lib.pkp.plugins.metadata.openurl10.schema.Openurl10BookSchema');
			return new Openurl10BookSchema();
		} elseif ($format === 'openurl10dissertation') {
			import('lib.pkp.plugins.metadata.openurl10.schema.Openurl10DissertationSchema');
			return new Openurl10DissertationSchema();
		} elseif ($format === 'openurl10journal') {
			import('lib.pkp.plugins.metadata.openurl10.schema.Openurl10JournalSchema');
			return new Openurl10JournalSchema();
		}
		assert(false);
	}
}


