<?php

/**
 * @file tests/plugins/metadata/mods/filter/Mods34SchemaSubmissionAdapterTest.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Mods34SchemaSubmissionAdapterTest
 * @ingroup tests_plugins_metadata_mods_filter
 * @see Mods34SchemaSubmissionAdapter
 *
 * @brief Test class for Mods34SchemaSubmissionAdapter.
 */

import('lib.pkp.tests.plugins.metadata.mods34.filter.Mods34DescriptionTestCase');
import('lib.pkp.classes.submission.Submission');
import('lib.pkp.plugins.metadata.mods34.filter.Mods34SchemaSubmissionAdapter');

class Mods34SchemaSubmissionAdapterTest extends Mods34DescriptionTestCase {
	/**
	 * @covers Mods34SchemaSubmissionAdapter
	 */
	public function testMods34SchemaSubmissionAdapter() {
		// Test constructor.
		$adapter = new Mods34SchemaSubmissionAdapter(ASSOC_TYPE_CITATION);
		self::assertEquals(ASSOC_TYPE_CITATION, $adapter->getAssocType());
		self::assertType('Mods34Schema', $adapter->getMetadataSchema());
		$expectedTransformations = array(
			array(
				'metadata::plugins.metadata.mods34.schema.Mods34Schema(CITATION)',
				'class::lib.pkp.classes.submission.Submission'
			),
			array(
				'class::lib.pkp.classes.submission.Submission',
				'metadata::plugins.metadata.mods34.schema.Mods34Schema(CITATION)'
			)
		);
		self::assertEquals($expectedTransformations, $adapter->getSupportedTransformations());

		// Instantiate a test description.
		$submissionDescription =& $this->getMods34Description();

		// Instantiate test submission.
		$submission = new Submission();
		$submission->setTitle('previous submission title', 'en_US');
		$submission->setAbstract('previous abstract', 'en_US');
		// Remove the abstract to test whether the replacement flag works.
		// (The abstract should not be deleted if replace is off.)
		$submissionDescription->removeStatement('abstract');

		// Test metadata injection (no replace).
		$resultSubmission =& $adapter->injectMetadataIntoDataObject($submissionDescription, $submission, false, 'lib.pkp.tests.plugins.metadata.mods34.filter.Author');
		$expectedResult = array(
			'cleanTitle' => array('en_US' => 'new submission title', 'de_DE' => 'neuer Titel'),
			'title' => array('en_US' => 'new submission title', 'de_DE' => 'neuer Titel'),
			'abstract' => array('en_US' => 'previous abstract'),
			'sponsor' => array('en_US' => 'Some Sponsor'),
			'dateSubmitted' => '2010-07-07',
			'language' => 'en',
			'pages' => 215,
			'coverageGeo' => array('en_US' => 'some geography'),
			'mods34:titleInfo/nonSort' => array('en_US' => 'the', 'de_DE' => 'ein'),
			'mods34:titleInfo/subTitle' => array('en_US' => 'subtitle', 'de_DE' => 'Subtitel'),
			'mods34:titleInfo/partNumber' => array('en_US' => 'part I', 'de_DE' => 'Teil I'),
			'mods34:titleInfo/partName' => array('en_US' => 'introduction', 'de_DE' => 'Einführung'),
			'mods34:note' => array(
				'en_US' => array('0' => 'some note', '1' => 'another note'),
				'de_DE' => array('0' => 'übersetzte Anmerkung')
			),
			'mods34:subject/temporal[@encoding="w3cdtf" @point="start"]' => '1950',
			'mods34:subject/temporal[@encoding="w3cdtf" @point="end"]' => '1954'
		);
		self::assertEquals($expectedResult, $resultSubmission->getAllData());

		// Test meta-data injection (replace).
		$resultSubmission =& $adapter->injectMetadataIntoDataObject($submissionDescription, $submission, true, 'lib.pkp.tests.plugins.metadata.mods34.filter.Author');
		unset($expectedResult['abstract'], $expectedResult['recordInfo/recordIdentifier[@source="pkp"]']);
		self::assertEquals($expectedResult, $resultSubmission->getAllData());

		// Test meta-data extraction.
		$extractedDescription =& $adapter->extractMetadataFromDataObject($submission);
		$submissionDescription->removeStatement('recordInfo/recordCreationDate[@encoding="w3cdtf"]');
		self::assertTrue($submissionDescription->addStatement('recordInfo/recordCreationDate[@encoding="w3cdtf"]', date('Y-m-d')));

		$missingMappings = array(
			// The following properties must be mapped via
			// application-specific subclasses.
			'genre[@authority="marcgt"]',
			'originInfo/place/placeTerm[@type="text"]',
			'originInfo/place/placeTerm[@type="code" @authority="iso3166"]',
			'originInfo/publisher',
			'originInfo/dateIssued[@keyDate="yes" @encoding="w3cdtf"]',
			'originInfo/edition',
			'physicalDescription/form[@authority="marcform"]',
			'physicalDescription/internetMediaType',
			'identifier[@type="isbn"]',
			'identifier[@type="doi"]',
			'identifier[@type="uri"]',
			'location/url[@usage="primary display"]',

			// Impossible to be correctly mapped right now, see
			// corresponding comments in the adapter.
			'recordInfo/recordIdentifier[@source="pkp"]',
			'subject/topic',
		);
		foreach($missingMappings as $missingMapping) {
			$submissionDescription->removeStatement($missingMapping);
		}
		self::assertEquals($submissionDescription, $extractedDescription);
	}
}
?>