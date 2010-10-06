<?php

/**
 * @defgroup plugins_citationOutput_apa
 */

/**
 * @file plugins/citationOutput/apa/PKPApaCitationOutputPlugin.inc.php
 *
 * Copyright (c) 2003-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPApaCitationOutputPlugin
 * @ingroup plugins_citationOutput_apa
 *
 * @brief Cross-application APA citation style plugin
 */


import('classes.plugins.Plugin');

class PKPApaCitationOutputPlugin extends Plugin {
	/**
	 * Constructor
	 */
	function PKPApaCitationOutputPlugin() {
		parent::Plugin();
	}


	//
	// Override protected template methods from PKPPlugin
	//
	/**
	 * @see PKPPlugin::getName()
	 */
	function getName() {
		return 'ApaCitationOutputPlugin';
	}

	/**
	 * @see PKPPlugin::getDisplayName()
	 */
	function getDisplayName() {
		return Locale::translate('plugins.citationOutput.apa.displayName');
	}

	/**
	 * @see PKPPlugin::getDescription()
	 */
	function getDescription() {
		return Locale::translate('plugins.citationOutput.apa.description');
	}
}

?>
