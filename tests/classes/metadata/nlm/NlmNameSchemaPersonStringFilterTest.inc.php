<?php

/**
 * @file tests/plugins/metadata/nlm30/NlmNameSchemaPersonStringFilterTest.inc.php
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
import('lib.pkp.plugins.metadata.nlm30.filter.NlmNameSchemaPersonStringFilter');

class NlmNameSchemaPersonStringFilterTest extends PKPTestCase {
	/**
	 * @covers NlmNameSchemaPersonStringFilter
	 * @covers NlmPersonStringFilter
	 */
	public function &testExecuteWithSinglePersonDescription() {
		$personDescription = new MetadataDescription('lib.pkp.plugins.metadata.nlm30.schema.NlmNameSchema', ASSOC_TYPE_AUTHOR);
		$personDescription->addStatement('given-names', $givenNames = 'Machado');
		$personDescription->addStatement('prefix', $prefix = 'de');
		$personDescription->addStatement('surname', $surname = 'Assis');
		$personDescription->addStatement('suffix', $suffix = 'Jr');

		$nlmNameSchemaPersonStringFilter = new NlmNameSchemaPersonStringFilter();
		self::assertEquals('Assis Jr, (Machado) de', $nlmNameSchemaPersonStringFilter->execute($personDescription));
		return $personDescription;
	}

	/**
	 * @covers NlmNameSchemaPersonStringFilter
	 * @covers NlmPersonStringFilter
	 * @depends testExecuteWithSinglePersonDescription
	 */
	public function testExecuteWithMultiplePersonDescriptions($personDescription1) {
		$personDescription2 = new MetadataDescription('lib.pkp.plugins.metadata.nlm30.schema.NlmNameSchema', ASSOC_TYPE_AUTHOR);
		$personDescription2->addStatement('given-names', $givenNames1 = 'Bernardo');
		$personDescription2->addStatement('given-names', $givenNames2 = 'Antonio');
		$personDescription2->addStatement('surname', $surname = 'Elis');

		$personDescriptions = array($personDescription1, $personDescription2, PERSON_STRING_FILTER_ETAL);

		$nlmNameSchemaPersonStringFilter = new NlmNameSchemaPersonStringFilter(PERSON_STRING_FILTER_MULTIPLE);

		self::assertEquals('Assis Jr, (Machado) de; Elis, A. (Bernardo); et al', $nlmNameSchemaPersonStringFilter->execute($personDescriptions));

		// Test template and delimiter
		$nlmNameSchemaPersonStringFilter->setDelimiter(':');
		$nlmNameSchemaPersonStringFilter->setTemplate('%firstname%%initials%%prefix% %surname%%suffix%');
		self::assertEquals('Machado de Assis Jr:Bernardo A. Elis:et al', $nlmNameSchemaPersonStringFilter->execute($personDescriptions));
	}
}
?>
