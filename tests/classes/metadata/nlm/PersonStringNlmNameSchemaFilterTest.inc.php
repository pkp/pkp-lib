<?php

/**
 * @file tests/plugins/metadata/nlm30/PersonStringNlmNameSchemaFilterTest.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PersonStringNlmNameSchemaFilterTest
 * @ingroup tests_classes_metadata_nlm
 * @see PersonStringNlmNameSchemaFilter
 *
 * @brief Tests for the PersonStringNlmNameSchemaFilter class.
 */

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.plugins.metadata.nlm30.filter.PersonStringNlmNameSchemaFilter');

class PersonStringNlmNameSchemaFilterTest extends PKPTestCase {
	/**
	 * @covers PersonStringNlmNameSchemaFilter
	 * @covers NlmPersonStringFilter
	 */
	public function testExecuteWithSinglePersonString() {
		$personArgumentArray = array(
			array('MULLER', false, false),                           // surname
			array('His Excellency B.C. Van de Haan', true, false),   // initials prefix surname + title
			array('Mrs. P.-B. von Redfield-Brownfox', true, false),  // initials prefix double-surname + title
			array('Professor K-G. Brown, MA, MSc.', true, true),     // initials surname + title + degree
			array('IFC Peterberg', false, false),                    // initials surname
			array('Peters, H. C.', false, false),                    // surname, initials
			array('Peters HC', false, false),                        // surname initials
			array('Yu, QK', false, false),                           // short surname, initials
			array('Yu QK', false, false),                            // short surname initials
			array('Sperling, Hans P.', false, false),                // surname, firstname initials
			array('Hans P. Sperling', false, false),                 // firstname initials surname
			array('Sperling, Hans Peter B.', false, false),          // surname, firstname middlename initials
			array('Hans Peter B. Sperling', false, false),           // firstname middlename initials surname
			array('Peters, Herbert', false, false),                  // surname, firstname
			array('Prof. Dr. Bernd Rutherford', true, false),        // firstname surname + title
			array('Her Honour Ruth-Martha Rheinfels', true, false),  // double-firstname surname + title
			array('Sperling, Hans Peter', false, false),             // surname, firstname middlename
			array('Hans Peter Sperling', false, false),              // firstname middlename surname
			array('Adelshausen III, H. (Gustav) von', false, false), // surname suffix, initials (firstname) prefix
			array('Adelshausen, (Gustav)', false, false),            // ibid.
			array('Gustav.Adelshausen', false, false),               // firstname.lastname (for ParaCite support)
			array('# # # Greenberg # # #', false, false),            // catch-all
		);
		$expectedResults = array(
			array(null, null, null, 'Muller'),
			array('His Excellency', array('B', 'C'), 'Van de', 'Haan'),
			array('Mrs.', array('P','B'), 'von', 'Redfield-Brownfox'),
			array('Professor - MA; MSc', array('K', 'G'), null, 'Brown'),
			array(null, array('I', 'F', 'C'), null, 'Peterberg'),
			array(null, array('H', 'C'), null, 'Peters'),
			array(null, array('H', 'C'), null, 'Peters'),
			array(null, array('Q', 'K'), null, 'Yu'),
			array(null, array('Q', 'K'), null, 'Yu'),
			array(null, array('Hans', 'P'), null, 'Sperling'),
			array(null, array('Hans', 'P'), null, 'Sperling'),
			array(null, array('Hans', 'Peter', 'B'), null, 'Sperling'),
			array(null, array('Hans', 'Peter', 'B'), null, 'Sperling'),
			array(null, array('Herbert'), null, 'Peters'),
			array('Prof. Dr.', array('Bernd'), null, 'Rutherford'),
			array('Her Honour', array('Ruth-Martha'), null, 'Rheinfels'),
			array(null, array('Hans', 'Peter'), null, 'Sperling'),
			array(null, array('Hans', 'Peter'), null, 'Sperling'),
			array('III', array('Gustav', 'H'), 'von', 'Adelshausen'),
			array(null, array('Gustav'), null, 'Adelshausen'),
			array(null, array('Gustav'), null, 'Adelshausen'),
			array(null, null, null, '# # # Greenberg # # #'),
		);

		$personStringNlmNameSchemaFilter = new PersonStringNlmNameSchemaFilter(ASSOC_TYPE_AUTHOR);
		foreach($personArgumentArray as $testNumber => $personArguments) {
			$personStringNlmNameSchemaFilter->setFilterTitle($personArguments[1]);
			$personStringNlmNameSchemaFilter->setFilterDegrees($personArguments[2]);
			$personDescription =& $personStringNlmNameSchemaFilter->execute($personArguments[0]);
			$this->assertPerson($expectedResults[$testNumber], $personDescription, $testNumber);
		}
	}

