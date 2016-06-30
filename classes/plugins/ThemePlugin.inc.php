<?php

/**
 * @file classes/plugins/ThemePlugin.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ThemePlugin
 * @ingroup plugins
 *
 * @brief Abstract class for theme plugins
 */

import('lib.pkp.classes.plugins.LazyLoadPlugin');

define('LESS_FILENAME_SUFFIX', '.less');

abstract class ThemePlugin extends LazyLoadPlugin {
	/**
	 * Collection of styles
	 *
	 * @see self::_registerStyles
	 * @param $styles array
	 */
	public $styles = array();

	/**
	 * Collection of scripts
	 *
	 * @see self::_registerScripts
	 * @param $scripts array
	 */
	public $scripts = array();

	/**
	 * Parent theme (optional)
	 *
	 * @param $parent ThemePlugin
	 */
	public $parent;

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
		if (!parent::register($category, $path)) return false;

		if (defined('SESSION_DISABLE_INIT')) {
			return false;
		}

		// Don't perform any futher operations if theme is not currently active
		if (!$this->isActive()) {
			return true;
		}

		// Fire an initialization method which themes should use to add
		// styles, scripts and fonts
		$this->init();

		$this->_registerTemplates();
		$this->_registerStyles();
		$this->_registerScripts();

		return true;
	}

	/**
	 * The primary method themes should use to add styles, scripts and fonts,
	 * or register hooks. This method is only fired for the currently active
	 * theme.
	 *
	 * @return null
	 */
	public abstract function init();

	/**
	 * Determine whether or not this plugin is currently active
	 *
	 * This only returns true if the theme is currently the selected theme
	 * in a given context. Use self::getEnabled() if you want to know if the
	 * theme is available for use on the site.
	 *
	 * @return boolean
	 */
	public function isActive() {
		$request = $this->getRequest();
		$context = $request->getContext();
		if (is_a($context, 'Context')) {
			$activeTheme = $context->getSetting('themePluginPath');
		} else {
			$site = $request->getSite();
			$activeTheme = $site->getSetting('themePluginPath');
		}

		return $activeTheme == basename($this->getPluginPath());
	}

	/**
	 * Add a stylesheet to load with this theme
	 *
	 * Style paths with a .less extension will be compiled and redirected to
	 * the compiled file.
	 *
	 * @param $name string A name for this stylesheet
	 * @param $style string The stylesheet. Should be a path relative to the
	 *   theme directory or, if the `inline` argument is included, style data to
	 *   be output.
	 * @param $args array Optional arguments hash. Supported args:
	 *   'context': Whether to load this on the `frontend` or `backend`.
	 *      default: `frontend`
	 *   'priority': Controls order in which styles are printed
	 *   'addLess': Additional LESS files to process before compiling. Array
	 *   `inline` bool Whether the $style value should be output directly as
	 *      style data.
	 */
	public function addStyle($name, $style, $args = array()) {

		// Pass a file path for LESS files
		if (substr($style, (strlen(LESS_FILENAME_SUFFIX) * -1)) === LESS_FILENAME_SUFFIX) {
			$args['style'] = $this->_getBaseDir($style);

		// Pass a URL for other files
		} elseif (empty($args['inline'])) {
			if (isset($args['baseUrl'])) {
				$args['style'] = $args['baseUrl'] . $style;
			} else {
				$args['style'] = $this->_getBaseUrl($style);
			}

		// Leave inlined styles alone
		} else {
			$args['style'] = $style;
		}

		// Generate file paths for any additional LESS files to compile with
		// this style
		if (isset($args['addLess'])) {
			foreach ($args['addLess'] as &$file) {
				$file = $this->_getBaseDir($file);
			}
		}

		$this->styles[$name] = $args;
	}

	/**
	 * Modify the params of an existing stylesheet
	 *
	 * @param $name string The name of the stylesheet to modify
	 * @param $args array Parameters to modify.
	 * @see self::addStyle()
	 * @return null
	 */
	public function modifyStyle($name, $args = array()) {

		$style = &$this->getStyle($name);

		if (empty($style)) {
			return;
		}

		if (isset($args['addLess'])) {
			foreach ($args['addLess'] as &$file) {
				$file = $this->_getBaseDir($file);
			}
		}

		if (isset($args['style']) && !isset($args['inline'])) {
			$args['style'] = substr($args['style'], (strlen(LESS_FILENAME_SUFFIX) * -1)) == LESS_FILENAME_SUFFIX ? $this->_getBaseDir($args['style']) : $this->_getBaseUrl($args['style']);
		}

		$style = array_merge($style, $args);
	}

	/**
	 * Get a style from this theme or any parent theme
	 *
	 * @param $name string The name of the style to retrieve
	 * @return array|null Reference to the style or null if not found
	 */
	public function &getStyle($name) {

		// Search this theme
		if (isset($this->styles[$name])) {
			$style = &$this->styles[$name];
			return $style;
		}

		// If no parent theme, no style was found
		if (!isset($this->parent)) {
			return;
		}

		return $this->parent->getStyle($name);
	}

	/**
	 * Add a script to load with this theme
	 *
	 * @param $name string A name for this script
	 * @param $script string The script to be included. Should be path relative
	 *   to the theme or, if the `inline` argument is included, script data to
	 *   be output.
	 * @param $args array Optional arguments hash. Supported args:
	 *   `context` string Whether to load this on the `frontend` or `backend`.
	 *      default: frontend
	 *   `priority` int Controls order in which scripts are printed
	 *      default: STYLE_SEQUENCE_NORMAL
	 *   `inline` bool Whether the $script value should be output directly as
	 *      script data. Used to pass backend data to the scripts.
	 */
	public function addScript($name, $script, $args = array()) {

		if (!empty($args['inline'])) {
			$args['script'] = $script;
		} elseif (isset($args['baseUrl'])) {
			$args['script'] = $args['baseUrl'] . $script;
		} else {
			$args['script'] = $this->_getBaseUrl($script);
		}

		$this->scripts[$name] = $args;
	}

	/**
	 * Modify the params of an existing script
	 *
	 * @param $name string The name of the script to modify
	 * @param $args array Parameters to modify.
	 * @see self::addScript()
	 * @return null
	 */
	public function modifyScript($name, $args = array()) {

		$script = &$this->getScript($name);

		if (empty($script)) {
			return;
		}

		if (isset($args['path'])) {
			$args['path'] = $this->_getBaseUrl($args['path']);
		}

		$script = array_merge( $script, $args );
	}

	/**
	 * Get a script from this theme or any parent theme
	 *
	 * @param $name string The name of the script to retrieve
	 * @return array|null Reference to the script or null if not found
	 */
	public function &getScript($name) {

		// Search this theme
		if (isset($this->scripts[$name])) {
			$style = &$this->scripts[$name];
			return $style;
		}

		// If no parent theme, no script was found
		if (!isset($this->parent)) {
			return;
		}

		return $this->parent->getScript($name);
	}

	/**
	 * Set a parent theme for this theme
	 *
	 * @param $parent string Key in the plugin registry for the parent theme
	 * @return null
	 */
	public function setParent($parent) {

		$parent = PluginRegistry::getPlugin('themes', $parent);

		if (!is_a($parent, 'ThemePlugin')) {
			return;
		}

		$this->parent = &$parent;
		$this->parent->init();
	}

	/**
	 * Register directories to search for template files
	 *
	 * @return null
	 */
	private function _registerTemplates() {

		// Register parent theme template directory
		if (isset($this->parent) && is_a($this->parent, 'ThemePlugin')) {
			$this->parent->_registerTemplates();
		}

		// Register this theme's template directory
		$request = $this->getRequest();
		$templateManager = TemplateManager::getManager($request);
		array_unshift(
			$templateManager->template_dir,
			$this->_getBaseDir('templates')
		);
	}

	/**
	 * Register stylesheets and font assets
	 *
	 * Passes styles defined by the theme to the template manager for handling.
	 *
	 * @return null
	 */
	private function _registerStyles() {

		if (isset($this->parent)) {
			$this->parent->_registerStyles();
		}

		$request = $this->getRequest();
		$dispatcher = $request->getDispatcher();
		$templateManager = TemplateManager::getManager($request);

		foreach($this->styles as $name => $data) {

			if (empty($data['style'])) {
				continue;
			}

			// Compile LESS files
			if (substr($data['style'], (strlen(LESS_FILENAME_SUFFIX) * -1)) == LESS_FILENAME_SUFFIX) {
				$styles = $dispatcher->url(
					$request,
					ROUTE_COMPONENT,
					null,
					'page.PageHandler',
					'css',
					null,
					array(
						'name' => $name,
					)
				);
			} else {
				$styles = $data['style'];
			}

			unset($data['style']);

			$templateManager->addStylesheet($name, $styles, $data);
		}
	}

	/**
	 * Register script assets
	 *
	 * Passes scripts defined by the theme to the template manager for handling.
	 *
	 * @return null
	 */
	public function _registerScripts() {

		if (isset($this->parent)) {
			$this->parent->_registerScripts();
		}

		$request = $this->getRequest();
		$dispatcher = $request->getDispatcher();
		$templateManager = TemplateManager::getManager($request);

		foreach($this->scripts as $name => $data) {
			$script = $data['script'];
			unset($data['script']);
			$templateManager->addJavaScript($name, $script, $data);
		}
	}

	/**
	 * Get the base URL to be used for file paths
	 *
	 * A base URL for loading LESS/CSS/JS files in <link> elements. It will
	 * also be set to the @baseUrl variable before LESS files are compiloed so
	 * that images and fonts can be located.
	 *
	 * @param $path string An optional path to append to the base
	 * @return string
	 */
	public function _getBaseUrl($path = '') {
		$request = $this->getRequest();
		$path = empty($path) ? '' : DIRECTORY_SEPARATOR . $path;
		return $request->getBaseUrl() . DIRECTORY_SEPARATOR . $this->getPluginPath() . $path;
	}

	/**
	 * Get the base path to be used for file references
	 *
	 * @param $path string An optional path to append to the base
	 * @return string
	 */
	public function _getBaseDir($path = '') {
		$path = empty($path) ? '' : DIRECTORY_SEPARATOR . $path;
		return Core::getBaseDir() . DIRECTORY_SEPARATOR . $this->getPluginPath() . $path;
	}
}

?>
