<?php

/**
 * @file tests/metadata/CitationDAOTest.inc.php
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
	private $citationDAO;

	protected function setUp() {
		parent::setUp();
		$this->citationDAO = DAORegistry::getDAO('CitationDAO');
	}

	public function testCitationCrud() {
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

		$citation = new Citation('raw citation');
		$citation->setAssocType(ASSOC_TYPE_ARTICLE);
		$citation->setAssocId(999999);
		$citation->setEditedCitation('edited citation');
		$citation->setParseScore(50);
		$citation->injectMetadata($citationDescription);

		// Create citation
		$citationId = $this->citationDAO->insertCitation($citation);
		self::assertTrue(is_numeric($citationId));
		self::assertTrue($citationId > 0);

		// Retrieve citation
		$citationById = $this->citationDAO->getCitation($citationId);
		$citationById->getMetadataFieldNames(); // Initializes internal state for comparison.
		self::assertEquals($citation, $citationById);

		$citationsByAssocIdDaoFactory = $this->citationDAO->getCitationsByAssocId(ASSOC_TYPE_ARTICLE, 999999);
		$citationsByAssocId = $citationsByAssocIdDaoFactory->toArray();
		self::assertEquals(1, count($citationsByAssocId));
		$citationsByAssocId[0]->getMetadataFieldNames(); // Initializes internal state for comparison.
		self::assertEquals($citation, $citationsByAssocId[0]);

		// Update citation
		$citationDescription->removeStatement('date');
		$citationDescription->addStatement('article-title', $value = 'PHPUnit rápido', 'pt_BR');

		$updatedCitation = new Citation('another raw citation');
		$updatedCitation->setId($citationId);
		$updatedCitation->setAssocType(ASSOC_TYPE_ARTICLE);
		$updatedCitation->setAssocId(999998);
		$updatedCitation->setEditedCitation('another edited citation');
		$updatedCitation->setParseScore(50);
		$updatedCitation->injectMetadata($citationDescription);

		$this->citationDAO->updateCitation($updatedCitation);
		$citationAfterUpdate = $this->citationDAO->getCitation($citationId);
		$citationAfterUpdate->getMetadataFieldNames(); // Initializes internal state for comparison.
		self::assertEquals($updatedCitation, $citationAfterUpdate);

		// Delete citation
		$this->citationDAO->deleteCitationsByAssocId(ASSOC_TYPE_ARTICLE, 999998);
		self::assertNull($this->citationDAO->getCitation($citationId));
	}
}
?>