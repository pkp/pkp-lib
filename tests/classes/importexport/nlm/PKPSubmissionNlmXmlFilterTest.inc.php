<?php

/**
 * @file tests/classes/importexport/nlm/PKPSubmissionNlmXmlFilterTest.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionNlmXmlFilterTest
 * @ingroup tests_classes_importexport_nlm
 * @see PKPSubmissionNlmXmlFilter
 *
 * @brief Tests for the PKPSubmissionNlmXmlFilterTest class.
 */

import('lib.pkp.tests.classes.importexport.nlm.NlmXmlFilterTest');
import('lib.pkp.classes.importexport.nlm.PKPSubmissionNlmXmlFilter');

class PKPSubmissionNlmXmlFilterTest extends NlmXmlFilterTest {
	/**
	 * @covers PKPSubmissionNlmXmlFilter
	 */
	public function testExecute() {
		// Instantiate test meta-data for a citation.
		import('lib.pkp.classes.metadata.MetadataDescription');
		$nameSchemaName = 'lib.pkp.classes.metadata.nlm.NlmNameSchema';
		$nameDescription = new MetadataDescription($nameSchemaName, ASSOC_TYPE_AUTHOR);
		$nameDescription->addStatement('given-names', $value = 'Peter');
		$nameDescription->addStatement('given-names', $value = 'B');
		$nameDescription->addStatement('surname', $value = 'Bork');
		$nameDescription->addStatement('prefix', $value = 'Mr.');

		$citationSchemaName = 'lib.pkp.classes.metadata.nlm.NlmCitationSchema';
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

		// Construct the expected output.
		$expectedOutput = '';
		for ($seq = 1; $seq <= 10; $seq++) {
			$expectedOutput .=
				"\t".'<ref id="B'.$seq.'">'."\n"
				. "\t\t".'<label>'.$seq.'</label>'."\n"
				. "\t\t".'<element-citation publication-type="book"><person-group person-group-type="author"><name><surname>Bork</surname><given-names>Peter B</given-names><prefix>Mr.</prefix></name></person-group><article-title>PHPUnit in a nutshell</article-title><day>17</day><month>8</month><year>2009</year><size>320</size><uri>http://phpunit.org/nutshell</uri></element-citation>'."\n"
				."\t".'</ref>'."\n";
		}

		// Execute the filter and check the outcome.
		$mockSubmission =& $this->getTestSubmission();
		$filter = new PKPSubmissionNlmXmlFilter();
		self::assertEquals($expectedOutput, $filter->execute($mockSubmission));
	}
}
?>
