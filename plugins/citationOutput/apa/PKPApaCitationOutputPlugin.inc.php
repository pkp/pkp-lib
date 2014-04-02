<?php

/**
 * @defgroup plugins_citationOutput_apa
 */

/**
 * @file plugins/citationOutput/apa/PKPApaCitationOutputPlugin.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
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
	 * @see PKPPlugin::register()
	 */
	function register($category, $path) {
		if (!parent::register($category, $path)) return false;
		$this->addLocaleData();
		return true;
	}

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
		return __('plugins.citationOutput.apa.displayName');
	}

	/**
	 * @see PKPPlugin::getDescription()
	 */
	function getDescription() {
		return __('plugins.citationOutput.apa.description');
	}
}

?>
