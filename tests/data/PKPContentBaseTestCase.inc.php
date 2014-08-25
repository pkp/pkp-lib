<?php

/**
 * @file tests/data/PKPContentBaseTestCase.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPContentBaseTestCase
 * @ingroup tests_data
 *
 * @brief Data build suite: Base class for content creation tests (PKP base)
 */

import('lib.pkp.tests.WebTestCase');

abstract class PKPContentBaseTestCase extends WebTestCase {
	/**
	 * Handle any section information on submission step 1
	 * @return string
	 */
	abstract protected function _handleSection($data);

	/**
	 * Get the number of items in the default submission checklist
	 * @return int
	 */
	abstract protected function _getChecklistLength();

	/**
	 * Create a submission with the supplied data.
	 * @param $data array Associative array of submission information
	 */
	protected function createSubmission($data) {
		// Check that the required parameters are provided
		foreach (array(
			'title',
		) as $paramName) {
			$this->assertTrue(isset($data[$paramName]));
		}

		$data = array_merge(array(
			'file' => getenv('DUMMYFILE'),
			'fileTitle' => $data['title'],
			'keywords' => array(),
			'additionalAuthors' => array(),
		), $data);

		// Find the "start a submission" button
		$this->clickAndWait('link=New Submission');

		// Permit the subclass to handle any series/section data
		$this->_handleSection($data);

		// Check the default checklist items.
		for ($i=0; $i<$this->_getChecklistLength(); $i++) $this->click('id=checklist-' . ($i+1));
		$this->clickAndWait('css=input.button.defaultButton');

		// Page 2: Upload
		$this->uploadFile($data['file']);
		$this->clickAndWait('css=input.button.defaultButton');

		// Page 3
		$this->type('id=title', $data['title']);
		if (isset($data['abstract'])) $this->typeTinyMCE('abstract', $data['abstract']);
		$this->type('id=subject', implode($data['keywords'], ';'));

		foreach ($data['additionalAuthors'] as $authorData) {
			$this->addAuthor($authorData);
		}
		$this->clickAndWait('css=input.button.defaultButton');

		// Page 4
		$this->clickAndWait('css=input.button.defaultButton');

		// Page 5
		$this->clickAndWait('css=input.button.defaultButton');
	}

	/**
	 * Add an author to the submission's author list.
	 * @param $data array
	 */
	protected function addAuthor($data) {
		// Check that the required parameters are provided
		foreach (array(
			'firstName', 'lastName', 'email', 'country',
		) as $paramName) {
			$this->assertTrue(isset($data[$paramName]));
		}

		$data = array_merge(array(
			'role' => 'Author',
		), $data);

		$this->clickAndWait('//input[@value=\'Add Author\']');
		$this->type('css=input:last[id$=firstName]', $data['firstName']);
		$this->type('css=input:last[id$=lastName]', $data['lastName']);
		$this->select('css=select:last[id$=country]', $data['country']);
		$this->type('css=input:last[id$=email]', $data['email']);
		if (isset($data['affiliation'])) $this->type('css=textarea:last[id$=affiliation]', $data['affiliation']);
	}

	/**
	 * Record an editorial decision
	 * @param $decision string
	 */
	protected function recordEditorialDecision($decision) {
		$this->select('name=decision', 'label=' . $this->escapeJS($decision));
		$this->chooseOkOnNextConfirmation();
		$this->clickAndWait('//input[@value=\'Record Decision\']');
		$this->getConfirmation(); // Consume pop-up
	}

	/**
	 * Assign and notify a reviewer.
	 * @param $username string
	 * @param $name string
	 */
	function assignReviewer($username, $name) {
		$this->clickAndWait('link=Select Reviewer');
		$this->clickAndWait('//td/a[contains(text(),\'' . $name . '\')]/../..//a[text()=\'Assign\']');

		// Find the last "notify" icon in the reviewer list
		$this->clickAndWait('xpath=(//div[@id=\'peerReview\']//table)[last()]//a/img/..');

		// Notify.
		$this->clickAndWait('css=input.button.defaultButton');
	}

	/**
	 * Log in as a reviewer and perform a review.
	 * @param $username string
	 * @param $password string (or null to presume twice-username)
	 * @param $title string
	 * @param $recommendation string
	 * @param $comments string optional
	 */
	function performReview($username, $password, $title, $recommendation, $comments = 'Here are my review comments.') {
		if ($password===null) $password = $username . $username;
		$this->logIn($username, $password);
		$this->clickAndWait('css=a:contains(\'' . substr($title, 0, 30) . '\')');

		$this->clickAndWait('//a[text()=\'Will do the review\']');
		$this->clickAndWait('css=input.button.defaultButton');

		$this->clickAndWait('css=img[alt=\'Comment\']');
		$this->waitForPopUp('Comments', 30000);
		$this->selectWindow('name=Comments');
		$this->typeTinyMCE('authorComments', $comments);
		$this->clickAndWait('name=save');
		$this->close();
		$this->selectWindow(null);
		sleep(2); // Wait for page reload
		$this->waitForElementPresent('css=[name=recommendation]');
		$this->select('css=[name=recommendation]', 'label=' . $this->escapeJS($recommendation));
		$this->waitForElementPresent('//input[@value=\'Submit Review To Editor\']');
		$this->chooseOkOnNextConfirmation();
		$this->clickAndWait('//input[@value=\'Submit Review To Editor\']');
		$this->getConfirmation(); // Consume pop-up
		$this->logOut();
	}

	protected function escapeJS($value) {
		return str_replace('\'', '\\\'', $value);
	}
}

?>
