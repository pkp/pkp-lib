<?php

/**
 * @file classes/plugins/MetadataPlugin.inc.php
 *
 * Copyright (c) 2003-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class MetadataPlugin
 * @ingroup plugins
 *
 * @brief Abstract class for metadata plugins
 */


import('classes.plugins.Plugin');

// Define the well-known file name for controlled vocabulary data.
define('METADATA_PLUGIN_VOCAB_DATAFILE', 'controlled_vocabs.xml');

class MetadataPlugin extends Plugin {
	/**
	 * Constructor
	 */
	function MetadataPlugin() {
		parent::Plugin();
	}


	//
	// Protected template methods to be implemented by sub-classes.
	//
	/**
	 * Get the metadata adapter class names provided by this plug-in.
	 * @return array a list of fully qualified class names
	 */
	function getMetadataAdapterNames() {
		// must be implemented by sub-classes
		assert(false);
	}


	//
	// Override public methods from PKPPlugin
	//
	/**
	 * @see PKPPlugin::register()
	 */
	function register($category, $path) {
		$success = parent::register($category, $path);
		HookRegistry::register ('Installer::postInstall', array(&$this, 'installMetadataSchema'));
		$this->addLocaleData();
		return $success;
	}

	/**
	 * This implementation looks for files that contain controlled
	 * vocabulary data.
	 * @see PKPPlugin::getInstallDataFile()
	 */
	function getInstallDataFile() {
		// Search the well-known locations for vocabulary data files. If
		// one is found then return it.
		$pluginPath = $this->getPluginPath();
		$wellKnownVocabLocations = array(
			'./'.$pluginPath.'/schema/'.METADATA_PLUGIN_VOCAB_DATAFILE,
			'./lib/pkp/'.$pluginPath.'/schema/'.METADATA_PLUGIN_VOCAB_DATAFILE
		);
		foreach ($wellKnownVocabLocations as $wellKnownVocabLocation) {
			if (file_exists($wellKnownVocabLocation)) return $wellKnownVocabLocation;
		}

		return null;
	}


	//
	// Public methods
	//
	/**
	 * Installs the meta-data adapters that belong to this schema.
	 * @param $hookName string
	 * @param $args array
	 */
	function installMetadataSchema($hookName, $args) {
		$adapterNames = $this->getMetadataAdapterNames();
		foreach($adapterNames as $adapterName) {
			// Instantiate adapter.
			$adapter =& instantiate($adapterName, 'MetadataDataObjectAdapter');

			// Install adapters as non-configurable site-wide filter instances.
			$adapter->setIsTemplate(false);

			// Make sure that the adapter has not been
			// installed before to guarantee idempotence.
			$filterDao =& DAORegistry::getDAO('FilterDAO'); /* @var $filterDao FilterDAO */
			$existingAdapters =& $filterDao->getObjectsByClass($adapterName, 0, false);
			if ($existingAdapters->getCount()) continue;

			// Install the adapter.
			$filterDao->insertObject($adapter, 0);
			unset($adapter);
		}
	}
}

?>
