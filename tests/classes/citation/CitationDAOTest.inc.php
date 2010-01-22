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

import('tests.DatabaseTestCase');
import('citation.CitationDAO');
import('citation.Citation');
import('metadata.NlmNameSchema');
import('metadata.NlmCitationSchema');
import('metadata.MetadataDescription');

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
		$citation->setEditedCitation('edited citation');
		$citation->setParseScore(50);
		$citation->injectMetadata($citationDescription);

		$citationId = $this->citationDAO->insertCitation($citation);
		self::assertTrue(is_integer($citationId));
		self::assertTrue($citationId > 0);
	}
}
?>