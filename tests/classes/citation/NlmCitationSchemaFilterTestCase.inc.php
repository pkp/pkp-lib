<?php

/**
 * @file tests/config/NlmCitationSchemaFilterTestCase.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NlmCitationSchemaFilterTestCase
 * @ingroup tests_classes_citation
 *
 * @brief Base class for all citation parser and lookup service implementation tests.
 */

// $Id$

import('tests.PKPTestCase');
import('metadata.nlm.NlmNameSchema');
import('metadata.nlm.NlmCitationSchema');
import('metadata.MetadataDescription');

abstract class NlmCitationSchemaFilterTestCase extends PKPTestCase {
	const TEST_ALL_CITATIONS = false;

	//
	// Protected helper methods
	//
	/**
	 * Test a given NLM citation filter with an array of test data.
	 * @param $citationFilterTests array test data
	 * @param $filter NlmCitationSchemaFilter
	 */
	protected function assertNlmCitationSchemaFilter($citationFilterTests, $filter) {
		// Execute the filter for all test citations and check the result
		foreach($citationFilterTests as $citationFilterTestIndex => $citationFilterTest) {
			// Transform citation description arrays into citation descriptions (if any);
			foreach(array('testInput', 'testOutput') as $testDataType) {
				if (is_array($citationFilterTest[$testDataType])) {
					$citationFilterTest[$testDataType] =&
							$this->instantiateNlmCitationDescription($citationFilterTest[$testDataType]);
				}
			}

			// Execute the filter with the test description/raw citation
			$testInput =& $citationFilterTest['testInput'];
			$testOutput =& $filter->execute($testInput);

			// Prepare an error message
			if (is_string($testInput)) {
				// A raw citation or other easy-to-display test input.
				$errorMessage = "Error in test #$citationFilterTestIndex: '$testInput'.";
			} else {
				// The test input cannot be easily rendered.
				$errorMessage = "Error in test #$citationFilterTestIndex.";
			}

			// The citation filter should return a result
			self::assertNotNull($testOutput, $errorMessage);

			// Test whether the returned result coincides with the expected result
			self::assertEquals($citationFilterTest['testOutput'], $testOutput, $errorMessage);
		}
	}

	/**
	 * Simulate a web service error
	 * @param $paramenters array parameters for the citation service
	 */
	protected function assertWebServiceError($parameters = array()) {
		// Mock ParscitCitationParserService->callWebService()
		$mockCPService =&
				$this->getMock($this->getCitationServiceName(), array('callWebService'));

		// If we have parameters for the parser than configure it now
		if (!empty($parameters)) {
			foreach($parameters as $parameterName => $parameterValue) {
				$setter = 'set'.ucfirst($parameterName);
				$mockCPService->$setter($parameterValue);
			}
		}

		// Set up the callWebService() method
		// to simulate an error condition (=return null)
		$mockCPService->expects($this->once())
		              ->method('callWebService')
		              ->will($this->returnValue(null));

		// Call the SUT
		$citation = new Citation(METADATA_GENRE_JOURNALARTICLE, 'rawCitation');
		$mockCPService->parseInternal('rawCitation', $citation);
		self::assertNull($citation);
	}

	//
	// Private helper methods
	//
	/**
	 * Instantiate an NLM citation description from an array.
	 * @param $citationArray array
	 * @return MetadataDescription
	 */
	private function &instantiateNlmCitationDescription(&$citationArray) {
		static $personGroups = array(
			'person-group[@person-group-type="author"]' => ASSOC_TYPE_AUTHOR,
			'person-group[@person-group-type="editor"]' => ASSOC_TYPE_EDITOR
		);

		// Replace the authors and editors arrays with NLM name descriptions
		foreach($personGroups as $personGroup => $personAssocType) {
			if (isset($citationArray[$personGroup])) {
				$citationArray[$personGroup] =&
						$this->instantiateNlmNameDescriptions($citationArray[$personGroup], $personAssocType);
			}
		}

		// Instantiate the NLM citation description
		$nlmCitationSchema = new NlmCitationSchema();
		$citationDescription = new MetadataDescription($nlmCitationSchema, ASSOC_TYPE_CITATION);
		self::assertTrue($citationDescription->setStatements($citationArray));

		return $citationDescription;
	}

	/**
	 * Instantiate an NLM name description from an array.
	 * @param $personArray array
	 * @param $assocType integer
	 * @return MetadataDescription
	 */
	private function &instantiateNlmNameDescriptions(&$personArray, $assocType) {
		$nlmNameSchema = new NlmNameSchema();
		$personDescriptions = array();
		foreach ($personArray as $key => $person) {
			// Create a new NLM name description and fill it
			// with the values from the test array.
			$personDescription = new MetadataDescription($nlmNameSchema, $assocType);
			self::assertTrue($personDescription->setStatements($person));

			// Add the result to the descriptions list
			$personDescriptions[$key] = $personDescription;
		}
		return $personDescriptions;
	}
}
?>
