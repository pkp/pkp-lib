<?php

/**
 * @file tests/functional/workflow/PKPProductionBaseTestCase.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPProductionBaseTestCase
 * @ingroup tests_functional_workflow
 *
 * @brief Base class for production tests.
 */

import('lib.pkp.tests.functional.workflow.WorkflowBaseTestCase');

class PKPProductionBaseTestCase extends WorkflowBaseTestCase {

	/**
	 * Request a layout task to the passed user and check
	 * if the task notification email was sent.
	 * @param $name string First and last names.
	 * @param $userEmail string
	 * @param $submissionFullTitle string
	 */
	protected function requestLayoutTask($name, $userEmail, $submissionFullTitle) {
		$this->assignParticipant('Layout Editor', $name, 'LAYOUT_REQUEST');
		$this->assertTrue($this->emailLog->exists(NOTIFICATION_TYPE_LAYOUT_ASSIGNMENT, $userEmail, $submissionFullTitle));
	}

	/**
	 * Complete the layout task requested by the passed
	 * editor for the passed submission and check if the
	 * layout complete email was sent.
	 * @param $editorName string
	 * @param $editorEmail string
	 * @param $submissionId int
	 * @param $bodyText string Part of the text that can identify
	 * the layout complete email.
	 */
	protected function completeLayoutTask($editorName, $editorEmail, $submissionId, $bodyText) {
		$this->notifyParticipant($editorName, 'LAYOUT_COMPLETE');
		$this->assertTrue($this->emailLog->existsByAssoc(ASSOC_TYPE_SUBMISSION, $submissionId, $editorEmail, null, $bodyText));
	}

	/**
	 * Check the approve submission notification.
	 */
	protected function checkApproveSubmissionNotification() {
		$notificationMessages = $this->getNotificationMessages();
		$this->waitForElementPresent('css=[id^=component-grid-files-productionready-productionreadyfilesgrid-]');
		$this->assertTextPresent($notificationMessages[NOTIFICATION_TYPE_APPROVE_SUBMISSION]);
	}
	
	/**
	 * Get notification message texts indexed by notification type.
	 * @return array
	 */
	protected function getNotificationMessages() {
		return array();
	}
}
