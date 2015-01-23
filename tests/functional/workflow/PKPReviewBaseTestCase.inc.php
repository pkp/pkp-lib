<?php

/**
 * @file tests/functional/workflow/PKPReviewBaseTestCase.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPReviewBaseTestCase
 * @ingroup tests_functional_workflow
 *
 * @brief Base class for testing the review process.
 */

import('lib.pkp.tests.functional.workflow.WorkflowBaseTestCase');

class PKPReviewBaseTestCase extends WorkflowBaseTestCase {

	/**
	 * Test performing a review.
	 */
	public function testPerformReview() {
		$this->open(self::$baseUrl);

		$emailLog = PluginRegistry::getPlugin('generic', 'emailloggerplugin'); 

		$submissionId = $this->findSubmissionAsEditor('dbarnes', null, $fullTitle = 'Lost Tracks: Buffalo National Park, 1909-1939');

		$this->sendToReview('External');
		$this->waitForElementPresent('css=a#participantToggle');
		// Check NOTIFICATION_TYPE_REVIEW_ROUND_STATUS
		$this->waitForInPlaceNotification($reviewRoundNotificationId = 'reviewRoundNotification_', 'Awaiting reviewers.'); 

		$this->assignParticipant('Author', 'Bob Barnetson');
		$this->assignReviewer('agallego', 'Adela Gallego');
		$this->waitForInPlaceNotification($reviewRoundNotificationId, 'Awaiting responses from reviewers.');

		$this->assertFalse($emailLog->exists(NOTIFICATION_TYPE_REVIEW_ASSIGNMENT, 'agallego@mailinator.com'), 
			'The reviewer received the review assignment notification email, but it shouldnt.');
		$this->assertTrue($emailLog->existsByAssoc(ASSOC_TYPE_SUBMISSION, $submissionId, 'agallego@mailinator.com', 
			null, 'You have been selected as a potential reviewer of the following submission.'));

		$this->logOut();

		$this->performReview('agallego', null, $fullTitle, null, 'A review comment.', $fullTitle, true);

		// Check emails.
		$failMessage = 'The reviewer comment email notification should not be sent to authors.';
		$this->assertFalse($emailLog->exists(NOTIFICATION_TYPE_REVIEWER_COMMENT, 'bbarnetson@mailinator.com'), $failMessage);
		$this->assertFalse($emailLog->exists(NOTIFICATION_TYPE_REVIEWER_COMMENT, 'jbrower@mailinator.com'), $failMessage);
		$this->assertTrue($emailLog->exists(NOTIFICATION_TYPE_REVIEWER_COMMENT, 'dbarnes@mailinator.com'),
			'The non authors stage participants did not received the reviewer comment email.');

		$this->assertTrue($emailLog->existsByAssoc(ASSOC_TYPE_SUBMISSION, $submissionId, 'rvaca@mailinator.com', 
			SUBMISSION_EMAIL_REVIEW_CONFIRM, 'Adela Gallego'));

		$this->logIn('agallego', 'agallegoagallego');	
	
		// Test that the task notification is removed from dashboard.
		$this->clickAndWait('link=Dashboard');
		$this->waitForElementPresent('css=[id^=component-grid-notifications-notificationsgrid-]');
		$this->assertTextNotPresent($fullTitle, 'The review task notification it is not removed from the tasks list');
		
		// Test that the submission is removed from the submission list.
		$this->click('//div[@id=\'dashboardTabs\']//a[text()=\'Submissions\']');
		$this->waitForElementPresent('css=[id^=component-grid-submissions-assignedsubmissions-assignedsubmissionslistgrid-]');
		$this->assertTextNotPresent($fullTitle, 'The submission it is not removed from the reviewer submissions list');

		$this->logOut();

		// Check the editor workflow.
		// Check for the all reviews in email. 
		$allReviewsInText = 'All reviews are in and a decision is needed in'; // notification.type.allReviewsIn 
		$this->assertTrue($emailLog->exists(NOTIFICATION_TYPE_ALL_REVIEWS_IN, 'dbarnes@mailinator.com', $allReviewsInText));
		
		// Check for the all reviews in dashboard task.
		$this->logIn('dbarnes', 'dbarnesdbarnes');
		$this->waitForElementPresent('css=[id^=component-grid-notifications-notificationsgrid-]');
		$this->assertTextPresent($allReviewsInText, 'The all reviews in notification is not present in Tasks.');
		
		// Check the review workflow page.
		$this->open(self::$baseUrl . '/index.php/publicknowledge/workflow/access/' . $submissionId);
		$this->waitForElementPresent('css=[id^=reviewRoundNotification_]');
		$this->waitForInPlaceNotification($reviewRoundNotificationId, 'New reviews ready.');
		$this->assertTextPresent($allReviewsInText, 'The all reviews in notification is not present in review round page.');

		// Request revision.
		$this->click('css=[id^=requestRevisions-button-]');
		$this->waitForElementPresent('css=[id^=component-grid-files-attachment-editorselectablereviewattachmentsgrid-]');
		$this->submitAjaxForm('sendReviews');
		$this->waitForInPlaceNotification($reviewRoundNotificationId, $revisionsRequestedText = 'Revisions have been requested.');
		$this->logout();

		// Test author dashboard page.
		$this->logIn('jbrower', 'jbrowerjbrower');
		$this->waitForElementPresent('css=[id^=component-grid-notifications-notificationsgrid-]');
		$this->open(self::$baseUrl . '/index.php/publicknowledge/authorDashboard/submission/' . $submissionId);
		$this->waitForInPlaceNotification($reviewRoundNotificationId, $revisionsRequestedText);
		$this->waitForElementPresent($addRevisionSelector = 'css=[id^=component-grid-files-review-authorreviewrevisionsgrid-addFile-button-]');
		$this->click($addRevisionSelector);
		$this->uploadWizardFile($fileTitle = 'Revision from author', null, false);
		$this->logout();

		// Check all revisions in notification email.
		$this->assertTrue($emailLog->exists(NOTIFICATION_TYPE_ALL_REVISIONS_IN, 'dbarnes@mailinator.com'), 'All revisions in notification email was not sent to the editor');
	}
}
