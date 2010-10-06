<?php

/**
 * @defgroup plugins_citationOutput_abnt
 */

/**
 * @file plugins/citationOutput/abnt/PKPAbntCitationOutputPlugin.inc.php
 *
 * Copyright (c) 2003-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPAbntCitationOutputPlugin
 * @ingroup plugins_citationOutput_abnt
 *
 * @brief Cross-application ABNT citation style plugin
 */


import('classes.plugins.Plugin');

class PKPAbntCitationOutputPlugin extends Plugin {
	/**
	 * Constructor
	 */
	function PKPAbntCitationOutputPlugin() {
		parent::Plugin();
	}


	//
	// Override protected template methods from PKPPlugin
	//
	/**
	 * @see PKPPlugin::getName()
	 */
	function getName() {
		return 'AbntCitationOutputPlugin';
	}

	/**
	 * @see PKPPlugin::getDisplayName()
	 */
	function getDisplayName() {
		return Locale::translate('plugins.citationOutput.abnt.displayName');
	}

	/**
	 * @see PKPPlugin::getDescription()
	 */
	function getDescription() {
		return Locale::translate('plugins.citationOutput.abnt.description');
	}
}

?>
