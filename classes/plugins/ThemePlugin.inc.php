<?php

/**
 * @file classes/plugins/ThemePlugin.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ThemePlugin
 * @ingroup plugins
 *
 * @brief Abstract class for theme plugins
 */

import('lib.pkp.classes.plugins.LazyLoadPlugin');

class ThemePlugin extends LazyLoadPlugin {
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
		if ($result && $this->getEnabled() && !defined('SESSION_DISABLE_INIT')) {
			HookRegistry::register('PageHandler::displayCss', array($this, '_displayCssCallback'));
			$templateManager = TemplateManager::getManager($request);
			$dispatcher = $request->getDispatcher();
			$templateManager->addStyleSheet($dispatcher->url($request, ROUTE_COMPONENT, null, 'page.PageHandler', 'css', null, array('name' => $this->getName())), STYLE_SEQUENCE_LAST);
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
	 * Get the compiled CSS cache filename
	 * @return string|null
	 */
	function getStyleCacheFilename() {
		// Only relevant if Less compilation is used; otherwise return null.
		if ($this->getLessStylesheet() === null) return null;

		return 'compiled-' . $this->getName() . '.css';
	}

	/**
	 * Called as a callback upon stylesheet compilation.
	 * Used to inject this theme's styles.
	 */
	function _displayCssCallback($hookName, $args) {
		$request = $args[0];
		$stylesheetName = $args[1];
		$result =& $args[2];
		$lastModified =& $args[3];

		// Ensure the callback is for this plugin before intervening
		if ($stylesheetName != $this->getName()) return false;

		if ($this->getLessStylesheet()) {
			$cacheDirectory = CacheManager::getFileCachePath();
			$cacheFilename = $this->getStyleCacheFilename();
			$lessFile = $this->getPluginPath() . '/' . $this->getLessStylesheet();
			$compiledStylesheetFile = $cacheDirectory . '/' . $cacheFilename;

			if ($cacheFilename === null || !file_exists($compiledStylesheetFile)) {
				// Need to recompile, so flag last modified.
				$lastModified = time();

				// Compile this theme's styles
				require_once('lib/pkp/lib/lessphp/lessc.inc.php');
				$less = new lessc($lessFile);
				$less->importDir = $this->getPluginPath(); // @see PKPTemplateManager::compileStylesheet
				$themeStyles = $less->parse();
				$compiledStyles = str_replace('{$baseUrl}', $request->getBaseUrl(), $themeStyles);

				// Give other plugins the chance to intervene
				HookRegistry::call('ThemePlugin::compileCss', array($request, $less, &$compiledStylesheetFile, &$compiledStyles));

				if ($cacheFilename === null || file_put_contents($compiledStylesheetFile, $compiledStyles) === false) {
					// If the stylesheet cache can't be written, log the error and
					// output the compiled styles directly without caching.
					error_log("Unable to write \"$compiledStylesheetFile\".");
					$result .= $compiledStyles;
					return false;
				}
			} else {
				// We were able to fall back on a previously compiled file. Set lastModified.
				$cacheLastModified = filemtime($compiledStylesheetFile);
				$lastModified = $lastModified===null?
					$cacheLastModified:
					min($lastModified, $cacheLastModified);
			}

			// Add the compiled styles to the rest
			$result .= "\n" . file_get_contents($compiledStylesheetFile);
		}
		return false;
	}
}

?>
