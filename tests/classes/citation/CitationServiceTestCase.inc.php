<?php

/**
 * @file tests/config/CitationServiceTestCase.inc.php
 *
 * Copyright (c) 2003-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CitationServiceTestCase
 * @ingroup tests_classes_citation
 * @see CitationParserServiceTestCase
 * @see CitationLookupServiceTestCase
 *
 * @brief Base class for all citation parser and lookup service implementation tests.
 */

// $Id$

import('tests.PKPTestCase');
import('citation.Citation');
import('submission.PKPAuthor');

abstract class CitationServiceTestCase extends PKPTestCase {
	const TEST_ALL_CITATIONS = false;

	private $_citationServiceName;

	/**
	 * get the citationServiceName
	 * @return string
	 */
	protected function getCitationServiceName() {
		return $this->_citationServiceName;
	}

	/**
	 * set the citationServiceName
	 * @param $citationServiceName string
	 */
	protected function setCitationServiceName($citationServiceName) {
		$this->_citationServiceName = $citationServiceName;
	}

	/**
	 * Return a configured service instance
	 * @param $parameters array service configuration parameters
	 * @return CitationService
	 */
	protected function &getCitationServiceInstance($parameters = array()) {
		// Instantiate the service
		$citationServiceName = $this->getCitationServiceName();
		$citationServiceInstance = new $citationServiceName();

		// If we have parameters for the citation service than configure it now
		if (!empty($parameters)) {
			foreach($parameters as $parameterName => $parameterValue) {
				$setter = 'set'.ucfirst($parameterName);
				$citationServiceInstance->$setter($parameterValue);
			}
		}

		return $citationServiceInstance;
	}

	/**
	 * Test a given citation service with an array of test data.
	 * @param $testCitations array test data
	 * @param $citationService CitationService
	 */
	protected function assertCitationService($citationsServiceTests, $parameters = array()) {
		// Instantiate the citation service
		$citationService =& $this->getCitationServiceInstance($parameters);

		// Call the citation service for all test citations and check the result
		foreach($citationsServiceTests as $citationServiceTestIndex => $citationsServiceTest) {
			$expectedResult = $citationsServiceTest['expectedResult'];

			// Replace the authors array with PKPAuthor objects
			if (isset($expectedResult['authors'])) {
				foreach ($expectedResult['authors'] as $key => $author) {
					// Create a new PKPAuthor object and fill it with the values
					// from the test array.
					$newAuthor = new PKPAuthor();
					foreach($author as $authorField => $authorValue) {
						$setter = 'set'.ucfirst($authorField);
						$newAuthor->$setter($authorValue);
					}

					// Replace the array with the object
					$expectedResult['authors'][$key] = $newAuthor;
				}
			}

			// Execute the citation service with our test citation
			$testCitation = $citationsServiceTest['testCitation'];

			// Citation parser service
			if ($citationService instanceof CitationParserService) {
				// We get a raw citation string as parser test data
				$citation = new Citation(METADATA_GENRE_UNKNOWN, $testCitation);
				$parsedOrLookedUpCitation =& $citationService->parse($citation);
				$errorMessage = "Error in citation #$citationServiceTestIndex: '$testCitation'.";

			// Citation lookup service
			} elseif ($citationService instanceof CitationLookupService) {
				// We get a citation object for lookup services
				$parsedOrLookedUpCitation =& $citationService->lookup($testCitation);
				$errorMessage = "Error in citation #$citationServiceTestIndex.";

			// Something we don't know
			} else {
				$this->fail('Unknown citation service type');
			}

			// The citation service should return a result
			self::assertNotNull($parsedOrLookedUpCitation, $errorMessage);

			// Test whether the returned result coincides with the expected result
			self::assertEquals($expectedResult, $parsedOrLookedUpCitation->getNonEmptyElementsAsArray(), $errorMessage);
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
}
?>
