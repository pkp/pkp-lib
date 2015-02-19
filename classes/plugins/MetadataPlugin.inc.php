<?php

/**
 * @file classes/plugins/MetadataPlugin.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class MetadataPlugin
 * @ingroup plugins
 *
 * @brief Abstract class for metadata plugins
 */


import('classes.plugins.Plugin');

// Define the well-known file name for controlled vocabulary data.
define('METADATA_PLUGIN_VOCAB_DATAFILE', 'controlledVocabs.xml');

// Define the sitewide plug-in setting that saves the state of the
// controlled vocabulary data.
define('METADATA_PLUGIN_VOCAB_INSTALLED_SETTING', 'metadataPluginControlledVocabInstalled');

class MetadataPlugin extends Plugin {
	/**
	 * Constructor
	 */
	function MetadataPlugin() {
		parent::Plugin();
	}


	//
	// Override public methods from PKPPlugin
	//
	/**
	 * @see PKPPlugin::register()
	 */
	function register($category, $path) {
		$success = parent::register($category, $path);
		$this->addLocaleData();
		return $success;
	}

	/**
	 * This implementation looks for files that contain controlled
	 * vocabulary data. It can discover and return more than one file.
	 * @see PKPPlugin::getInstallDataFile()
	 * @return array|null
	 */
	function getInstallDataFile() {
		// Check whether the vocabulary has already
		// been installed.
		if($this->getSetting(0, METADATA_PLUGIN_VOCAB_INSTALLED_SETTING)) return null;

		// Search the well-known locations for vocabulary data files. If
		// one is found then return it.
		$pluginPath = $this->getPluginPath();
		$wellKnownVocabLocations = array(
			'./'.$pluginPath.'/schema/'.METADATA_PLUGIN_VOCAB_DATAFILE,
			'./lib/pkp/'.$pluginPath.'/schema/'.METADATA_PLUGIN_VOCAB_DATAFILE
		);

		$dataFiles = array();
		foreach ($wellKnownVocabLocations as $wellKnownVocabLocation) {
			if (file_exists($wellKnownVocabLocation)) $dataFiles[] = $wellKnownVocabLocation;
		}

		if(empty($dataFiles)) {
			return null;
		} else {
			return $dataFiles;
		}
	}

	/**
	 * This implementation marks the vocabulary data as installed.
	 * @see PKPPlugin::installData()
	 */
	function installData($hookName, $args) {
		parent::installData($hookName, $args);
		$success =& $args[1];

		if ($success) {
			// Mark the controlled vocab as installed.
			$pluginSettingsDao =& DAORegistry::getDAO('PluginSettingsDAO'); /* @var $pluginSettingsDao PluginSettingsDAO */
			$success = $pluginSettingsDao->updateSetting(0, $this->getName(), METADATA_PLUGIN_VOCAB_INSTALLED_SETTING, true, 'bool');
		}

		return false;
	}
}

?>
