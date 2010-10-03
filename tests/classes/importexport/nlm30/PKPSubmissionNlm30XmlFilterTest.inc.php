<?php

/**
 * @file tests/classes/importexport/nlm30/PKPSubmissionNlm30XmlFilterTest.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionNlm30XmlFilterTest
 * @ingroup tests_classes_importexport_nlm
 * @see PKPSubmissionNlm30XmlFilter
 *
 * @brief Tests for the PKPSubmissionNlm30XmlFilterTest class.
 */

import('lib.pkp.tests.classes.importexport.nlm.Nlm30XmlFilterTest');
import('lib.pkp.classes.importexport.nlm.PKPSubmissionNlm30XmlFilter');

class PKPSubmissionNlm30XmlFilterTest extends Nlm30XmlFilterTest {
	/**
	 * @covers PKPSubmissionNlm30XmlFilter
	 */
	public function testExecute() {
		// Instantiate test meta-data for a citation.
		import('lib.pkp.classes.metadata.MetadataDescription');
		$nameSchemaName = 'lib.pkp.plugins.metadata.nlm30.schema.Nlm30NameSchema';
		$nameDescription = new MetadataDescription($nameSchemaName, ASSOC_TYPE_AUTHOR);
		$nameDescription->addStatement('given-names', $value = 'Peter');
		$nameDescription->addStatement('given-names', $value = 'B');
		$nameDescription->addStatement('surname', $value = 'Bork');
		$nameDescription->addStatement('prefix', $value = 'Mr.');

		$citationSchemaName = 'lib.pkp.plugins.metadata.nlm30.schema.Nlm30CitationSchema';
		$citationDescription = new MetadataDescription($citationSchemaName, ASSOC_TYPE_CITATION);
		$citationDescription->addStatement('person-group[@person-group-type="author"]', $nameDescription);
		$citationDescription->addStatement('article-title', $value = 'PHPUnit in a nutshell', 'en_US');
		$citationDescription->addStatement('date', $value = '2009-08-17');
		$citationDescription->addStatement('size', $value = 320);
		$citationDescription->addStatement('uri', $value = 'http://phpunit.org/nutshell');
		$citationDescription->addStatement('[@publication-type]', $value = 'book');

		$citation =& $this->getCitation($citationDescription);

		// Persist a few copies of the citation for testing.
		$citationDao =& $this->getCitationDao();
		for ($seq = 1; $seq <= 10; $seq++) {
			$citation->setSeq($seq);
			$citationId = $citationDao->insertObject($citation);
			self::assertTrue(is_numeric($citationId));
			self::assertTrue($citationId > 0);
		}

		// Execute the filter and check the outcome.
		$mockSubmission =& $this->getTestSubmission();
		$filter = new PKPSubmissionNlm30XmlFilter();
		$nlmXml = $filter->execute($mockSubmission);

		$this->normalizeAndCompare($nlmXml, 'lib/pkp/tests/classes/importexport/nlm30/sample-nlm30-citation.xml');
	}
}
?>
