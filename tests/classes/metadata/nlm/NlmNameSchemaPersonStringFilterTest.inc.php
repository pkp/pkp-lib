<?php

/**
 * @file tests/plugins/metadata/nlm30/Nlm30NameSchemaPersonStringFilterTest.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Nlm30NameSchemaPersonStringFilterTest
 * @ingroup tests_classes_metadata_nlm
 * @see Nlm30NameSchemaPersonStringFilter
 *
 * @brief Tests for the Nlm30NameSchemaPersonStringFilter class.
 */

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.plugins.metadata.nlm30.filter.Nlm30NameSchemaPersonStringFilter');

class Nlm30NameSchemaPersonStringFilterTest extends PKPTestCase {
	/**
	 * @covers Nlm30NameSchemaPersonStringFilter
	 * @covers Nlm30PersonStringFilter
	 */
	public function &testExecuteWithSinglePersonDescription() {
		$personDescription = new MetadataDescription('lib.pkp.plugins.metadata.nlm30.schema.Nlm30NameSchema', ASSOC_TYPE_AUTHOR);
		$personDescription->addStatement('given-names', $givenNames = 'Machado');
		$personDescription->addStatement('prefix', $prefix = 'de');
		$personDescription->addStatement('surname', $surname = 'Assis');
		$personDescription->addStatement('suffix', $suffix = 'Jr');

		$nlmNameSchemaPersonStringFilter = new Nlm30NameSchemaPersonStringFilter();
		self::assertEquals('Assis Jr, (Machado) de', $nlmNameSchemaPersonStringFilter->execute($personDescription));
		return $personDescription;
	}

	/**
	 * @covers Nlm30NameSchemaPersonStringFilter
	 * @covers Nlm30PersonStringFilter
	 * @depends testExecuteWithSinglePersonDescription
	 */
	public function testExecuteWithMultiplePersonDescriptions($personDescription1) {
		$personDescription2 = new MetadataDescription('lib.pkp.plugins.metadata.nlm30.schema.Nlm30NameSchema', ASSOC_TYPE_AUTHOR);
		$personDescription2->addStatement('given-names', $givenNames1 = 'Bernardo');
		$personDescription2->addStatement('given-names', $givenNames2 = 'Antonio');
		$personDescription2->addStatement('surname', $surname = 'Elis');

		$personDescriptions = array($personDescription1, $personDescription2, PERSON_STRING_FILTER_ETAL);

		$nlmNameSchemaPersonStringFilter = new Nlm30NameSchemaPersonStringFilter(PERSON_STRING_FILTER_MULTIPLE);

		self::assertEquals('Assis Jr, (Machado) de; Elis, A. (Bernardo); et al', $nlmNameSchemaPersonStringFilter->execute($personDescriptions));

		// Test template and delimiter
		$nlmNameSchemaPersonStringFilter->setDelimiter(':');
		$nlmNameSchemaPersonStringFilter->setTemplate('%firstname%%initials%%prefix% %surname%%suffix%');
		self::assertEquals('Machado de Assis Jr:Bernardo A. Elis:et al', $nlmNameSchemaPersonStringFilter->execute($personDescriptions));
	}
}
?>
