<?php

/**
 * @defgroup tests_plugins_metadata
 */

/**
 * @file tests/plugins/metadata/MetadataPluginTestCase.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class MetadataPluginTestCase
 * @ingroup tests_plugins_metadata
 * @see MetadataPlugin
 *
 * @brief Abstract base class for MetadataPlugin tests.
 */

import('lib.pkp.tests.plugins.PluginTestCase');
import('lib.pkp.classes.plugins.MetadataPlugin');

class MetadataPluginTestCase extends PluginTestCase {
	/**
	 * Executes the metadata plug-in test.
	 * @param $pluginDir string
	 * @param $pluginName string
	 * @param $filterGroups array
	 * @param $controlledVocabs array
	 */
	protected function executeMetadataPluginTest($pluginDir, $pluginName, $filterGroups, $controlledVocabs) {
		// Make sure that the vocab xml configuration is valid.
		$controlledVocabFile = 'plugins/metadata/'.$pluginDir.'/schema/'.METADATA_PLUGIN_VOCAB_DATAFILE;
		$this->validateXmlConfig(array('./'.$controlledVocabFile, './lib/pkp/'.$controlledVocabFile));

		// Make sure that vocab data from earlier tests is being deleted first.
		$controlledVocabDao =& DAORegistry::getDAO('ControlledVocabDAO'); /* @var $controlledVocabDao ControlledVocabDAO */
		foreach($controlledVocabs as $controlledVocabSymbolic) {
			$controlledVocab =& $controlledVocabDao->getBySymbolic($controlledVocabSymbolic, 0, 0);
			if ($controlledVocab) $controlledVocabDao->deleteObject($controlledVocab);
		}

		$pluginSettingsDao =& DAORegistry::getDAO('PluginSettingsDAO'); /* @var $pluginSettingsDao PluginSettingsDAO */
		$pluginSettingsDao->deleteSetting(0, $pluginName, METADATA_PLUGIN_VOCAB_INSTALLED_SETTING);

		$this->executePluginTest('metadata', $pluginDir, $pluginName, $filterGroups);

		// Test whether the controlled vocabs have been installed.
		foreach($controlledVocabs as $controlledVocab) {
			self::assertType('ControlledVocab', $controlledVocabDao->getBySymbolic($controlledVocab, 0, 0));
		}
	}
}
?>