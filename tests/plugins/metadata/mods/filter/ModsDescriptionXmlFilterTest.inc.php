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

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.metadata.MetadataDescription');
import('lib.pkp.plugins.metadata.mods.filter.ModsDescriptionXmlFilter');

class ModsDescriptionXmlFilterTest extends PKPTestCase {
	/**
	 * @covers ModsDescriptionXmlFilter
	 */
	public function testModsDescriptionXmlFilter() {
		// Instantiate a test description.
		$authorDescription = new MetadataDescription('lib.pkp.plugins.metadata.mods.schema.ModsNameSchema', ASSOC_TYPE_AUTHOR);
		self::assertTrue($authorDescription->addStatement('[@type]', $nameType = 'personal'));
		self::assertTrue($authorDescription->addStatement('namePart[@type="family"]', $familyName = 'some family name'));
		self::assertTrue($authorDescription->addStatement('role/roleTerm[@type="code" @authority="marcrelator"]', $role = 'aut'));
		$submissionDescription = new MetadataDescription('plugins.metadata.mods.schema.ModsSchema', ASSOC_TYPE_CITATION);
		self::assertTrue($submissionDescription->addStatement('titleInfo/title', $articleTitle = 'new submission title'));
		self::assertTrue($submissionDescription->addStatement('name', $authorDescription));
		self::assertTrue($submissionDescription->addStatement('typeOfResource', $typeOfResource = 'text'));
		self::assertTrue($submissionDescription->addStatement('recordInfo/languageOfCataloging/languageTerm[@authority="iso639-2b"]', $languageOfCataloging = 'eng'));

		// Instantiate filter.
		$filter = new ModsDescriptionXmlFilter();

		// Transform MODS description to XML.
		$output = $filter->execute($submissionDescription);

		// FIXME: continue here
		// - compare output to stored XML
		// - add much more statements, especially for other cardinalities, translations, etc.
		// - create a common base class with the adapter test and test the adapter with the same description
	}
}
?>