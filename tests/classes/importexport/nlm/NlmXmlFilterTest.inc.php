<?php

/**
 * @file tests/classes/importexport/nlm30/NlmXmlFilterTest.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NlmXmlFilterTest
 * @ingroup tests_classes_importexport_nlm
 *
 * @brief Basic test class for filters that handle NLM XML.
 */

import('lib.pkp.tests.PKPTestCase');

import('lib.pkp.classes.core.PKPRouter');
import('lib.pkp.classes.core.PKPRequest');

class NlmXmlFilterTest extends PKPTestCase {
	protected
		// Define a fake assoc object for testing.
		$assocId = 999999,
		$assocType = 0xFFFFFF,
		$citationDao;

	//
	// Implement template methods from PKPTestCase
	//
	protected function setUp() {
		$application =& PKPApplication::getApplication();
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$request =& $application->getRequest();
		if (is_null($request->getRouter())) {
			$router = new PKPRouter();
			$request->setRouter($router);
		}

		// Instantiate the citation DAO and make sure we have no left-overs
		// from previous unsuccessful tests.
		$this->citationDao = DAORegistry::getDAO('CitationDAO');
		$this->citationDao->deleteObjectsByAssocId($this->assocType, $this->assocId);
	}

	public function tearDown() {
		// Delete the test citations.
		$this->citationDao->deleteObjectsByAssocId($this->assocType, $this->assocId);
	}


	//
	// Protected helper methods
	//
	/**
	 * Return the CitationDAO
	 * @return CitationDAO
	 */
	protected function &getCitationDao() {
		return $this->citationDao;
	}

	/**
	 * Instantiate a minimal mock submission for testing.
	 * @return Submission
	 */
	protected function &getTestSubmission() {
		import('lib.pkp.classes.submission.Submission');
		$mockSubmission =&
				$this->getMock('Submission', array('getAssocType'));
		$mockSubmission->expects($this->any())
		               ->method('getAssocType')
		               ->will($this->returnValue($this->assocType));
		$mockSubmission->setId($this->assocId);
		return $mockSubmission;
	}

	/**
	 * Inject the given meta-data into a new citation object.
	 * @param $metadataDescription MetadataDescription
	 * @return Citation
	 */
	protected function &getCitation($citationDescription) {
		// Instantiate the citation and inject the meta-data.
		import('lib.pkp.classes.citation.Citation');
		$citation = new Citation('raw citation');
		$citation->setAssocType($this->assocType);
		$citation->setAssocId($this->assocId);
		$citation->injectMetadata($citationDescription);
		return $citation;
	}

	protected function normalizeAndCompare($nlmXml, $expectedFile) {
		// Normalize the output.
		$domDocument = new DOMDocument();
		$domDocument->preserveWhiteSpace = false;
		$domDocument->formatOutput = true;
		$domDocument->loadXML($nlmXml);
		$nlmXml = $domDocument->saveXML($domDocument->documentElement);

		// Compare with the expected result.
		self::assertStringEqualsFile($expectedFile, $nlmXml);
	}
}
?>
