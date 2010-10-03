<?php

/**
 * @defgroup plugins_metadata_openurl10
 */

/**
 * @file plugins/metadata/openurl10/PKPOpenUrl10MetadataPlugin.inc.php
 *
 * Copyright (c) 2003-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPOpenUrl10MetadataPlugin
 * @ingroup plugins_metadata_openurl10
 *
 * @brief Abstract base class for OpenURL 1.0 metadata plugins
 */


import('lib.pkp.classes.plugins.MetadataPlugin');

class PKPMods34MetadataPlugin extends MetadataPlugin {
	/**
	 * @see PKPPlugin::getName()
	 */
	function getName() {
		return 'Mods34MetadataPlugin';
	}

	/**
	 * @see PKPPlugin::getDisplayName()
	 */
	function getDisplayName() {
		return Locale::translate('plugins.metadata.mods34.displayName');
	}

	/**
	 * @see PKPPlugin::getDescription()
	 */
	function getDescription() {
		return Locale::translate('plugins.metadata.mods34.description');
	}
}

?>
