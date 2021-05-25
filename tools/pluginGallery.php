<?php

/**
 * @file tools/pluginGalleryTool.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PluginGalleryTool
 * @ingroup tools
 *
 * @brief CLI tool for installing a plugin version descriptor.
 */

require(dirname(dirname(dirname(dirname(__FILE__)))) . '/tools/bootstrap.inc.php');

import('lib.pkp.classes.plugins.PluginGalleryDAO');

define('PLUGIN_GALLERY_ALL_CATEGORY_SEARCH_VALUE', 'all');

class PluginGalleryTool extends CommandLineTool {
	/**
	 * Constructor.
	 * @param $argv array command-line arguments
	 */
	function __construct($argv = array()) {
		parent::__construct($argv);

		if (!isset($this->argv[0]) || !$this->validateArgs()) {
			$this->usage();
			exit(1);
		}
	}

	/**
	 * Validate arguments
	 */
	function validateArgs() {
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
				if (count(explode('/', $this->argv[1])) != 3) {
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
	function usage() {
		echo "Plugin Gallery tool\n"
			. "Usage: {$this->scriptName} action [arguments]\n"
			. "  Actions:\n"
			. "\tlist [search]: show latest compatible plugin(s), by optional criteria \"search\"\n"
			. "\tinfo path: show detail for plugin identified by \"path\"\n";
	}

	/**
	 * Execute the specified command.
	 */
	function execute() {
		$result = false;
		$pluginGalleryDao = DAORegistry::getDAO('PluginGalleryDAO');
		switch ($this->argv[0]) {
			case 'list':
				$plugins = $pluginGalleryDao->getNewestCompatible(
					method_exists('Application', 'get') ? Application::get() : Application::getApplication(),
					null,
					count($this->argv) > 1 ? $this->argv[1] : null
				);
				$this->listPlugins($plugins);
				$result = true;
				break;
			case 'info':
				$opts = explode('/', $this->argv[1]);
				$plugin = $this->selectPlugin($opts[1], $opts[2]);
				if ($plugin) {
					$localeFields = $pluginGalleryDao->getLocaleFieldNames();
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
					error_log('"'.$opts[2].'" not found in "'.$opts[1].'"');
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
	function selectPlugin($category, $name) {
		$pluginGalleryDao = DAORegistry::getDAO('PluginGalleryDAO');
		$plugins = $pluginGalleryDao->getNewestCompatible(
			method_exists('Application', 'get') ? Application::get() : Application::getApplication(),
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
	function listPlugins($plugins) {
		foreach ($plugins as $key => $plugin) {
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

$tool = new PluginGalleryTool(isset($argv) ? $argv : array());
$tool->execute();


