<?php

/**
 * @file tests/functional/pages/submission/FunctionalSubmissionBaseTestCase.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FunctionalSubmissionBaseTestCase
 * @ingroup tests_functional_pages_submission
 *
 * @brief Test the submission process.
 */


import('lib.pkp.tests.WebTestCase');

abstract class FunctionalSubmissionBaseTestCase extends WebTestCase {

	/**
	 * @see WebTestCase::getAffectedTables()
	 */
	protected function getAffectedTables() {
		return array(
			'submissions', 'submission_settings', 'submission_files',
			'submission_file_settings', 'submission_artwork_files',
			'controlled_vocab_entries', 'controlled_vocab_entry_settings', 'controlled_vocabs',
			'event_log', 'event_log_settings', 'item_views', 'notes', 'notifications',
			'notification_settings', 'sessions', 'stage_assignments', 'authors', 'author_settings',
			'users'
		);
	}

	/**
	 * Submit a submission.
	 */
	protected function submitSubmission() {
		$this->logAuthorIn();

		$submissionPage = self::$baseUrl . '/index.php/publicknowledge/submission/wizard/';

		//
		// First submission page.
		//
		$this->verifyAndOpen($submissionPage . '1');
		$this->waitForElementPresent('css=button.submitFormButton');

		$this->doActionsOnStep1();

		// Accept submission conditions.
		$checkboxId = 0;
		while ($this->isElementPresent("checklist-$checkboxId")) {
			$this->check("checklist-$checkboxId");
			$checkboxId++;
		}

		$this->clickAndWait('css=button.submitFormButton');

		//
		// Second submission page.
		//
		$this->waitForLocation($submissionPage . '2*');
		$this->waitForElementPresent('css=div.plupload_buttons');

		// We should now have the submission ID in the URL.
		$url = $this->getLocation();
		$matches = null;
		String::regexp_match_get('/submissionId=([0-9]+)#/', $url, $matches);
		self::assertTrue(count($matches) == 2);
		$submissionId = $matches[1];
		self::assertTrue(is_numeric($submissionId));
		$submissionId = (integer)$submissionId;

		// Close the upload file modal.
		$this->click("id=cancelButton");

		// Save the second step without uploading a file.
		$this->waitForElementNotPresent('css=div.plupload_buttons');
		$this->click('css=button.submitFormButton');

		//
		// Third submission page.
		//
		$this->waitForElementPresent('css=button.submitFormButton');

		$title = 'test book';
		$this->waitForElementPresent("css=[id^=title]");
		$this->type("css=[id^=title]", $title);

		$this->verifyElementPresent('css=iframe[id^=abstract]');
		if ($this->verified()) {
			// TinyMCE hack.
			$jsScript = "selenium.browserbot.getCurrentWindow().document".
			            ".querySelector('iframe[id^=abstract]').contentDocument.body.innerHTML = ".
			            "'$title abstract'";
			$this->getEval($jsScript);
		} else {
			$this->type('css=[id^=abstract]', $title . ' abstract');
		}

		$this->click("css=#submitStep3Form .submitFormButton");
		$this->waitForElementPresent($selector = '//span[text()=\'OK\']/..');
		$this->click($selector);

		$authorDashboardLink = self::$baseUrl . '/index.php/publicknowledge/authorDashboard/submission/' . $submissionId;
		$this->waitForElementPresent("css=a[href='" . $authorDashboardLink . "']");

		$this->assertTrue(is_int($submissionId));

		return $submissionId;
	}

	/**
	 * Do any application specific actions on step 1.
	 * The page is already loaded, don't need to check for it.
	 */
	abstract protected function doActionsOnStep1();
}
?>
