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
 *
 * @see Plugin
 *
 * @brief Registry class for managing plugins.
 */

namespace PKP\plugins;

define('PLUGINS_PREFIX', 'plugins/');

use APP\core\Application;
use Exception;
use PKP\config\Config;

use PKP\core\Registry;

class PluginRegistry
{
    //
    // Public methods
    //
    /**
     * Return all plugins in the given category as an array, or, if the
     * category is not specified, all plugins in an associative array of
     * arrays by category.
     *
     * @param string $category the name of the category to retrieve
     *
     * @return array
     */
    public static function &getPlugins($category = null)
    {
        $plugins = & Registry::get('plugins', true, []); // Reference necessary
        if ($category !== null) {
            if (!isset($plugins[$category])) {
                $plugins[$category] = [];
            }
            return $plugins[$category];
        }
        return $plugins;
    }

    /**
     * Get all plugins in a single array.
     *
     * @return array
     */
    public static function getAllPlugins()
    {
        $plugins = & self::getPlugins();
        $allPlugins = [];
        if (!empty($plugins)) {
            foreach ($plugins as $list) {
                if (is_array($list)) {
                    $allPlugins += $list;
                }
            }
        }
        return $allPlugins;
    }

    /**
     * Register a plugin with the registry in the given category.
     *
     * @param string $category the name of the category to extend
     * @param Plugin $plugin The instantiated plugin to add
     * @param string $path The path the plugin was found in
     * @param int $mainContextId To identify enabled plug-ins
     *  we need a context. This context is usually taken from the
     *  request but sometimes there is no context in the request
     *  (e.g. when executing CLI commands). Then the main context
     *  can be given as an explicit ID.
     *
     * @return bool True IFF the plugin was registered successfully
     */
    public static function register($category, $plugin, $path, $mainContextId = null)
    {
        $pluginName = $plugin->getName();
        $plugins = & self::getPlugins();

        // If the plugin is already loaded or failed/refused to register
        if (isset($plugins[$category][$pluginName]) || !$plugin->register($category, $path, $mainContextId)) {
            return false;
        }

        $plugins[$category][$pluginName] = $plugin;
        return true;
    }

    /**
     * Get a plugin by name.
     *
     * @param string $category category name
     * @param string $name plugin name
     *
     * @return Plugin?
     */
    public static function getPlugin($category, $name)
    {
        $plugins = & self::getPlugins();
        return $plugins[$category][$name] ?? null;
    }

