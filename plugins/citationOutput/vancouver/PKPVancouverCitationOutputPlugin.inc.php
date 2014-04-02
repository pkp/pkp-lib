<?php

/**
 * @defgroup plugins_citationOutput_vancouver
 */

/**
 * @file plugins/citationOutput/vancouver/PKPVancouverCitationOutputPlugin.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPVancouverCitationOutputPlugin
 * @ingroup plugins_citationOutput_vancouver
 *
 * @brief Cross-application Vancouver citation style plugin
 */


import('classes.plugins.Plugin');

class PKPVancouverCitationOutputPlugin extends Plugin {
	/**
	 * Constructor
	 */
	function PKPVancouverCitationOutputPlugin() {
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
		return 'VancouverCitationOutputPlugin';
	}

	/**
	 * @see PKPPlugin::getDisplayName()
	 */
	function getDisplayName() {
		return __('plugins.citationOutput.vancouver.displayName');
	}

	/**
	 * @see PKPPlugin::getDescription()
	 */
	function getDescription() {
		return __('plugins.citationOutput.vancouver.description');
	}
}

?>
