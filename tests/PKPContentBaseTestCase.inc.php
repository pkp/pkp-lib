<?php

/**
 * @file tests/PKPContentBaseTestCase.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPContentBaseTestCase
 * @ingroup tests_data
 *
 * @brief Base class for content-based tests (PKP base)
 */

import('lib.pkp.tests.WebTestCase');

define('DUMMY_PDF', 0);
define('DUMMY_ZIP', 1);

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\Interactions\WebDriverActions;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverSelect;
use Facebook\WebDriver\Exception\NoSuchElementException;

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
	 * Get the submission submission element's name
	 * @return string
	 */
	abstract protected function _getSubmissionElementName();

	/**
	 * Create a submission with the supplied data.
	 * @param $data array Associative array of submission information
	 * @param $location string Whether or not the submission wll be created
	 *   from the frontend or backend
	 */
	protected function createSubmission($data, $location = 'frontend') {
		// Check that the required parameters are provided
		foreach (array(
			'title',
		) as $paramName) {
			$this->assertTrue(isset($data[$paramName]));
		}

		$data = array_merge(array(
			'files' => array(
				array(
					'file' => DUMMY_PDF,
					'fileTitle' => $data['title']
				)
			),
			'keywords' => array(),
			'additionalAuthors' => array(),
		), $data);

		// Find the "Make a New Submission" link
		if ($location == 'frontend') {
			$this->waitForElementPresent($selector='//a[contains(text(), \'Make a New Submission\')]');
		} else {
			$this->waitForElementPresent($selector='//a[contains(text(), \'New Submission\')]');
		}
		$this->click($selector);

		// Check the default checklist items.
		$this->waitForElementPresent('id=checklist-0');
		foreach (self::$driver->findElements(WebDriverBy::xpath('//input[starts-with(@id, "checklist-") and not (@checked)]')) as $element) {
			$element->click();
		}

		if (empty($data['submitterRole'])){
			$this->click('//input[@id=\'userGroupId\']');
		} else {
			$this->click('//input[@name=\'userGroupId\'][following-sibling::text()[position()=1][contains(.,\'' . $this->escapeJS($data['submitterRole']) . '\')]]');
		}

		$this->click('//input[@id=\'privacyConsent\']');

		// Permit the subclass to handle any series/section data
		$this->_handleStep1($data);

		$this->click('css=[id^=submitFormButton-]');

		// Page 2: File wizard
		$element = $this->waitForElementPresent($selector = 'id=cancelButton');
		sleep(2); // FIXME: Avoid occasional failures with the genre dropdown getting hit instead of cancel

		// Try to avoid ghost-popup-menu-intercepting-clicks at start of page 3
		$actions = new WebDriverActions(self::$driver);
		$actions->moveToElement($element)->perform();

		$this->click($selector); // Thanks but no thanks
		self::$driver->wait()->until(WebDriverExpectedCondition::invisibilityOfElementLocated(WebDriverBy::cssSelector('div.pkp_modal_panel')));

		foreach ($data['files'] as $file) {
			if (!isset($file['file'])) $file['file'] = DUMMY_PDF;
			$this->click('css=[id^=component-grid-files-submission-submissionwizardfilesgrid-addFile-button-]');
			$metadata = isset($file['metadata'])?$file['metadata']:array();
			$this->uploadWizardFile($file['fileTitle'], $file['file'], $metadata);
		}

		// Make sure the sidebar menus are not activated
		self::$driver->getMouse()->mouseMove($this->waitForElementPresent('//div[@class="pkp_site_name"]')->getCoordinates());

		$this->click('//form[@id=\'submitStep2Form\']//button[text()=\'Save and continue\']');

		// Page 3
		$this->waitForElementPresent('css=[id^=title-]');
		$this->type('css=[id^=title-]', $data['title']);
		if (isset($data['abstract'])) $this->typeTinyMCE('abstract', $data['abstract']);
		foreach ($data['keywords'] as $tag) $this->addTag('keyword', $tag);

		foreach ($data['additionalAuthors'] as $authorData) {
			$this->addAuthor($authorData);
		}
		// Permit the subclass to handle any extra step 3 actions
		$this->_handleStep3($data);
		$this->waitJQuery();
		$this->click('//form[@id=\'submitStep3Form\']//button[text()=\'Save and continue\']');

		// Page 4
		$this->waitJQuery();
		$this->click('//form[@id=\'submitStep4Form\']//button[text()=\'Finish Submission\']');
		$this->waitJQuery();
		$this->click('//a[text()=\'OK\']');
		$this->waitForElementPresent('//h2[contains(text(), \'Submission complete\')]');
		$this->waitJQuery();
	}

	/**
	 * Upload a file via the file wizard.
	 * @param $fileTitle string
	 * @param $file string|int Path to file to upload, or one of the DUMMY_ constants (default DUMMY_PDF)
	 * @param $metadata array Optional set of metadata for the upload
	 */
	protected function uploadWizardFile($fileTitle, $file = DUMMY_PDF, $metadata = array()) {
		if (is_numeric($file)) {
			// Determine which dummy file to use.
			switch($file) {
				case DUMMY_ZIP:
					$dummyfile = getenv('DUMMY_ZIP');
					$extension = 'zip';
					break;
				case DUMMY_PDF:
				default:
					$dummyfile = getenv('DUMMY_PDF');
					$extension = 'pdf';
			}
			$file = sys_get_temp_dir() . '/' . preg_replace('/[^a-z0-9\.]/', '', substr(strtolower($fileTitle),0,40)) . '.' . $extension;

			// Generate a copy of the file to use with a unique-ish filename.
			copy($dummyfile, $file);
		}

		// Provide defaults for metadata
		$metadata = array_merge(
			array(
				'genre' => $this->_getSubmissionElementName(),
			),
			$metadata
		);

		// Unpack pieces for later use outside $metadata
		$genreName = $metadata['genre'];
		unset($metadata['genre']);

		// Pick the genre and upload the file
		$this->waitForElementPresent('id=genreId');
		$this->select('id=genreId', "label=$genreName");
		$this->uploadFile($file);
		$this->click('//div[@class="pkp_modal_panel"]//button[@id="continueButton"]');

		// Enter the title into the metadata form
		$this->waitForElementPresent('css=[id^=name-]');
		$this->click('//fieldset[@id="fileMetaData"]//a[contains(@class,"pkpEditableToggle")]');
		$this->type('css=[id^=name-]', $fileTitle);

		// Enter remaining metadata into the form fields
		foreach ($metadata as $name => $value) {
			$this->type('css=[id^=' . $name . '-]', $value);
		}

		// Validate the form and finish
		self::$driver->executeScript('$("form[id^=uploadForm]").valid();');
		$this->click('css=[id=continueButton]');
		$this->waitForElementPresent('//h2[contains(text(), "File Added")]');
		$this->click('//button[@id="continueButton"]');
		self::$driver->wait()->until(WebDriverExpectedCondition::invisibilityOfElementLocated(WebDriverBy::cssSelector('div.pkp_modal_panel')));
	}

	/**
	 * Add an author to the submission's author list.
	 * @param $data array
	 */
	protected function addAuthor($data) {
		// Check that the required parameters are provided
		foreach (array(
			'givenName', 'familyName', 'email', 'country',
		) as $paramName) {
			$this->assertTrue(isset($data[$paramName]));
		}

		$data = array_merge(array(
			'role' => 'Author',
		), $data);

		$this->click('css=[id^=component-grid-users-author-authorgrid-addAuthor-button-]');
		$this->waitForElementPresent('css=[id^=givenName-]');
		$this->type('css=[id^=givenName-]', $data['givenName']);
		$this->type('css=[id^=familyName-]', $data['familyName']);
		$this->select('id=country', 'label=' . $data['country']);
		$this->type('css=[id^=email-]', $data['email']);
		if (isset($data['affiliation'])) $this->type('css=[id^=affiliation-]', $data['affiliation']);
		$this->click('//label[contains(.,\'' . $this->escapeJS($data['role']) . '\')]');
		$this->click('//button[text()=\'Save\']');
		self::$driver->wait()->until(WebDriverExpectedCondition::invisibilityOfElementLocated(WebDriverBy::cssSelector('div.pkp_modal_panel')));
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
		/*$this->click('css=[name=active]');
		$this->waitForElementPresent('//div[contains(text(),"All Active")]');*/
		$xpath = '//div[contains(text(),' . $this->quoteXpath($title) . ')]';
		$this->waitForElementPresent($xpath);
		$this->click($xpath);
	}

	/**
	 * Record an editorial decision
	 * @param $decision string
	 */
	protected function recordEditorialDecision($decision) {
		$this->click('//a[contains(.,\'' . $this->escapeJS($decision) . '\')]');
		if (in_array($decision, array('Accept Submission', 'Send To Production', 'Send to External Review'))) {
			sleep(2); // FIXME: Avoid missing modal
			$this->click('//button[contains(.,"Next:")]');
		}
		$this->click('//button[contains(.,\'Record Editorial Decision\')]');
		self::$driver->wait()->until(WebDriverExpectedCondition::invisibilityOfElementLocated(WebDriverBy::cssSelector('div.pkp_modal_panel')));
	}

	/**
	 * Record an editorial recommendation
	 * @param $recommendation string
	 */
	protected function recordEditorialRecommendation($recommendation) {
		$this->waitForElementPresent($selector='//a[@id[starts-with(., \'recommendation-button-\')]]');
		$this->click($selector);
		$this->waitForElementPresent($selector='id=recommendation');
		$this->select('id=recommendation', 'label=' . $this->escapeJS($recommendation));
		$this->waitForElementPresent($selector='//button[text()=\'Record Editorial Recommendation\']');
		$this->click($selector);
		self::$driver->wait()->until(WebDriverExpectedCondition::invisibilityOfElementLocated(WebDriverBy::cssSelector('div.pkp_modal_panel')));
	}

	/**
	 * Assign a participant
	 * @param $role string
	 * @param $name string
	 */
	protected function assignParticipant($role, $name, $recommendOnly = null) {
		sleep(2); // FIXME: Avoid occasional "element is not attached to the page document" errors
		$this->waitForElementPresent('css=[id^=component-grid-users-stageparticipant-stageparticipantgrid-requestAccount-button-]');
		$this->click('css=[id^=component-grid-users-stageparticipant-stageparticipantgrid-requestAccount-button-]');
		$this->waitForElementPresent($selector = '//select[@name="filterUserGroupId"]');
		$this->select($selector, 'label=' . $this->escapeJS($role));
		// Search by last name
		$names = explode(' ', $name);
		$this->waitForElementPresent($selector='//input[@id[starts-with(., \'namegrid-users-userselect-userselectgrid-\')]]');
		$this->type($selector, $names[1]);
		$this->click('//form[@id=\'searchUserFilter-grid-users-userselect-userselectgrid\']//button[@id[starts-with(., \'submitFormButton-\')]]');
		// Assume there is only one user with this last name and user group
		$this->waitForElementPresent($selector='//input[@name=\'userId\']');
		$this->click($selector);
		if ($recommendOnly) {
			$this->waitForElementPresent($selector='//input[@name=\'recommendOnly\']');
			$this->click($selector);
		}
		$this->click('//button[text()=\'OK\']');
		$this->waitForElementPresent('//div[@class="ui-pnotify-text" and text()="User added as a stage participant."]');
		self::$driver->wait()->until(WebDriverExpectedCondition::invisibilityOfElementLocated(WebDriverBy::cssSelector('div.pkp_modal_panel')));
	}

	/**
	 * Assign a reviewer.
	 * @param $name string
	 */
	function assignReviewer($name) {
		$this->waitJQuery();
		$this->click('css=[id^=component-grid-users-reviewer-reviewergrid-addReviewer-button-]');
		$this->waitJQuery();
		$this->waitForElementPresent('css=div.pkpListPanel--selectReviewer');
		$this->type('css=div.pkpListPanel--selectReviewer input.pkpListPanel__searchInput', $name);
		$this->waitForElementPresent($xpath='//div[contains(text(),' . $this->quoteXpath($name) . ')]');
		$this->click($xpath);
		$this->waitJQuery();
		$this->click('css=[id^=selectReviewerButton]');
		$this->waitJQuery();
		$this->click('//button[text()=\'Add Reviewer\']');
		self::$driver->wait()->until(WebDriverExpectedCondition::invisibilityOfElementLocated(WebDriverBy::cssSelector('div.pkp_modal_panel')));
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
		$xpath = '//div[normalize-space(text())=' . $this->quoteXpath($title) . ']';
		$this->waitForElementPresent($xpath);
		$this->click($xpath);


		$this->waitForElementPresent($selector='//button[text()=\'Accept Review, Continue to Step #2\']');
		sleep(2); // FIXME: Avoid occasional unchecked checkbox
		$this->click('//input[@id=\'privacyConsent\']');
		$this->click($selector);

		$this->waitForElementPresent($selector='//button[text()=\'Continue to Step #3\']');
		$this->click($selector);
		$this->waitForElementPresent('css=[id^=comments-]');
		$this->typeTinyMCE('comments', $comments);

		if ($recommendation !== null) {
			$this->select('id=recommendation', 'label=' . $this->escapeJS($recommendation));
		}

		$this->waitForElementPresent($selector='//button[text()=\'Submit Review\']');
		$this->click($selector);
		$this->waitForElementPresent($selector='link=OK');
		$this->click($selector);
		$this->waitForElementPresent('//h2[contains(text(), \'Review Submitted\')]');
		$this->logOut();
	}
}