	/**
	 * @covers PersonStringNlmNameSchemaFilter
	 * @covers NlmPersonStringFilter
	 * @depends testExecuteWithSinglePersonString
	 */
	public function testExecuteWithMultiplePersonsStrings() {
		$personsString = 'MULLER:IFC Peterberg:Peters HC:Yu QK:Hans Peter B. Sperling:et al';
		$expectedResults = array(
			array(null, null, null, 'Muller'),
			array(null, array('I', 'F', 'C'), null, 'Peterberg'),
			array(null, array('H', 'C'), null, 'Peters'),
			array(null, array('Q', 'K'), null, 'Yu'),
			array(null, array('Hans', 'Peter', 'B'), null, 'Sperling'),
		);

		$personStringNlmNameSchemaFilter = new PersonStringNlmNameSchemaFilter(ASSOC_TYPE_AUTHOR, PERSON_STRING_FILTER_MULTIPLE);
		$personDescriptions =& $personStringNlmNameSchemaFilter->execute($personsString);
		// The last description should be an 'et-al' string
		self::assertEquals(PERSON_STRING_FILTER_ETAL, array_pop($personDescriptions));
		foreach($personDescriptions as $testNumber => $personDescription) {
			$this->assertPerson($expectedResults[$testNumber], $personDescription, $testNumber);
		}

		// Test again, this time with title and degrees
		$personsString = 'Dr. MULLER; IFC Peterberg; Prof. Peters HC, MSc.; Yu QK;Hans Peter B. Sperling; etal';
		$expectedResults = array(
			array('Dr.', null, null, 'Muller'),
			array(null, array('I', 'F', 'C'), null, 'Peterberg'),
			array('Prof. - MSc', array('H', 'C'), null, 'Peters'),
			array(null, array('Q', 'K'), null, 'Yu'),
			array(null, array('Hans', 'Peter', 'B'), null, 'Sperling'),
		);

		$personStringNlmNameSchemaFilter->setFilterTitle(true);
		$personStringNlmNameSchemaFilter->setFilterDegrees(true);
		$personDescriptions =& $personStringNlmNameSchemaFilter->execute($personsString);
		// The last description should be an 'et-al' string
		self::assertEquals(PERSON_STRING_FILTER_ETAL, array_pop($personDescriptions));
		foreach($personDescriptions as $testNumber => $personDescription) {
			$this->assertPerson($expectedResults[$testNumber], $personDescription, $testNumber);
		}

		// Test whether Vancouver style comma separation works correctly
		$personsString = 'Peterberg IFC, Peters HC, Sperling HP';
		$expectedResults = array(
			array(null, array('I', 'F', 'C'), null, 'Peterberg'),
			array(null, array('H', 'C'), null, 'Peters'),
			array(null, array('H', 'P'), null, 'Sperling')
		);
		$personStringNlmNameSchemaFilter->setFilterTitle(false);
		$personStringNlmNameSchemaFilter->setFilterDegrees(false);
		$personDescriptions =& $personStringNlmNameSchemaFilter->execute($personsString);
		foreach($personDescriptions as $testNumber => $personDescription) {
			$this->assertPerson($expectedResults[$testNumber], $personDescription, $testNumber);
		}

		// Single name strings should not be cut when separated by comma.
		$personsString = 'Willinsky, John';
		$expectedResult = array(null, array('John'), null, 'Willinsky');
		$personDescriptions =& $personStringNlmNameSchemaFilter->execute($personsString);
		$this->assertEquals(1, count($personDescriptions));
		$this->assertPerson($expectedResult, $personDescriptions[0], $testNumber);

		// Test APA style author tokenization.
		$singleAuthor = array(1 => 'Berndt, T. J.');
		$twoAuthors = array(2 => 'Wegener-Prent, D. T., & Petty, R. E.');
		$threeToSevenAuthors = array(6 => 'Kernis Wettelberger, M. H., Cornell, D. P., Sun, C. R., Berry, A., Harlow, T., & Bach, J. S.');
		$moreThanSevenAuthors = array(7 => 'Miller, F. H., Choi, M.J., Angeli, L. L., Harland, A. A., Stamos, J. A., Thomas, S. T., . . . Rubin, L. H.');
		$singleEditor = array(1 => 'A. Editor');
		$twoEditors = array(2 => 'A. Editor-Double & B. Editor');
		$threeToSevenEditors = array(6 => 'M.H. Kernis Wettelberger, D. P. Cornell, C.R. Sun, A. Berry, T. Harlow & J.S. Bach');
		$moreThanSevenEditors = array(7 => 'F. H. Miller, M. J. Choi, L. L. Angeli, A. A. Harland, J. A. Stamos, S. T. Thomas . . . L. H. Rubin');
		foreach(array($singleAuthor , $twoAuthors, $threeToSevenAuthors, $moreThanSevenAuthors,
				$singleEditor, $twoEditors, $threeToSevenEditors, $moreThanSevenEditors) as $test) {
			$expectedNumber = key($test);
			$testString = current($test);
			$personDescriptions =& $personStringNlmNameSchemaFilter->execute($testString);
			$this->assertEquals($expectedNumber, count($personDescriptions), 'Offending string: '.$testString);
		}
	}

	/**
	 * Test a given person description against an array of expected results
	 * @param $expectedResultArray array
	 * @param $personDescription MetadataDescription
	 * @param $testNumber integer The test number for debugging purposes
	 */
	private function assertPerson($expectedResultArray, $personDescription, $testNumber) {
		self::assertEquals($expectedResultArray[0], $personDescription->getStatement('suffix'), "Wrong suffix for test $testNumber.");
		self::assertEquals($expectedResultArray[1], $personDescription->getStatement('given-names'), "Wrong given-names for test $testNumber.");
		self::assertEquals($expectedResultArray[2], $personDescription->getStatement('prefix'), "Wrong prefix for test $testNumber.");
		self::assertEquals($expectedResultArray[3], $personDescription->getStatement('surname'), "Wrong surname for test $testNumber.");
	}
}
?>
