<?php

/**
 * @file tests/classes/citation/CitationDAOTest.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CitationDAOTest
 * @ingroup tests_classes_citation
 * @see CitationDAO
 *
 * @brief Test class for CitationDAO.
 */

import('lib.pkp.tests.DatabaseTestCase');
import('lib.pkp.classes.citation.CitationDAO');
import('lib.pkp.classes.citation.Citation');
import('lib.pkp.classes.metadata.nlm.NlmNameSchema');
import('lib.pkp.classes.metadata.nlm.NlmCitationSchema');
import('lib.pkp.classes.metadata.MetadataDescription');

class CitationDAOTest extends DatabaseTestCase {
	/**
	 * @covers CitationDAO
	 */
	public function testCitationCrud() {
		$citationDAO = DAORegistry::getDAO('CitationDAO');

		$nameSchema = new NlmNameSchema();
		$nameDescription = new MetadataDescription($nameSchema, ASSOC_TYPE_AUTHOR);
		$nameDescription->addStatement('given-names', $value = 'Peter');
		$nameDescription->addStatement('given-names', $value = 'B');
		$nameDescription->addStatement('surname', $value = 'Bork');
		$nameDescription->addStatement('prefix', $value = 'Mr.');

		$citationSchema = new NlmCitationSchema();
		$citationDescription = new MetadataDescription($citationSchema, ASSOC_TYPE_CITATION);
		$citationDescription->addStatement('person-group[@person-group-type="author"]', $nameDescription);
		$citationDescription->addStatement('article-title', $value = 'PHPUnit in a nutshell', 'en_US');
		$citationDescription->addStatement('article-title', $value = 'PHPUnit in Kürze', 'de_DE');
		$citationDescription->addStatement('date', $value = '2009-08-17');
		$citationDescription->addStatement('size', $value = 320);
		$citationDescription->addStatement('uri', $value = 'http://phpunit.org/nutshell');

		// Add a simple source description
		$sourceDescription = new MetadataDescription($citationSchema, ASSOC_TYPE_CITATION);
		$sourceDescription->addStatement('article-title', $value = 'a simple source description', 'en_US');

		$citation = new Citation('raw citation');
		$citation->setAssocType(ASSOC_TYPE_ARTICLE);
		$citation->setAssocId(999999);
		$citation->setEditedCitation('edited citation');
		$citation->setParseScore(50);
		$citation->addSourceDescription($sourceDescription);
		$citation->injectMetadata($citationDescription);

		// Create citation
		$citationId = $citationDAO->insertObject($citation);
		self::assertTrue(is_numeric($citationId));
		self::assertTrue($citationId > 0);

		// Retrieve citation
		$citationById = $citationDAO->getObjectById($citationId);
		// Remove state differences for comparison.
		$citation->removeSupportedMetadataAdapter($citationSchema);
		$citationById->removeSupportedMetadataAdapter($citationSchema);
		$sourceDescription->setAssocId($citationId);
		$sourceDescription->removeSupportedMetadataAdapter($citationSchema);
		self::assertEquals($citation, $citationById);

		$citationsByAssocIdDaoFactory = $citationDAO->getObjectsByAssocId(ASSOC_TYPE_ARTICLE, 999999);
		$citationsByAssocId = $citationsByAssocIdDaoFactory->toArray();
		self::assertEquals(1, count($citationsByAssocId));
		// Remove state differences for comparison.
		$citationsByAssocId[0]->removeSupportedMetadataAdapter($citationSchema);
		self::assertEquals($citation, $citationsByAssocId[0]);

		// Update citation
		$citationDescription->removeStatement('date');
		$citationDescription->addStatement('article-title', $value = 'PHPUnit rápido', 'pt_BR');

		// Update source descriptions
		$sourceDescription->addStatement('article-title', $value = 'edited source description', 'en_US', true);

		$updatedCitation = new Citation('another raw citation');
		$updatedCitation->setId($citationId);
		$updatedCitation->setAssocType(ASSOC_TYPE_ARTICLE);
		$updatedCitation->setAssocId(999998);
		$updatedCitation->setEditedCitation('another edited citation');
		$updatedCitation->setParseScore(50);
		$updatedCitation->addSourceDescription($sourceDescription);
		$updatedCitation->injectMetadata($citationDescription);

		$citationDAO->updateObject($updatedCitation);
		$citationAfterUpdate = $citationDAO->getObjectById($citationId);
		// Remove state differences for comparison.
		$updatedCitation->removeSupportedMetadataAdapter($citationSchema);
		$citationAfterUpdate->removeSupportedMetadataAdapter($citationSchema);
		$sourceDescription->removeSupportedMetadataAdapter($citationSchema);
		self::assertEquals($updatedCitation, $citationAfterUpdate);

		// Delete citation
		$citationDAO->deleteObjectsByAssocId(ASSOC_TYPE_ARTICLE, 999998);
		self::assertNull($citationDAO->getObjectById($citationId));
	}
}
?>