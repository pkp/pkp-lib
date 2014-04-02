<?php

/**
 * @defgroup plugins_citationParser_freecite
 */

/**
 * @file plugins/citationParser/freecite/PKPFreeciteCitationParserPlugin.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPFreeciteCitationParserPlugin
 * @ingroup plugins_citationParser_freecite
 *
 * @brief Cross-application FreeCite citation parser
 */


import('classes.plugins.Plugin');

class PKPFreeciteCitationParserPlugin extends Plugin {
	/**
	 * Constructor
	 */
	function PKPFreeciteCitationParserPlugin() {
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
		return 'FreeciteCitationParserPlugin';
	}

	/**
	 * @see PKPPlugin::getDisplayName()
	 */
	function getDisplayName() {
		return __('plugins.citationParser.freecite.displayName');
	}

	/**
	 * @see PKPPlugin::getDescription()
	 */
	function getDescription() {
		return __('plugins.citationParser.freecite.description');
	}
}

?>
