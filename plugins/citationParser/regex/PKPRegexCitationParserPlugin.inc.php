<?php
/**
 * @defgroup plugins_citationParser_regex Regular Expression Citation Parser
 */

/**
 * @file plugins/citationParser/regex/PKPRegexCitationParserPlugin.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPRegexCitationParserPlugin
 * @ingroup plugins_citationParser_regex
 *
 * @brief Cross-application regular expression based citation parser
 */


import('classes.plugins.Plugin');

class PKPRegexCitationParserPlugin extends Plugin {
	/**
	 * Constructor
	 */
	function PKPRegexCitationParserPlugin() {
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
		return 'RegexCitationParserPlugin';
	}

	/**
	 * @copydoc PKPPlugin::getDisplayName()
	 */
	function getDisplayName() {
		return __('plugins.citationParser.regex.displayName');
	}

	/**
	 * @copydoc PKPPlugin::getDescription()
	 */
	function getDescription() {
		return __('plugins.citationParser.regex.description');
	}
}

?>
