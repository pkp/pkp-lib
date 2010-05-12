<?php

/**
 * @file tests/classes/metadata/MetadataDescriptionDAOTest.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class MetadataDescriptionDAOTest
 * @ingroup tests_classes_metadata
 * @see MetadataDescriptionDAO
 *
 * @brief Test class for MetadataDescriptionDAO.
 */

import('lib.pkp.tests.DatabaseTestCase');
import('lib.pkp.classes.metadata.MetadataDescriptionDAO');
import('lib.pkp.classes.metadata.nlm.NlmNameSchema');
import('lib.pkp.classes.metadata.nlm.NlmCitationSchema');
import('lib.pkp.classes.metadata.MetadataDescription');

class MetadataDescriptionDAOTest extends DatabaseTestCase {
	/**
	 * @covers MetadataDescriptionDAO
	 *
	 * FIXME: The test data used here and in the CitationDAOTest
	 * are very similar. We should find a way to not duplicate this
	 * test data.
	 */
	public function testMetadataDescriptionCrud() {
		$metadataDescriptionDAO = DAORegistry::getDAO('MetadataDescriptionDAO');

		$nameSchema = new NlmNameSchema();
		$nameDescription = new MetadataDescription($nameSchema, ASSOC_TYPE_AUTHOR);
		$nameDescription->addStatement('given-names', $value = 'Peter');
		$nameDescription->addStatement('given-names', $value = 'B');
		$nameDescription->addStatement('surname', $value = 'Bork');
		$nameDescription->addStatement('prefix', $value = 'Mr.');

		$citationSchema = new NlmCitationSchema();
		$testDescription = new MetadataDescription($citationSchema, ASSOC_TYPE_CITATION);
		$testDescription->setAssocId(999999);
		$testDescription->setDisplayName('test meta-data description');
		$testDescription->setSeq(5);
		$testDescription->addStatement('person-group[@person-group-type="author"]', $nameDescription);
		$testDescription->addStatement('article-title', $value = 'PHPUnit in a nutshell', 'en_US');
		$testDescription->addStatement('article-title', $value = 'PHPUnit in Kürze', 'de_DE');
		$testDescription->addStatement('date', $value = '2009-08-17');
		$testDescription->addStatement('size', $value = 320);
		$testDescription->addStatement('uri', $value = 'http://phpunit.org/nutshell');

		// Create meta-data description
		$metadataDescriptionId = $metadataDescriptionDAO->insertObject($testDescription);
		self::assertTrue(is_numeric($metadataDescriptionId));
		self::assertTrue($metadataDescriptionId > 0);

		// Retrieve meta-data description by id
		$metadataDescriptionById = $metadataDescriptionDAO->getObjectById($metadataDescriptionId);
		$testDescription->removeSupportedMetadataAdapter($citationSchema); // Required for comparison
		self::assertEquals($testDescription, $metadataDescriptionById);

		$metadataDescriptionsByAssocIdDaoFactory = $metadataDescriptionDAO->getObjectsByAssocId(ASSOC_TYPE_CITATION, 999999);
		$metadataDescriptionsByAssocId = $metadataDescriptionsByAssocIdDaoFactory->toArray();
		self::assertEquals(1, count($metadataDescriptionsByAssocId));
		self::assertEquals($testDescription, $metadataDescriptionsByAssocId[0]);

		// Update meta-data description
		$testDescription->removeStatement('date');
		$testDescription->addStatement('article-title', $value = 'PHPUnit rápido', 'pt_BR');

		$metadataDescriptionDAO->updateObject($testDescription);
		$testDescription->removeSupportedMetadataAdapter($citationSchema); // Required for comparison
		$metadataDescriptionAfterUpdate = $metadataDescriptionDAO->getObjectById($metadataDescriptionId);
		self::assertEquals($testDescription, $metadataDescriptionAfterUpdate);

		// Delete meta-data description
		$metadataDescriptionDAO->deleteObjectsByAssocId(ASSOC_TYPE_CITATION, 999999);
		self::assertNull($metadataDescriptionDAO->getObjectById($metadataDescriptionId));
	}
}
?>