<?php
/**
 * @file controllers/grid/users/reviewer/form/UnassignReviewerForm.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class UnassignReviewerForm
 * @ingroup controllers_grid_users_reviewer_form
 *
 * @brief Allow the editor to remove a review assignment
 */

import('lib.pkp.classes.form.Form');

class UnassignReviewerForm extends Form {
	/** The review assignment to delete */
	var $_reviewAssignment;

	/** The submission associated with the review assignment **/
	var $_submission;

	/** The review round associated with the review assignment **/
	var $_reviewRound;

	/**
	 * Constructor
	 * @param mixed $reviewAssignment ReviewAssignment
	 * @param mixed $reviewRound ReviewRound
	 * @param mixed $submission Submission
	 */
	function __construct($reviewAssignment, $reviewRound, $submission) {
		$this->setReviewAssignment($reviewAssignment);
		$this->setReviewRound($reviewRound);
		$this->setSubmission($submission);

		parent::__construct('controllers/grid/users/reviewer/form/unassignReviewerForm.tpl');
	}

	//
	// Overridden template methods
	//
	/**
	 * @copydoc Form::initData
	 */
	function initData() {
		$request = Application::get()->getRequest();
		$context = $request->getContext();
		$submission = $this->getSubmission();
		$reviewAssignment = $this->getReviewAssignment();
		$reviewRound = $this->getReviewRound();
		$reviewerId = $reviewAssignment->getReviewerId();

		$this->setData('submissionId', $submission->getId());
		$this->setData('stageId', $reviewRound->getStageId());
		$this->setData('reviewRoundId', $reviewRound->getId());
		$this->setData('reviewAssignmentId', $reviewAssignment->getId());
		$this->setData('reviewerId', $reviewerId);

		import('lib.pkp.classes.mail.SubmissionMailTemplate');
		$template = new SubmissionMailTemplate($submission, 'REVIEW_CANCEL');
		if ($template) {
			$userDao = DAORegistry::getDAO('UserDAO');
			$reviewer = $userDao->getById($reviewerId);
			$user = $request->getUser();

			$template->assignParams(array(
				'reviewerName' => $reviewer->getFullName(),
				'signatureFullName' => $user->getFullname(),
			));
			$template->replaceParams();

			$this->setData('personalMessage', $template->getBody());
		}
	}

	/**
	 * Deletes the review assignment and notifies the reviewer via email
	 *
	 * @return bool whether or not the review assignment was deleted successfully
	 */
	function execute() {
		$request = Application::get()->getRequest();
		$submission = $this->getSubmission();
		$reviewAssignment = $this->getReviewAssignment();

		// Notify the reviewer via email.
		import('lib.pkp.classes.mail.SubmissionMailTemplate');
		$mail = new SubmissionMailTemplate($submission, 'REVIEW_CANCEL', null, null, false);

		if ($mail->isEnabled() && !$this->getData('skipEmail')) {
			$userDao = DAORegistry::getDAO('UserDAO');
			$reviewerId = (int) $this->getData('reviewerId');
			$reviewer = $userDao->getById($reviewerId);
			$mail->addRecipient($reviewer->getEmail(), $reviewer->getFullName());
			$mail->setBody($this->getData('personalMessage'));
			$mail->assignParams();
			if (!$mail->send($request)) {
				import('classes.notification.NotificationManager');
				$notificationMgr = new NotificationManager();
				$notificationMgr->createTrivialNotification($request->getUser()->getId(), NOTIFICATION_TYPE_ERROR, array('contents' => __('email.compose.error')));
			}
		}

		// Delete or cancel the review assignment.
		$submissionDao = Application::getSubmissionDAO();
		$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
		$userDao = DAORegistry::getDAO('UserDAO');

		if (isset($reviewAssignment) && $reviewAssignment->getSubmissionId() == $submission->getId() && !HookRegistry::call('EditorAction::clearReview', array(&$submission, $reviewAssignment))) {
			$reviewer = $userDao->getById($reviewAssignment->getReviewerId());
			if (!isset($reviewer)) return false;
			if ($reviewAssignment->getDateConfirmed()) {
				// The review has been confirmed but not completed. Flag it as cancelled.
				$reviewAssignment->setCancelled(true);
				$reviewAssignmentDao->updateObject($reviewAssignment);
			} else {
				// The review had not been confirmed yet. Delete the assignment.
				$reviewAssignmentDao->deleteById($reviewAssignment->getId());
			}

			// Stamp the modification date
			$submission->stampModified();
			$submissionDao->updateObject($submission);

			$notificationDao = DAORegistry::getDAO('NotificationDAO');
			$notificationDao->deleteByAssoc(
				ASSOC_TYPE_REVIEW_ASSIGNMENT,
				$reviewAssignment->getId(),
				$reviewAssignment->getReviewerId(),
				NOTIFICATION_TYPE_REVIEW_ASSIGNMENT
			);

			// Insert a trivial notification to indicate the reviewer was removed successfully.
			$currentUser = $request->getUser();
			$notificationMgr = new NotificationManager();
			$notificationMgr->createTrivialNotification($currentUser->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => $reviewAssignment->getDateConfirmed()?__('notification.cancelledReviewer'):__('notification.removedReviewer')));

			// Add log
			import('lib.pkp.classes.log.SubmissionLog');
			import('classes.log.SubmissionEventLogEntry');
			SubmissionLog::logEvent($request, $submission, SUBMISSION_LOG_REVIEW_CLEAR, 'log.review.reviewCleared', array('reviewAssignmentId' => $reviewAssignment->getId(), 'reviewerName' => $reviewer->getFullName(), 'submissionId' => $submission->getId(), 'stageId' => $reviewAssignment->getStageId(), 'round' => $reviewAssignment->getRound()));

			return true;
		}
		return false;
	}

	/**
	 * Assign form data to user-submitted data.
	 * @see Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(array(
			'personalMessage',
			'reviewAssignmentId',
			'reviewRoundId',
			'reviewerId',
			'skipEmail',
			'stageId',
			'submissionId',
		));
	}

	//
	// Getters and Setters
	//
	/**
	 * Set the ReviewAssignment
	 *
	 * @param mixed $reviewAssignment ReviewAssignment
	 */
	function setReviewAssignment($reviewAssignment) {
		$this->_reviewAssignment = $reviewAssignment;
	}

	/**
	 * Get the ReviewAssignment
	 *
	 * @return ReviewAssignment
	 */
	function getReviewAssignment() {
		return $this->_reviewAssignment;
	}

	/**
	 * Set the ReviewRound
	 *
	 * @param mixed $reviewRound ReviewRound
	 */
	function setReviewRound($reviewRound) {
		$this->_reviewRound = $reviewRound;
	}

	/**
	 * Get the ReviewRound
	 *
	 * @return ReviewRound
	 */
	function getReviewRound() {
		return $this->_reviewRound;
	}

	/**
	 * Set the submission
	 * @param $submission Submission
	 */
	function setSubmission($submission) {
		$this->_submission = $submission;
	}

	/**
	 * Get the submission
	 * @return Submission
	 */
	function getSubmission() {
		return $this->_submission;
	}
}
