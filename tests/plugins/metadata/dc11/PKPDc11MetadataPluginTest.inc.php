<?php

/**
 * @defgroup tests_plugins_metadata_dc11
 */

/**
 * @file tests/plugins/metadata/dc11/PKPDc11MetadataPluginTest.inc.php
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPDc11MetadataPluginTest
 * @ingroup tests_plugins_metadata_dc11
 * @see PKPDc11MetadataPlugin
 *
 * @brief Test class for PKPDc11MetadataPlugin.
 */


import('lib.pkp.tests.plugins.metadata.MetadataPluginTestCase');

class PKPDc11MetadataPluginTest extends MetadataPluginTestCase {
	/**
	 * @covers Dc11MetadataPlugin
	 * @covers PKPDc11MetadataPlugin
	 */
	public function testDc11MetadataPlugin($appSpecificFilters) {
		$this->executeMetadataPluginTest(
			'dc11',
			'Dc11MetadataPlugin',
			$appSpecificFilters,
			array()
		);
	}
}
?>
