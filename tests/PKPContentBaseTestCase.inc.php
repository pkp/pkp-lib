<?php

/**
 * @file tests/PKPContentBaseTestCase.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPContentBaseTestCase
 * @ingroup tests_data
 *
 * @brief Base class for content-based tests (PKP base)
 */

import('lib.pkp.tests.WebTestCase');

abstract class PKPContentBaseTestCase extends WebTestCase {
	/**
	 * Handle any section information on submission step 1
	 * @return string
	 */
	protected function _handleStep1($data) {
	}

	/**
	 * Handle any section information on submission step 3
	 * @return string
	 */
	protected function _handleStep3($data) {
	}

	/**
	 * Get the number of items in the default submission checklist
	 * @return int
	 */
	abstract protected function _getChecklistLength();

	/**
	 * Get the submission submission element's name
	 * @return string
	 */
	abstract protected function _getSubmissionElementName();

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
			'files' => array(
				array(
					'file' => null,
					'fileTitle' => $data['title']
				)
			),
			'keywords' => array(),
			'additionalAuthors' => array(),
		), $data);

		// Find the "start a submission" button
		$this->waitForElementPresent('//span[starts-with(., \'Start a New Submission\')]/..');
		$this->click('//span[starts-with(., \'Start a New Submission\')]/..');

		// Check the default checklist items.
		$this->waitForElementPresent('id=checklist-0');
		for ($i=0; $i<$this->_getChecklistLength(); $i++) $this->click('id=checklist-' . $i);

		// Permit the subclass to handle any series/section data
		$this->_handleStep1($data);

		$this->click('css=[id^=submitFormButton-]');

		// Page 2: File wizard
		$this->waitForElementPresent($selector = 'id=cancelButton');
		$this->click($selector); // Thanks but no thanks
		foreach ($data['files'] as $file) {
			if (!isset($file['file'])) $file['file'] = null;
			$this->click('css=[id^=component-grid-files-submission-submissionwizardfilesgrid-addFile-button-]');
			$this->uploadWizardFile($file['fileTitle'], $file['file']);
		}
		sleep(1); // Occasional race conditions in travis
		$this->waitForElementPresent('//span[text()=\'Save and continue\']/..');
		$this->click('//span[text()=\'Save and continue\']/..');

		// Page 3
		sleep(1); // Occasional race conditions in travis
		$this->waitForElementPresent('css=[id^=title-]');
		$this->type('css=[id^=title-]', $data['title']);
		if (isset($data['abstract'])) $this->typeTinyMCE('abstract', $data['abstract']);
		foreach ($data['keywords'] as $tag) $this->addTag('keyword', $tag);

		foreach ($data['additionalAuthors'] as $authorData) {
			$this->addAuthor($authorData);
		}
		// Permit the subclass to handle any extra step 3 actions
		$this->_handleStep3($data);

		// Finish
		$this->waitForElementPresent('//span[text()=\'Finish Submission\']/..');
		$this->click('//span[text()=\'Finish Submission\']/..');
		$this->waitForText('css=div.pkp_controllers_modal_titleBar > h2', 'Confirm');
		$this->waitForElementPresent("//span[text()='OK']/..");
		$this->click("//span[text()='OK']/..");
		$this->waitForElementPresent('//h2[contains(text(), \'Submission complete\')]');
		$this->waitJQuery();
	}

	/**
	 * Upload a file via the file wizard.
	 * @param $fileTitle string
	 * @param $file string (Null to use dummy file)
	 */
	protected function uploadWizardFile($fileTitle, $file = null) {
		if (!$file) {
			// Generate a file to use using the DUMMY_PDF env var.
			$dummyfile = getenv('DUMMY_PDF');
			$file = sys_get_temp_dir() . '/' . preg_replace('/[^a-z0-9\.]/', '', strtolower($fileTitle)) . '.pdf';
			copy($dummyfile, $file);
		}
		$this->waitForElementPresent('id=genreId');
		$this->select('id=genreId', 'label=' . $this->_getSubmissionElementName());
		$this->uploadFile($file);
		$this->click('id=continueButton');
		$this->waitForElementPresent('css=[id^=name-]');
		$this->type('css=[id^=name-]', $fileTitle);
		$this->runScript('$(\'#metadataForm\').valid();');
		$this->click('//span[text()=\'Continue\']/..');
		$this->waitJQuery();
		$this->waitForElementPresent($selector = '//span[text()=\'Complete\']/..');
		$this->click($selector);
		$this->waitJQuery();
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

		$this->click('css=[id^=component-grid-users-author-authorgrid-addAuthor-button-]');
		$this->waitForElementPresent('css=[id^=firstName-]');
		$this->type('css=[id^=firstName-]', $data['firstName']);
		$this->type('css=[id^=lastName-]', $data['lastName']);
		$this->select('id=country', $data['country']);
		$this->type('css=[id^=email-]', $data['email']);
		if (isset($data['affiliation'])) $this->type('css=[id^=affiliation-]', $data['affiliation']);
		$this->click('//label[text()=\'' . $this->escapeJS($data['role']) . '\']');
		$this->click('//span[text()=\'Save\']/..');
		$this->waitForElementNotPresent('css=.ui-widget-overlay');
		$this->waitJQuery();
	}

	/**
	 * Log in as an Editor and find the specified submission.
	 * @param $username string
	 * @param $password string (null to presume twice-username)
	 * @param $title string
	 */
	protected function findSubmissionAsEditor($username, $password = null, $title) {
		if ($password === null) $password = $username . $username;
		$this->logIn($username, $password);
		$this->waitForElementPresent('css=#dashboardTabs');
		$this->click('css=[name=active]');
		$this->waitForElementPresent('css=[id^=component-grid-submissions-activesubmissions-activesubmissionslistgrid-]');
		$this->scrollGridDown('activeSubmissionsListGridContainer');
		$xpath = '//span[contains(text(),' . $this->quoteXpath($title) .')]/../../..//a[contains(@id, "-stage-itemWorkflow-button-")]';
		$this->waitForElementPresent($xpath);
		$this->click($xpath);
	}

	protected function quoteXpath($string) {
		// Use an xpath concat to escape quotes in literals.
		// http://kushalm.com/the-perils-of-xpath-expressions-specifically-escaping-quotes
		return 'concat(\'' . strtr($this->escapeJS($string),
			array(
				'\\\'' => '\', "\'", \''
			)
		) . '\',\'\')';
	}

	/**
	 * Record an editorial decision
	 * @param $decision string
	 */
	protected function recordEditorialDecision($decision) {
		$this->waitForElementPresent('//span[text()=\'' . $this->escapeJS($decision) . '\']/..');
		$this->click('//span[text()=\'' . $this->escapeJS($decision) . '\']/..');
		$this->waitForElementPresent('//span[text()=\'Record Editorial Decision\']/..');
		$this->click('//span[text()=\'Record Editorial Decision\']/..');
		$this->waitForElementNotPresent('css=.ui-widget-overlay');
	}

	/**
	 * Assign a participant
	 * @param $role string
	 * @param $name string
	 */
	protected function assignParticipant($role, $name) {
		$this->waitForElementPresent('css=[id^=component-grid-users-stageparticipant-stageparticipantgrid-requestAccount-button-]');
		$this->click('css=[id^=component-grid-users-stageparticipant-stageparticipantgrid-requestAccount-button-]');
		$this->waitJQuery();
		$this->select('id=userGroupId', 'label=' . $this->escapeJS($role));
		$this->waitForElementPresent('//select[@name=\'userId\']//option[text()=\'' . $this->escapeJS($name) . '\']');
		$this->select('id=userId', 'label=' . $this->escapeJS($name));
		$this->click('//span[text()=\'OK\']/..');
		$this->waitForText('css=div.ui-pnotify-text', 'User added as a stage participant.');
		$this->waitJQuery();
	}

	/**
	 * Assign a reviewer.
	 * @param $username string
	 * @param $name string
	 */
	function assignReviewer($username, $name) {
		$this->waitForElementPresent('css=[id^=component-grid-users-reviewer-reviewergrid-addReviewer-button-]');
		$this->click('css=[id^=component-grid-users-reviewer-reviewergrid-addReviewer-button-]');
		$this->waitForElementPresent('css=[id^=reviewerId_input-]');
		$this->type('css=[id^=reviewerId_input-]', $username);
		$this->typeKeys('css=[id^=reviewerId_input-]', $username);

		$this->waitForElementPresent($selector = '//li[text()=\'' . $this->escapeJS($name) . '\']');
		$this->mouseOver($selector);
		$this->click($selector);

		$this->click('//span[text()=\'Add Reviewer\']/..');
		$this->waitForElementNotPresent('css=.ui-widget-overlay');
	}

	/**
	 * Log in as a reviewer and perform a review.
	 * @param $username string
	 * @param $password string (or null to presume twice-username)
	 * @param $title string
	 * @param $recommendation string Optional recommendation label
	 * @param $comments string optional Optional comment text
	 */
	function performReview($username, $password, $title, $recommendation = null, $comments = 'Here are my review comments.') {
		if ($password===null) $password = $username . $username;
		$this->logIn($username, $password);

		// Use an xpath concat to permit apostrophes to appear in titles
		// http://kushalm.com/the-perils-of-xpath-expressions-specifically-escaping-quotes
		$this->scrollGridDown('assignedSubmissionsListGridContainer');
		$xpath = '//span[contains(text(),' . $this->quoteXpath($title) .')]/../../..//a[contains(@id, "-stage-itemWorkflow-button-")]';
		$this->waitForElementPresent($xpath);
		$this->click($xpath);

		$this->waitForElementPresent('//span[text()=\'Accept Review, Continue to Step #2\']/..');
		$this->click('//span[text()=\'Accept Review, Continue to Step #2\']/..');

		$this->waitForElementPresent('//span[text()=\'Continue to Step #3\']/..');
		$this->click('//span[text()=\'Continue to Step #3\']/..');
		$this->waitForElementPresent('css=[id^=comments-]');
		$this->type('css=[id^=comments-]', $comments);

		if ($recommendation !== null) {
			$this->select('id=recommendation', 'label=' . $this->escapeJS($recommendation));
		}

		$this->waitForElementPresent('//span[text()=\'Submit Review\']/..');
		$this->click('//span[text()=\'Submit Review\']/..');
		$this->waitForElementPresent('//span[text()=\'OK\']/..');
		$this->click('//span[text()=\'OK\']/..');
		$this->waitForElementPresent('//h2[contains(text(), \'Review Submitted\')]');
		$this->waitJQuery();
		$this->logOut();
	}
}

?>
