<?php

/**
 * @file classes/plugins/PluginRegistry.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PluginRegistry
 * @ingroup plugins
 * @see Plugin
 *
 * @brief Registry class for managing plugins.
 */

define('PLUGINS_PREFIX', 'plugins/');

class PluginRegistry {
	//
	// Public methods
	//
	/**
	 * Return all plugins in the given category as an array, or, if the
	 * category is not specified, all plugins in an associative array of
	 * arrays by category.
	 * @param $category String the name of the category to retrieve
	 * @return array
	 */
	static function &getPlugins($category = null) {
		$plugins =& Registry::get('plugins', true, []); // Reference necessary
		if ($category !== null) {
			if (!isset($plugins[$category])) $plugins[$category] = [];
			return $plugins[$category];
		}
		return $plugins;
	}

	/**
	 * Get all plugins in a single array.
	 * @return array
	 */
	static function getAllPlugins() {
		$plugins =& self::getPlugins();
		$allPlugins = [];
		if (!empty($plugins)) foreach ($plugins as $list) {
			if (is_array($list)) $allPlugins += $list;
		}
		return $allPlugins;
	}

	/**
	 * Register a plugin with the registry in the given category.
	 * @param $category String the name of the category to extend
	 * @param $plugin The instantiated plugin to add
	 * @param $path The path the plugin was found in
	 * @param $mainContextId integer To identify enabled plug-ins
	 *  we need a context. This context is usually taken from the
	 *  request but sometimes there is no context in the request
	 *  (e.g. when executing CLI commands). Then the main context
	 *  can be given as an explicit ID.
	 * @return boolean True IFF the plugin was registered successfully
	 */
	static function register($category, $plugin, $path, $mainContextId = null) {
		$pluginName = $plugin->getName();
		$plugins =& self::getPlugins();

		// Allow the plugin to register.
		if (!$plugin->register($category, $path, $mainContextId)) return false;

		// If the plugin was already loaded, do not load it again.
		if (isset($plugins[$category][$pluginName])) return false;

		if (isset($plugins[$category])) $plugins[$category][$pluginName] = $plugin;
		else $plugins[$category] = [$pluginName => $plugin];
		return true;
	}

	/**
	 * Get a plugin by name.
	 * @param $category String category name
	 * @param $name String plugin name
	 * @return Plugin?
	 */
	static function getPlugin($category, $name) {
		$plugins =& self::getPlugins();
		return $plugins[$category][$name]??null;
	}

	/**
	 * Load all plugins for a given category.
	 * @param $category string The name of the category to load
	 * @param $enabledOnly boolean if true load only enabled
	 *  plug-ins (db-installation required), otherwise look on
	 *  disk and load all available plug-ins (no db required).
	 * @param $mainContextId integer To identify enabled plug-ins
	 *  we need a context. This context is usually taken from the
	 *  request but sometimes there is no context in the request
	 *  (e.g. when executing CLI commands). Then the main context
	 *  can be given as an explicit ID.
	 * @return array Set of plugins, sorted in sequence.
	 */
	static function loadCategory ($category, $enabledOnly = false, $mainContextId = null) {
		$plugins = [];
		$categoryDir = PLUGINS_PREFIX . $category;
		if (!is_dir($categoryDir)) return $plugins;

		if ($enabledOnly && Config::getVar('general', 'installed')) {
			// Get enabled plug-ins from the database.
			$application = Application::get();
			$products = $application->getEnabledProducts('plugins.'.$category, $mainContextId);
			foreach ($products as $product) {
				$file = $product->getProduct();
				$plugin = self::_instantiatePlugin($category, $categoryDir, $file, $product->getProductClassname());
				if ($plugin instanceof Plugin) {
					$plugins[$plugin->getSeq()]["$categoryDir/$file"] = $plugin;
				}
			}
		} else {
			// Get all plug-ins from disk. This does not require
			// any database access and can therefore be used during
			// first-time installation.
			$handle = opendir($categoryDir);
			while (($file = readdir($handle)) !== false) {
				if ($file == '.' || $file == '..') continue;
				$plugin = self::_instantiatePlugin($category, $categoryDir, $file);
				if ($plugin && is_object($plugin)) {
					$plugins[$plugin->getSeq()]["$categoryDir/$file"] = $plugin;
				}
			}
			closedir($handle);
		}

		// Fire a hook prior to registering plugins for a category
		// n.b.: this should not be used from a PKPPlugin::register() call to "jump categories"
		HookRegistry::call('PluginRegistry::loadCategory', [&$category, &$plugins]);

		// Register the plugins in sequence.
		ksort($plugins);
		foreach ($plugins as $seq => $junk1) {
			foreach ($plugins[$seq] as $pluginPath => $junk2) {
				self::register($category, $plugins[$seq][$pluginPath], $pluginPath, $mainContextId);
			}
		}
		unset($plugins);

		// Return the list of successfully-registered plugins.
		$plugins =& self::getPlugins($category);

		// Fire a hook after all plugins of a category have been loaded, so they
		// are able to interact if required
		HookRegistry::call('PluginRegistry::categoryLoaded::' . $category, [&$plugins]);

		// Sort the plugins by priority before returning.
		uasort($plugins, function($a, $b) {
			return $a->getSeq() - $b->getSeq();
		});

		return $plugins;
	}

