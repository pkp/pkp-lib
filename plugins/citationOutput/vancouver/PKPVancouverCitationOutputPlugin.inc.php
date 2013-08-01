<?php
/**
 * @defgroup plugins_citationOutput_vancouver
 */

/**
 * @file plugins/citationOutput/vancouver/PKPVancouverCitationOutputPlugin.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
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
	 * @copydoc PKPPlugin::register()
	 */
	function register($category, $path) {
		if (!parent::register($category, $path)) return false;
		$this->addLocaleData();
		return true;
	}

	/**
	 * @copydoc PKPPlugin::getName()
	 */
	function getName() {
		return 'VancouverCitationOutputPlugin';
	}

	/**
	 * @copydoc PKPPlugin::getDisplayName()
	 */
	function getDisplayName() {
		return __('plugins.citationOutput.vancouver.displayName');
	}

	/**
	 * @copydoc PKPPlugin::getDescription()
	 */
	function getDescription() {
		return __('plugins.citationOutput.vancouver.description');
	}
}

?>
