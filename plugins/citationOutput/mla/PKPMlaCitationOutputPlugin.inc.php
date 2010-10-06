<?php

/**
 * @defgroup plugins_citationOutput_mla
 */

/**
 * @file plugins/citationOutput/mla/PKPMlaCitationOutputPlugin.inc.php
 *
 * Copyright (c) 2003-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPMlaCitationOutputPlugin
 * @ingroup plugins_citationOutput_mla
 *
 * @brief Cross-application MLA citation style plugin
 */


import('classes.plugins.Plugin');

class PKPMlaCitationOutputPlugin extends Plugin {
	/**
	 * Constructor
	 */
	function PKPMlaCitationOutputPlugin() {
		parent::Plugin();
	}


	//
	// Override protected template methods from PKPPlugin
	//
	/**
	 * @see PKPPlugin::getName()
	 */
	function getName() {
		return 'MlaCitationOutputPlugin';
	}

	/**
	 * @see PKPPlugin::getDisplayName()
	 */
	function getDisplayName() {
		return Locale::translate('plugins.citationOutput.mla.displayName');
	}

	/**
	 * @see PKPPlugin::getDescription()
	 */
	function getDescription() {
		return Locale::translate('plugins.citationOutput.mla.description');
	}
}

?>
