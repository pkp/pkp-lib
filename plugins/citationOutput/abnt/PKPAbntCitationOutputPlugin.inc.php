<?php
/**
 * @defgroup plugins_citationOutput_abnt ABNT Citation Format
 */

/**
 * @file plugins/citationOutput/abnt/PKPAbntCitationOutputPlugin.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPAbntCitationOutputPlugin
 * @ingroup plugins_citationOutput_abnt
 *
 * @brief Cross-application ABNT citation style plugin
 */


import('lib.pkp.classes.plugins.Plugin');

class PKPAbntCitationOutputPlugin extends Plugin {
	/**
	 * Constructor
	 */
	function PKPAbntCitationOutputPlugin() {
		parent::Plugin();
	}


	//
	// Override protected template methods from Plugin
	//
	/**
	 * @copydoc Plugin::register()
	 */
	function register($category, $path) {
		if (!parent::register($category, $path)) return false;
		$this->addLocaleData();
		return true;
	}

	/**
	 * @copydoc Plugin::getName()
	 */
	function getName() {
		return 'AbntCitationOutputPlugin';
	}

	/**
	 * @copydoc Plugin::getDisplayName()
	 */
	function getDisplayName() {
		return __('plugins.citationOutput.abnt.displayName');
	}

	/**
	 * @copydoc Plugin::getDescription()
	 */
	function getDescription() {
		return __('plugins.citationOutput.abnt.description');
	}
}

?>
