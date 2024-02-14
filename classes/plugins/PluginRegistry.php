<?php

/**
 * @file classes/plugins/PluginRegistry.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PluginRegistry
 *
 * @ingroup plugins
 *
 * @see Plugin
 *
 * @brief Registry class for managing plugins.
 */

namespace PKP\plugins;

use APP\core\Application;
use Exception;
use FilesystemIterator;
use Illuminate\Support\Arr;
use PKP\core\Registry;
use ReflectionObject;

class PluginRegistry
{
    /** Base path of plugins */
    public const PLUGINS_PREFIX = 'plugins/';

    /**
     * Return all plugins in the given category as an array, or, if the
     * category is not specified, all plugins in an associative array of
     * arrays by category.
     */
    public static function &getPlugins(?string $category = null): array
    {
        $plugins = & Registry::get('plugins', true, []); // Reference necessary
        if ($category !== null) {
            $plugins[$category] ??= [];
            return $plugins[$category];
        }
        return $plugins;
    }

    /**
     * Get all plugins in a single array.
     */
    public static function getAllPlugins(): array
    {
        return array_reduce(static::getPlugins(), fn (array $output, array $pluginsByCategory) => $output += $pluginsByCategory, []);
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
    public static function register(string $category, Plugin $plugin, string $path, ?int $mainContextId = null): bool
    {
        $pluginName = $plugin->getName();
        $plugins = & static::getPlugins();

        // If the plugin is already loaded or failed/refused to register
        if (isset($plugins[$category][$pluginName]) || !$plugin->register($category, $path, $mainContextId)) {
            return false;
        }

        $plugins[$category][$pluginName] = $plugin;
        return true;
    }

    /**
     * Get a plugin by category and name.
     */
    public static function getPlugin(string $category, string $name): ?Plugin
    {
        return static::getPlugins()[$category][$name] ?? null;
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
     *
     * @hook PluginRegistry::loadCategory [[&$category, &$plugins]]
     */
    public static function loadCategory(string $category, bool $enabledOnly = false, ?int $mainContextId = null): array
    {
        static $cache;
        $key = implode("\0", func_get_args());
        $plugins = $cache[$key] ??= $enabledOnly && Application::isInstalled()
            ? static::_loadFromDatabase($category, $mainContextId)
            : static::_loadFromDisk($category);

        // Fire a hook prior to registering plugins for a category
        // n.b.: this should not be used from a PKPPlugin::register() call to "jump categories"
        Hook::call('PluginRegistry::loadCategory', [&$category, &$plugins]);

        // Register the plugins in sequence.
        ksort($plugins);
        array_walk_recursive($plugins, fn (Plugin $plugin, string $pluginPath) => static::register($category, $plugin, $pluginPath, $mainContextId));

        // Return the list of successfully-registered plugins.
        $plugins = & static::getPlugins($category);

        // Fire a hook after all plugins of a category have been loaded, so they
        // are able to interact if required
        Hook::call("PluginRegistry::categoryLoaded::{$category}", [&$plugins]);

        // Sort the plugins by priority before returning.
        uasort($plugins, fn (Plugin $a, Plugin $b) => $a->getSeq() - $b->getSeq());

        return $plugins;
    }

    /**
     * Load a specific plugin from a category by path name.
     * Similar to loadCategory, except that it only loads a single plugin
     * within a category rather than loading all.
     *
     * @param int $mainContextId To identify enabled plug-ins
     *  we need a context. This context is usually taken from the
     *  request but sometimes there is no context in the request
     *  (e.g. when executing CLI commands). Then the main context
     *  can be given as an explicit ID.
     */
    public static function loadPlugin(string $category, string $pluginName, ?int $mainContextId = null): ?Plugin
    {
        if ($plugin = static::_instantiatePlugin($category, $pluginName)) {
            static::register($category, $plugin, self::PLUGINS_PREFIX . "{$category}/{$pluginName}", $mainContextId);
        }
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
     * @hook PluginRegistry::getCategories [[&$categories]]
     */
    public static function getCategories(): array
    {
        $categories = Application::get()->getPluginCategories();
        Hook::call('PluginRegistry::getCategories', [&$categories]);
        return $categories;
    }

    /**
     * Load all plugins in the system and return them in a single array.
     */
    public static function loadAllPlugins(bool $enabledOnly = false): array
    {
        // Retrieve and register categories (order is significant).
        $categories = static::getCategories();
        return array_reduce($categories, fn (array $plugins, string $category) => $plugins + static::loadCategory($category, $enabledOnly), []);
    }

    /**
     * Instantiate a plugin.
     */
    private static function _instantiatePlugin(string $category, string $pluginName, ?string $classToCheck = null): ?Plugin
    {
        if (!preg_match('/^[a-z0-9]+$/i', $pluginName)) {
            throw new Exception("Invalid product name \"{$pluginName}\"");
        }

        // First, try a namespaced class name matching the installation directory.
        $pluginClassName = "\\APP\\plugins\\{$category}\\{$pluginName}\\" . ucfirst($pluginName) . 'Plugin';
        $plugin = class_exists($pluginClassName)
            ? new $pluginClassName()
            : static::_deprecatedInstantiatePlugin($category, $pluginName);

        $classToCheck = $classToCheck ?: Plugin::class;
        $isObject = is_object($plugin);
        // Complements $classToCheck with a namespace when needed
        if (!str_contains($classToCheck, '\\') && $isObject && ($reflection = new ReflectionObject($plugin))->inNamespace()) {
            $classToCheck = "{$reflection->getNamespaceName()}\\{$classToCheck}";
        }
        if ($plugin !== null && !($plugin instanceof $classToCheck)) {
            $type = $isObject ? $plugin::class : gettype($plugin);
            error_log(new Exception("Plugin {$pluginName} expected to inherit from {$classToCheck}, actual type {$type}"));
            return null;
        }
        return $plugin;
    }

    /**
     * Attempts to retrieve plugins from the database.
     */
    private static function _loadFromDatabase(string $category, ?int $mainContextId = null): array
    {
        $plugins = [];
        $categoryDir = static::PLUGINS_PREFIX . $category;
        $products = Application::get()->getEnabledProducts("plugins.{$category}", $mainContextId);
        foreach ($products as $product) {
            $name = $product->getProduct();
            if ($plugin = static::_instantiatePlugin($category, $name, $product->getProductClassname())) {
                $plugins[$plugin->getSeq()]["{$categoryDir}/{$name}"] = $plugin;
            }
        }
        return $plugins;
    }

    /**
     * Get all plug-ins from disk without querying the database, used during installation.
     */
    private static function _loadFromDisk(string $category): array
    {
        $categoryDir = static::PLUGINS_PREFIX . $category;
        if (!is_dir($categoryDir)) {
            return [];
        }
        $plugins = [];
        foreach (new FilesystemIterator($categoryDir) as $path) {
            if (!$path->isDir()) {
                continue;
            }
            $pluginName = $path->getFilename();
            if ($plugin = static::_instantiatePlugin($category, $pluginName)) {
                $plugins[$plugin->getSeq()]["{$categoryDir}/{$pluginName}"] = $plugin;
            }
        }
        return $plugins;
    }

    /**
     * Instantiate a plugin.
     *
     * @deprecated 3.4.0 Old way to instantiate a plugin
     */
    private static function _deprecatedInstantiatePlugin(string $category, string $pluginName): ?Plugin
    {
        $pluginPath = static::PLUGINS_PREFIX . "{$category}/{$pluginName}";
        // Try the plug-in wrapper for backwards compatibility.
        $pluginWrapper = "{$pluginPath}/index.php";
        if (file_exists($pluginWrapper)) {
            return include $pluginWrapper;
        }

        // Try the well-known plug-in class name next (with and without ".inc.php")
        $pluginClassName = ucfirst($pluginName) . ucfirst($category) . 'Plugin';
        if (Arr::first(['.inc.php', '.php'], fn (string $suffix) => file_exists("{$pluginPath}/{$pluginClassName}{$suffix}"))) {
            $pluginPackage = "plugins.{$category}.{$pluginName}";
            return instantiate("{$pluginPackage}.{$pluginClassName}", $pluginClassName, $pluginPackage, 'register');
        }

        return null;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\plugins\PluginRegistry', '\PluginRegistry');
    define('PLUGINS_PREFIX', PluginRegistry::PLUGINS_PREFIX);
}