	/**
	 * Load a specific plugin from a category by path name.
	 * Similar to loadCategory, except that it only loads a single plugin
	 * within a category rather than loading all.
	 * @param $category string
	 * @param $pathName string
	 * @param $mainContextId integer To identify enabled plug-ins
	 *  we need a context. This context is usually taken from the
	 *  request but sometimes there is no context in the request
	 *  (e.g. when executing CLI commands). Then the main context
	 *  can be given as an explicit ID.
	 * @return Plugin?
	 */
	static function loadPlugin($category, $pathName, $mainContextId = null) {
		$pluginPath = PLUGINS_PREFIX . $category . '/' . $pathName;
		if (!is_dir($pluginPath) || !file_exists($pluginPath . '/index.php')) return null;

		$plugin = @include("$pluginPath/index.php");
		if (!is_object($plugin)) return null;

		self::register($category, $plugin, $pluginPath, $mainContextId);
		return $plugin;
	}

	/**
	 * Get a list of the various plugin categories available.
	 *
	 * NB: The categories are returned in the order in which they
	 * have to be registered and/or installed. Plug-ins in categories
	 * later in the list may depend on plug-ins in earlier
	 * categories.
	 *
	 * @return array
	 */
	static function getCategories() {
		$application = Application::get();
		$categories = $application->getPluginCategories();
		HookRegistry::call('PluginRegistry::getCategories', [&$categories]);
		return $categories;
	}

	/**
	 * Load all plugins in the system and return them in a single array.
	 * @param $enabledOnly boolean load only enabled plug-ins
	 * @return array Set of all plugins
	 */
	static function loadAllPlugins($enabledOnly = false) {
		// Retrieve and register categories (order is significant).
		foreach (self::getCategories() as $category) {
			self::loadCategory($category, $enabledOnly);
		}
		return self::getAllPlugins();
	}


	//
	// Private helper methods
	//
	/**
	 * Instantiate a plugin.
	 *
	 * This method can be called statically.
	 *
	 * @param $category string
	 * @param $categoryDir string
	 * @param $file string
	 * @param $classToCheck string set null to maintain pre-2.3.x backwards compatibility
	 * @return Plugin?
	 */
	static function _instantiatePlugin($category, $categoryDir, $file, $classToCheck = null) {
		if(!is_null($classToCheck) && !preg_match('/[a-zA-Z0-9]+/', $file)) throw new Exception('Invalid product name "'.$file.'"!');

		$pluginPath = "$categoryDir/$file";
		if (!is_dir($pluginPath)) return null;

		// Try the plug-in wrapper first for backwards
		// compatibility.
		$pluginWrapper = "$pluginPath/index.php";
		if (file_exists($pluginWrapper)) {
			$plugin = include($pluginWrapper);
			assert(is_a($plugin, $classToCheck ?: 'Plugin'));
			return $plugin;
		} else {
			// Try the well-known plug-in class name next.
			$pluginClassName = ucfirst($file).ucfirst($category).'Plugin';
			$pluginClassFile = $pluginClassName.'.inc.php';
			if (file_exists("$pluginPath/$pluginClassFile")) {
				// Try to instantiate the plug-in class.
				$pluginPackage = 'plugins.'.$category.'.'.$file;
				$plugin = instantiate($pluginPackage.'.'.$pluginClassName, $pluginClassName, $pluginPackage, 'register');
				assert(is_a($plugin, $classToCheck ?: 'Plugin'));
				return $plugin;
			}
		}
		return null;
	}
}

