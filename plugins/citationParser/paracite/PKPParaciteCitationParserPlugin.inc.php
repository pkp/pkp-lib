<?php

/**
 * @defgroup plugins_citationParser_paracite
 */

/**
 * @file plugins/citationParser/paracite/PKPParaciteCitationParserPlugin.inc.php
 *
 * Copyright (c) 2003-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPParaciteCitationParserPlugin
 * @ingroup plugins_citationParser_paracite
 *
 * @brief Cross-application ParaCite citation parser
 */


import('classes.plugins.Plugin');

class PKPParaciteCitationParserPlugin extends Plugin {
	/**
	 * Constructor
	 */
	function PKPParaciteCitationParserPlugin() {
		parent::Plugin();
	}


	//
	// Override protected template methods from PKPPlugin
	//
	/**
	 * @see PKPPlugin::getName()
	 */
	function getName() {
		return 'ParaciteCitationParserPlugin';
	}

	/**
	 * @see PKPPlugin::getDisplayName()
	 */
	function getDisplayName() {
		return Locale::translate('plugins.citationParser.paracite.displayName');
	}

	/**
	 * @see PKPPlugin::getDescription()
	 */
	function getDescription() {
		return Locale::translate('plugins.citationParser.paracite.description');
	}
}

?>
