<?php

/**
 * @file tools/plugins.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PluginsTool
 * @ingroup tools
 *
 * @brief CLI tool to get information about installed/available plugins
 */

require(dirname(dirname(dirname(dirname(__FILE__)))) . '/tools/bootstrap.inc.php');

import('lib.pkp.controllers.grid.plugins.PluginGalleryGridHandler'); // load constant: PLUGIN_GALLERY_ALL_CATEGORY_SEARCH_VALUE

class PluginsTool extends CommandLineTool
{
    /**
     * Constructor.
     * @param $argv array command-line arguments
     */
    function __construct($argv = array())
    {
        parent::__construct($argv);

        if (!isset($this->argv[0]) || !$this->validateArgs()) {
            $this->usage();
            exit(1);
        }
    }

    /**
     * Validate arguments
     */
    function validateArgs()
    {
        switch ($this->argv[0]) {
            case 'list':
                if (count($this->argv) > 2) {
                    return false;
                }
                return true;
            case 'info':
                if (count($this->argv) != 2) {
                    return false;
                }
                if (count(explode('/', $this->argv[1])) != 2) {
                    echo "\n\033[0;31m✘ The plugin path `" . $this->argv[1] . "` is not valid. It should be in the following format: generic/pln\033[0m\n\n";
                    return false;
                }
                return true;
            default:
                return false;
        }
    }

    /**
     * Print command usage information.
     */
    function usage()
    {
        echo "Plugin Gallery tool\n"
            . "Usage: {$this->scriptName} action [arguments]\n"
            . "  Actions:\n"
            . "\tlist [search]: show latest compatible plugin(s). Optional \"search\" text against plugin class\n"
            . "\tinfo path: show detail for plugin identified by \"path\", such as generic/pln\n";
    }

    /**
     * Execute the specified command.
     */
    function execute()
    {
        $result = false;
        /** @var PluginGalleryDAO $pluginGalleryDao */
        $pluginGalleryDao = DAORegistry::getDAO('PluginGalleryDAO');
        switch ($this->argv[0]) {
            case 'list':
                $plugins = $pluginGalleryDao->getNewestCompatible(
                    Application::get(),
                    null,
                    count($this->argv) > 1 ? $this->argv[1] : null
                );
                $this->listPlugins($plugins);
                $result = true;
                break;
            case 'info':
                $opts = explode('/', $this->argv[1]);
                $plugin = $this->selectPlugin($opts[0], $opts[1]);
                if ($plugin) {
                    foreach ($plugin->getAllData() as $key => $data) {
                        if (is_array($data)) {
                            print $key.': '.str_replace("\n", '\n', $plugin->getLocalizedData($key))."\n";
                        } else {
                            print $key.': '.str_replace("\n", '\n', $data)."\n";
                        }
                    }
                    $result = true;
                }
                if (!$result) {
                    error_log('"'.$opts[1].'" not found in "'.$opts[0].'"');
                    $result = true;
                }
                break;
        }
        if (!$result) {
            $this->usage();
            exit(1);
        }
        return $result;
    }

    /**
     * Select a specific plugin
     * @param $category string a plugin category
     * @param $name string a plugin name
     * @return GalleryPlugin|null
     */
    function selectPlugin($category, $name)
    {
        /** @var PluginGalleryDAO $pluginGalleryDao */
        $pluginGalleryDao = DAORegistry::getDAO('PluginGalleryDAO');
        $plugins = $pluginGalleryDao->getNewestCompatible(
            Application::get(),
            $category,
            $name
        );
        foreach ($plugins as $plugin) {
            if ($plugin->getData('product') === $name) {
                return $plugin;
            }
        }
        return;
    }

    /**
     * Print the plugins as a list
     * @param $plugins GalleryPlugin[] array of plugins
     */
    function listPlugins($plugins)
    {
        foreach ($plugins as $plugin) {
            $statusKey = '';
            switch ($plugin->getCurrentStatus()) {
                case PLUGIN_GALLERY_STATE_NEWER:
                $statusKey = 'manager.plugins.installedVersionNewer';
                break;
                case PLUGIN_GALLERY_STATE_UPGRADABLE:
                $statusKey = 'manager.plugins.installedVersionOlder';
                break;
                case PLUGIN_GALLERY_STATE_CURRENT:
                $statusKey = 'manager.plugins.installedVersionNewest';
                break;
                case PLUGIN_GALLERY_STATE_AVAILABLE:
                $statusKey = 'manager.plugins.noInstalledVersion';
                break;
                case PLUGIN_GALLERY_STATE_INCOMPATIBLE:
                $statusKey = 'manager.plugins.noCompatibleVersion';
                break;
            }
            $keyOut = explode('.', $statusKey);
            $keyOut = array_pop($keyOut);
            print implode('/', array('plugins', $plugin->getData('category'), $plugin->getData('product'))) . ' ' . $plugin->getData('releasePackage') . ' ' . $keyOut . "\n";
        }
    }
}

$tool = new PluginsTool(isset($argv) ? $argv : array());
$tool->execute();
