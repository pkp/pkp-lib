<?php

/**
 * @defgroup plugins_metadata_nlm30
 */

/**
 * @file plugins/metadata/nlm30/PKPNlm30MetadataPlugin.inc.php
 *
 * Copyright (c) 2003-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPNlm30MetadataPlugin
 * @ingroup plugins_metadata_nlm30
 *
 * @brief Abstract base class for NLM 3.0 metadata plugins
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
