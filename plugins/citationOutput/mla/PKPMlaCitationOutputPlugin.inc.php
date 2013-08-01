<?php
/**
 * @defgroup plugins_citationOutput_mla MLA Citation Format
 */

/**
 * @file plugins/citationOutput/mla/PKPMlaCitationOutputPlugin.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
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
		return 'MlaCitationOutputPlugin';
	}

	/**
	 * @copydoc PKPPlugin::getDisplayName()
	 */
	function getDisplayName() {
		return __('plugins.citationOutput.mla.displayName');
	}

	/**
	 * @copydoc PKPPlugin::getDescription()
	 */
	function getDescription() {
		return __('plugins.citationOutput.mla.description');
	}
}

?>
