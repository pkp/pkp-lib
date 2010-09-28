<?php

/**
 * @file tests/plugins/metadata/mods/filter/ModsDescriptionXmlFilterTest.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ModsDescriptionXmlFilterTest
 * @ingroup tests_plugins_metadata_mods_filter
 * @see ModsDescriptionXmlFilter
 *
 * @brief Test class for ModsDescriptionXmlFilter.
 */

import('lib.pkp.tests.plugins.metadata.mods.filter.ModsDescriptionTestCase');
import('lib.pkp.plugins.metadata.mods.filter.ModsDescriptionXmlFilter');

class ModsDescriptionXmlFilterTest extends ModsDescriptionTestCase {
	/**
	 * @covers ModsDescriptionXmlFilter
	 */
	public function testModsDescriptionXmlFilter() {
		// Get the test description.
		$submissionDescription = $this->getModsDescription();

		// Instantiate filter.
		$filter = new ModsDescriptionXmlFilter();

		// Transform MODS description to XML.
		$output = $filter->execute($submissionDescription);
		self::assertXmlStringEqualsXmlFile('./lib/pkp/tests/plugins/metadata/mods/filter/test.xml', $output);
	}
}
?>