<?php

/**
 * @file tests/classes/metadata/nlm/NlmNameSchemaPersonStringFilterTest.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NlmNameSchemaPersonStringFilterTest
 * @ingroup tests_classes_metadata_nlm
 * @see NlmNameSchemaPersonStringFilter
 *
 * @brief Tests for the NlmNameSchemaPersonStringFilter class.
 */

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.metadata.nlm.NlmNameSchemaPersonStringFilter');

class NlmNameSchemaPersonStringFilterTest extends PKPTestCase {
	private $_nlmNameSchemaPersonStringFilter;

	public function setUp() {
		$this->_nlmNameSchemaPersonStringFilter = new NlmNameSchemaPersonStringFilter();
	}

	/**
	 * @covers NlmNameSchemaPersonStringFilter::supports
	 * @covers NlmNameSchemaPersonStringFilter::process
	 * @covers NlmNameSchemaPersonStringFilter::isValid
	 * @covers NlmNameSchemaPersonStringFilter::_flattenPersonDescription
	 */
	public function &testExecuteWithSinglePersonDescription() {
		$personDescription = new MetadataDescription('lib.pkp.classes.metadata.nlm.NlmNameSchema', ASSOC_TYPE_AUTHOR);
		$personDescription->addStatement('given-names', $givenNames = 'Machado');
		$personDescription->addStatement('prefix', $prefix = 'de');
		$personDescription->addStatement('surname', $surname = 'Assis');
		$personDescription->addStatement('suffix', $suffix = 'Jr');
		self::assertEquals('Assis Jr, (Machado) de', $this->_nlmNameSchemaPersonStringFilter->execute($personDescription));
		return $personDescription;
	}

	/**
	 * @covers NlmNameSchemaPersonStringFilter::supports
	 * @covers NlmNameSchemaPersonStringFilter::execute
	 * @covers NlmNameSchemaPersonStringFilter::isValid
	 * @covers NlmNameSchemaPersonStringFilter::_flattenPersonsDescriptions
	 * @depends testExecuteWithSinglePersonDescription
	 */
	public function testExecuteWithMultiplePersonDescriptions($personDescription1) {
		$personDescription2 = new MetadataDescription('lib.pkp.classes.metadata.nlm.NlmNameSchema', ASSOC_TYPE_AUTHOR);
		$personDescription2->addStatement('given-names', $givenNames1 = 'Bernardo');
		$personDescription2->addStatement('given-names', $givenNames2 = 'Antonio');
		$personDescription2->addStatement('surname', $surname = 'Elis');

		$personDescriptions = array($personDescription1, $personDescription2, PERSON_STRING_FILTER_ETAL);
		$this->_nlmNameSchemaPersonStringFilter->setFilterMode(PERSON_STRING_FILTER_MULTIPLE);
		self::assertEquals('Assis Jr, (Machado) de; Elis, A. (Bernardo); et al', $this->_nlmNameSchemaPersonStringFilter->execute($personDescriptions));

		// Test template and delimiter
		$this->_nlmNameSchemaPersonStringFilter->setDelimiter(':');
		$this->_nlmNameSchemaPersonStringFilter->setTemplate('%firstname%%initials%%prefix% %surname%%suffix%');
		self::assertEquals('Machado de Assis Jr:Bernardo A. Elis:et al', $this->_nlmNameSchemaPersonStringFilter->execute($personDescriptions));
	}
}
?>
