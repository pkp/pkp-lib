<?php

/**
 * @defgroup tests_plugins_metadata_mods34 MODS 3.4 Metadata Plugin Tests
 */

/**
 * @file tests/plugins/metadata/mods34/PKPMods34MetadataPluginTest.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPMods34MetadataPluginTest
 * @ingroup tests_plugins_metadata_mods34
 * @see PKPMods34MetadataPlugin
 *
 * @brief Test class for PKPMods34MetadataPlugin.
 */


import('lib.pkp.tests.plugins.metadata.MetadataPluginTestCase');

class PKPMods34MetadataPluginTest extends MetadataPluginTestCase {
	/**
	 * @covers Mods34MetadataPlugin
	 * @covers PKPMods34MetadataPlugin
	 */
	public function testMods34MetadataPlugin($appSpecificFilters) {
		$this->executeMetadataPluginTest(
			'mods34',
			'Mods34MetadataPlugin',
			array_merge($appSpecificFilters, array('mods34=>mods34-xml')),
			array('mods34-name-types', 'mods34-name-role-roleTerms-marcrelator',
				'mods34-typeOfResource', 'mods34-genre-marcgt', 'mods34-physicalDescription-form-marcform')
		);
	}
}
?>