    /**
     * Load all plugins for a given category.
     *
     * @param string $category The name of the category to load
     * @param bool $enabledOnly if true load only enabled
     *  plug-ins (db-installation required), otherwise look on
     *  disk and load all available plug-ins (no db required).
     * @param int $mainContextId To identify enabled plug-ins
     *  we need a context. This context is usually taken from the
     *  request but sometimes there is no context in the request
     *  (e.g. when executing CLI commands). Then the main context
     *  can be given as an explicit ID.
     *
     * @return array Set of plugins, sorted in sequence.
     */
    public static function loadCategory($category, $enabledOnly = false, $mainContextId = null)
    {
        $plugins = [];
        $categoryDir = PLUGINS_PREFIX . $category;
        if (!is_dir($categoryDir)) {
            return $plugins;
        }

        if ($enabledOnly && Application::isInstalled()) {
            // Get enabled plug-ins from the database.
            $application = Application::get();
            $products = $application->getEnabledProducts('plugins.' . $category, $mainContextId);
            foreach ($products as $product) {
                $file = $product->getProduct();
                $plugin = self::_instantiatePlugin($category, $categoryDir, $file, $product->getProductClassname());
                if ($plugin instanceof \PKP\plugins\Plugin) {
                    $plugins[$plugin->getSeq()]["${categoryDir}/${file}"] = $plugin;
                }
            }
        } else {
            // Get all plug-ins from disk. This does not require
            // any database access and can therefore be used during
            // first-time installation.
            $handle = opendir($categoryDir);
            while (($file = readdir($handle)) !== false) {
                if ($file == '.' || $file == '..') {
                    continue;
                }
                $plugin = self::_instantiatePlugin($category, $categoryDir, $file);
                if ($plugin && is_object($plugin)) {
                    $plugins[$plugin->getSeq()]["${categoryDir}/${file}"] = $plugin;
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
        $plugins = & self::getPlugins($category);

        // Fire a hook after all plugins of a category have been loaded, so they
        // are able to interact if required
        HookRegistry::call('PluginRegistry::categoryLoaded::' . $category, [&$plugins]);

        // Sort the plugins by priority before returning.
        uasort($plugins, function ($a, $b) {
            return $a->getSeq() - $b->getSeq();
        });

        return $plugins;
    }

    /**
     * Load a specific plugin from a category by path name.
     * Similar to loadCategory, except that it only loads a single plugin
     * within a category rather than loading all.
     *
     * @param string $category
     * @param string $pathName
     * @param int $mainContextId To identify enabled plug-ins
     *  we need a context. This context is usually taken from the
     *  request but sometimes there is no context in the request
     *  (e.g. when executing CLI commands). Then the main context
     *  can be given as an explicit ID.
     *
     * @return Plugin?
     */
    public static function loadPlugin($category, $pathName, $mainContextId = null)
    {
        $pluginPath = PLUGINS_PREFIX . $category . '/' . $pathName;
        if (!is_dir($pluginPath) || !file_exists($pluginPath . '/index.php')) {
            return null;
        }

        $plugin = @include("${pluginPath}/index.php");
        if (!is_object($plugin)) {
            return null;
        }

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
    public static function getCategories()
    {
        $application = Application::get();
        $categories = $application->getPluginCategories();
        HookRegistry::call('PluginRegistry::getCategories', [&$categories]);
        return $categories;
    }

    /**
     * Load all plugins in the system and return them in a single array.
     *
     * @param bool $enabledOnly load only enabled plug-ins
     *
     * @return array Set of all plugins
     */
    public static function loadAllPlugins($enabledOnly = false)
    {
        static $isLoaded;
        if (!$isLoaded) {
            // Retrieve and register categories (order is significant).
            foreach (self::getCategories() as $category) {
                self::loadCategory($category, $enabledOnly);
            }
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
     * @param string $category
     * @param string $categoryDir
     * @param string $file
     * @param string $classToCheck set null to maintain pre-2.3.x backwards compatibility
     *
     * @return Plugin?
     */
    public static function _instantiatePlugin($category, $categoryDir, $file, $classToCheck = null)
    {
        if (!is_null($classToCheck) && !preg_match('/[a-zA-Z0-9]+/', $file)) {
            throw new Exception('Invalid product name "' . $file . '"!');
        }

        $pluginPath = "${categoryDir}/${file}";
        if (!is_dir($pluginPath)) {
            return null;
        }

        // Try the plug-in wrapper for backwards compatibility. (DEPRECATED as of 3.4.0)
        $pluginWrapper = "${pluginPath}/index.php";
        if (file_exists($pluginWrapper)) {
            $plugin = include($pluginWrapper);
            assert(is_a($plugin, $classToCheck ?: '\PKP\plugins\Plugin'));
            return $plugin;
        } else {
            // First, try a namespaced class name matching the installation directory.
            $pluginClassName = '\\APP\\plugins\\' . $category . '\\' . $file . '\\' . ucfirst($file) . 'Plugin';
            if (class_exists($pluginClassName)) {
                return new $pluginClassName();
            }

            // Try the well-known plug-in class name next (deprecated; pre-namespacing).
            // (DEPRECATED as of 3.4.0.)
            $pluginClassName = ucfirst($file) . ucfirst($category) . 'Plugin';
            $pluginClassFile = $pluginClassName . '.inc.php';
            if (file_exists("${pluginPath}/${pluginClassFile}")) {
                // Try to instantiate the plug-in class.
                $pluginPackage = 'plugins.' . $category . '.' . $file;
                $plugin = instantiate($pluginPackage . '.' . $pluginClassName, $pluginClassName, $pluginPackage, 'register');
                assert(is_a($plugin, $classToCheck ?: '\PKP\plugins\Plugin'));
                return $plugin;
            }
        }
        return null;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\plugins\PluginRegistry', '\PluginRegistry');
}
