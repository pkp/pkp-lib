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
	 * @param $path string The path to this stylesheet, relative to the theme
	 * @param $args array Optional arguments hash. Supported args:
	 *   'context': Whether to load this on the `frontend` or `backend`.
	 *      default: `frontend`
	 *   'priority': Controls order in which styles are printed
	 *   'addLess': Additional LESS files to process before compiling. Array
	 */
	public function addStyle($name, $path, $args = array()) {

		// Pass a file path for LESS files
		if (substr($path, -4) == 'less' ) {
			$fullPath = $this->_getBaseDir($path);

		// Pass a URL for other files
		} else {
			if (isset($args['baseUrl'])) {
				$fullPath = $args['baseUrl'] . $path;
			} else {
				$fullPath = $this->_getBaseUrl($path);
			}
		}

		// Generate file paths for any additional LESS files to compile with
		// this style
		if (isset($args['addLess'])) {
			foreach ($args['addLess'] as &$file) {
				$file = $this->_getBaseDir($file);
			}
		}

		$this->styles[$name] = array(
			'path' => $fullPath,
			'context' => isset($args['context']) && $args['context'] == 'backend' ? 'backend' : 'frontend',
			'priority' => isset($args['priority']) ? (int) $args['priority'] : STYLE_SEQUENCE_NORMAL,
			'addLess' => isset($args['addLess']) ? (array) $args['addLess'] : array(),
			'baseUrl' => isset($args['baseUrl']) ? (string) $args['baseUrl'] : '',
		);
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

		if (isset($args['path'])) {
			$args['path'] = substr($args['path'], -4) == 'less' ? $this->_getBaseDir($path) : $this->_getBaseUrl($args['path']);
		}


		$style = array_merge( $style, $args );
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
	 * @param $path string The path to this script, relative to the theme
	 * @param $args array Optional arguments hash. Supported args:
	 *   string $context Whether to load this on the `frontend` or `backend`.
	 *      default: `frontend`
	 *   int $priority Controls order in which styles are printed
	 */
	public function addScript($name, $path, $args = array()) {

		$this->scripts[$name] = array(
			'path'     => isset($args['baseUrl']) ? $args['baseUrl'] . $path : $this->_getBaseUrl($path),
			'context'  => isset($args['context']) && $args['context'] == 'backend' ? 'backend' : 'frontend',
			'priority' => isset($args['priority']) ? (int) $args['priority'] : STYLE_SEQUENCE_NORMAL,
		);
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

		foreach($this->styles as $name => $style) {

			if (empty($style['path'])) {
				continue;
			}

			// Compile LESS files
			if (substr($style['path'], -4) == 'less') {
				$url = $dispatcher->url(
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
				$url = $style['path'];
			}

			$templateManager->addStylesheet(
				$url,
				$style['priority'],
				$style['context']
			);
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

		foreach($this->scripts as $name => $script) {
			$templateManager->addJavaScript(
				$script['path'],
				$script['priority'],
				$script['context']
			);
		}
	}

	/**
	 * Get the base URL to be used for file paths
	 *
	 * A base URL for loading LESS/CSS/JS files in <link> elements. It will
	 * also be set to the @base-url variable before LESS files are compiloed so
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
