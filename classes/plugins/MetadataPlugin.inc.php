<?php

/**
 * @file classes/plugins/MetadataPlugin.inc.php
 *
 * Copyright (c) 2003-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class MetadataPlugin
 * @ingroup plugins
 *
 * @brief Abstract class for metadata plugins
 */


import('classes.plugins.Plugin');

class MetadataPlugin extends Plugin {
	function MetadataPlugin() {
		parent::Plugin();
	}

	/**
	 * Get the metadata schema class names provided by this plug-in.
	 * @return array a list of fully qualified class names
	 */
	function getMetadataClassNames() {
		// must be implemented by sub-classes
		assert(false);
	}

	/**
	 * Get the metadata adapter class names provided by this plug-in.
	 * @return array a list of fully qualified class names
	 */
	function getMetadataAdapterNames() {
		// must be implemented by sub-classes
		assert(false);
	}
}

?>
