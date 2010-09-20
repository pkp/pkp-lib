<?php

/**
 * @file tests/plugins/metadata/mods/filter/ModsSchemaSubmissionAdapterTest.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ModsSchemaSubmissionAdapterTest
 * @ingroup tests_plugins_metadata_mods_filter
 * @see ModsSchemaSubmissionAdapter
 *
 * @brief Test class for ModsSchemaSubmissionAdapter.
 */

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.submission.Submission');
import('lib.pkp.plugins.metadata.mods.filter.ModsSchemaSubmissionAdapter');

class ModsSchemaSubmissionAdapterTest extends PKPTestCase {
	/**
	 * @covers ModsSchemaSubmissionAdapter
	 */
	public function testModsSchemaSubmissionAdapter() {
		// Test constructor.
		$adapter = new ModsSchemaSubmissionAdapter(ASSOC_TYPE_CITATION);
		self::assertEquals(ASSOC_TYPE_CITATION, $adapter->getAssocType());
		self::assertType('ModsSchema', $adapter->getMetadataSchema());
		$expectedTransformations = array(
			array(
				'metadata::plugins.metadata.mods.schema.ModsSchema(CITATION)',
				'class::lib.pkp.classes.submission.Submission'
			),
			array(
				'class::lib.pkp.classes.submission.Submission',
				'metadata::plugins.metadata.mods.schema.ModsSchema(CITATION)'
			)
		);
		self::assertEquals($expectedTransformations, $adapter->getSupportedTransformations());

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

		// Instantiate test submission.
		$submission = new Submission();
		$submission->setTitle('previous submission title', 'en_US');
		$submission->setAbstract('previous abstract', 'en_US');

		// Test metadata injection (no replace).
		$resultSubmission =& $adapter->injectMetadataIntoDataObject($submissionDescription, $submission, false, 'lib.pkp.tests.plugins.metadata.mods.filter.Author');
		$expectedResult = array(
			'cleanTitle' => array('en_US' => 'new submission title'),
			'title' => array('en_US' => 'new submission title'),
			'abstract' => array('en_US' => 'previous abstract')
		);
		self::assertEquals($expectedResult, $resultSubmission->getAllData());

		// Test meta-data injection (replace).
		$resultSubmission =& $adapter->injectMetadataIntoDataObject($submissionDescription, $submission, true, 'lib.pkp.tests.plugins.metadata.mods.filter.Author');
		$expectedResult = array(
			'cleanTitle' => array('en_US' => 'new submission title'),
			'title' => array('en_US' => 'new submission title')
		);
		self::assertEquals($expectedResult, $resultSubmission->getAllData());

		// Test meta-data extraction.
		$extractedDescription =& $adapter->extractMetadataFromDataObject($submission);
		self::assertEquals($submissionDescription, $extractedDescription);
	}
}
?>