<?php

/**
 * @file tests/metadata/MetadataDescriptionTest.inc.php
 *
 * Copyright (c) 2003-2009 John Willinsky
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

	protected function setUp() {
		$metadataSchema = new TestSchema();
		$this->metadataDescription = new MetadataDescription($metadataSchema, ASSOC_TYPE_CITATION);
	}

	/**
	 * @covers MetadataDescription::addStatement
	 */
	public function testAddStatement() {
		$tests = array(
			array('not-translated-one', 'nto', null),

			array('not-translated-many', 'ntm1', null),
			array('not-translated-many', 'ntm2', null),

			array('translated-one', 'to_en', 'en_US'),
			array('translated-one', 'to_de', 'de_DE'),

			array('translated-many', 'tm1_en', 'en_US'),
			array('translated-many', 'tm1_de', 'de_DE'),
			array('translated-many', 'tm2_en', 'en_US'),
			array('translated-many', 'tm2_de', 'de_DE')
		);

		foreach ($tests as $test) {
			$this->metadataDescription->addStatement($test[0], $test[1], $test[2]);
		}

		$expectedResult = array (
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
		self::assertEquals($expectedResult, $this->metadataDescription->getAllData());
	}
}
?>