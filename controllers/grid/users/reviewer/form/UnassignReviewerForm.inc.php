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

import('lib.pkp.controllers.grid.users.reviewer.form.ReviewerNotifyActionForm');

class UnassignReviewerForm extends ReviewerNotifyActionForm {
	/**
	 * Constructor
	 * @param mixed $reviewAssignment ReviewAssignment
	 * @param mixed $reviewRound ReviewRound
	 * @param mixed $submission Submission
	 */
	function __construct($reviewAssignment, $reviewRound, $submission) {
		parent::__construct($reviewAssignment, $reviewRound, $submission, 'controllers/grid/users/reviewer/form/unassignReviewerForm.tpl');
	}

	/**
	 * @copydoc ReviewerNotifyActionForm::getEmailKey()
	 */
	public function getEmailKey() {
		return 'REVIEW_CANCEL';
	}

	/**
	 * Deletes the review assignment and notifies the reviewer via email
	 * @return bool whether or not the review assignment was deleted successfully
	 */
	function execute() {
		if (!parent::execute()) return false;

		$request = Application::get()->getRequest();
		$submission = $this->getSubmission();
		$reviewAssignment = $this->getReviewAssignment();

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
}
