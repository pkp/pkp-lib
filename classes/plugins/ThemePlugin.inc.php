<?php

/**
 * @file classes/plugins/ThemePlugin.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ThemePlugin
 * @ingroup plugins
 *
 * @brief Abstract class for theme plugins
 */

import('lib.pkp.classes.plugins.LazyLoadPlugin');

class ThemePlugin extends LazyLoadPlugin {
	/** @var boolean True iff style inclusion is to be inhibited */
	var $_inhibitCompilation;

	/**
	 * Constructor
	 */
	function ThemePlugin() {
		parent::Plugin();
	}

	/**
	 * @copydoc Plugin::register
	 */
	function register($category, $path) {
		$this->pluginPath = $path;

		$result = parent::register($category, $path);

		$request = $this->getRequest();
		$context = $request->getContext();
		$pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
		if ($result) {
			$this->_inhibitCompilation = !$pluginSettingsDao->getSetting($context?$context->getId():CONTEXT_SITE, $this->getName(), 'enabled');
			HookRegistry::register('PKPTemplateManager::compileStylesheet', array($this, '_compileStylesheetCallback'));
		}
		return $result;
	}

	/**
	 * Get the display name of this plugin. This name is displayed on the
	 * Journal Manager's setup page 5, for example.
	 * @return String
	 */
	function getDisplayName() {
		assert(false); // Should always be overridden
	}

	/**
	 * Get a description of the plugin.
	 */
	function getDescription() {
		assert(false); // Should always be overridden
	}

	/**
	 * Get the filename to this theme's stylesheet, or null if none.
	 * @return string|null
	 */
	function getLessStylesheet() {
		return null;
	}

	/**
	 * Called as a callback upon stylesheet compilation.
	 * Used to inject this theme's styles.
	 */
	function _compileStylesheetCallback($hookName, $args) {
		$compiledStyles =& $args[0];

		if ($this->getLessStylesheet() && !$this->_inhibitCompilation) {
			// Compile this theme's styles
			$less = new lessc($this->getPluginPath() . '/' . $this->getLessStylesheet());
			$less->importDir = $this->getPluginPath(); // @see PKPTemplateManager::compileStylesheet
			$additionalStyles = $less->parse();

			// Add the compiled styles to the rest
			$compiledStyles .= "\n" . $additionalStyles;
		}

		return false;
	}

	/**
	 * Flag the theme for activation.
	 * @param $contextId int
	 */
	function flagActivation($contextId) {
		$this->_inhibitCompilation = false;
		$pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
		$pluginSettingsDao->updateSetting($contextId, $this->getName(), 'enabled', true);
	}

	/**
	 * Trigger a CSS recompile including this theme's style information
	 * @param $contextId int
	 */
	function activate($contextId) {
		$this->flagActivation($contextId);
		PKPTemplateManager::compileStylesheet($contextId);
	}

	/**
	 * Flag the theme for deactivation.
	 * @param $contextId int Context ID
	 */
	function flagDeactivation($contextId) {
		$this->_inhibitCompilation = true;
		$pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
		$pluginSettingsDao->updateSetting($contextId, $this->getName(), 'enabled', false);
	}

	/**
	 * Trigger a CSS recompile without this theme's style information.
	 * @param $contextId int
	 */
	function deactivate($contextId) {
		$this->flagDeactivation($contextId);
		PKPTemplateManager::compileStylesheet($contextId);
	}
}

?>
