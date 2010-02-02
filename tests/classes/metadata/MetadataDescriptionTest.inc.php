<?php

/**
 * @file tests/metadata/MetadataDescriptionTest.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class MetadataDescriptionTest
 * @ingroup tests_classes_metadata
 * @see MetadataDescription
 *
 * @brief Test class for MetadataDescription.
 */

import('tests.PKPTestCase');
import('metadata.MetadataDescription');
import('tests.classes.metadata.TestSchema');

class MetadataDescriptionTest extends PKPTestCase {
	private $metadataDescription;
	private static
		$testStatements = array(
			array('not-translated-one', 'nto', null),

			array('not-translated-many', 'ntm1', null),
			array('not-translated-many', 'ntm2', null),

			array('translated-one', 'to_en', 'en_US'),
			array('translated-one', 'to_de', 'de_DE'),

			array('translated-many', 'tm1_en', 'en_US'),
			array('translated-many', 'tm1_de', 'de_DE'),
			array('translated-many', 'tm2_en', 'en_US'),
			array('translated-many', 'tm2_de', 'de_DE')
		),
		$testStatementsData = array (
			'not-translated-one' => 'nto',
			'not-translated-many' => array (
				0 => 'ntm1',
				1 => 'ntm2'
			),
			'translated-one' => array (
				'en_US' => 'to_en',
				'de_DE' => 'to_de'
			),
			'translated-many' => array (
				'en_US' => array (
					0 => 'tm1_en',
					1 => 'tm2_en'
				),
				'de_DE' => array (
					0 => 'tm1_de',
					1 => 'tm2_de'
				)
			)
  		);

	protected function setUp() {
		$metadataSchema = new TestSchema();
		$this->metadataDescription = new MetadataDescription($metadataSchema, ASSOC_TYPE_CITATION);
	}

	/**
	 * @covers MetadataDescription::addStatement
	 */
	public function testAddStatement() {
		foreach (self::$testStatements as $test) {
			$this->metadataDescription->addStatement($test[0], $test[1], $test[2]);
		}

		self::assertEquals(self::$testStatementsData, $this->metadataDescription->getAllData());
	}

	public function testSetStatements() {
		$this->metadataDescription->setAllData(self::$testStatementsData);

		$testStatements = array (
			'not-translated-one' => 'nto-new',
			'not-translated-many' => array (
				0 => 'ntm1-new',
			)
  		);

  		$expectedResult = self::$testStatementsData;
  		$expectedResult['not-translated-one'] = 'nto-new';
  		$expectedResult['not-translated-many'] = array(0 => 'ntm1-new');

  		// Test without replace
  		self::assertTrue($this->metadataDescription->setStatements($testStatements));
  		self::assertEquals($expectedResult, $this->metadataDescription->getAllData());

  		// Test replace
  		self::assertTrue($this->metadataDescription->setStatements($testStatements, true));
  		self::assertEquals($testStatements, $this->metadataDescription->getAllData());

  		// Test that an error in the test statements maintains the previous state
  		// of the description.
  		// 1) Set some initial state (and make a non-referenced copy for later comparison)
  		$previousData = array('non-translated-one' => 'previous-value');
  		$previousDataCopy = $previousData;
  		$this->metadataDescription->setAllData($previousData);
  		// 2) Create invalid test statement
  		$testStatements['non-existent-property'] = 'some-value';
  		// 3) Make sure that the previous data will always be restored when
  		//    an error occurs.
  		self::assertFalse($this->metadataDescription->setStatements($testStatements));
  		self::assertEquals($previousDataCopy, $this->metadataDescription->getAllData());
  		self::assertFalse($this->metadataDescription->setStatements($testStatements, true));
  		self::assertEquals($previousDataCopy, $this->metadataDescription->getAllData());
	}
}
?>