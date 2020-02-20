<?php
/**
 * @file controllers/grid/users/reviewer/form/ReinstateReviewerForm.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReinstateReviewerForm
 * @ingroup controllers_grid_users_reviewer_form
 *
 * @brief Allow the editor to reinstate a cancelled review assignment
 */

import('lib.pkp.controllers.grid.users.reviewer.form.ReviewerNotifyActionForm');

class ReinstateReviewerForm extends ReviewerNotifyActionForm {
	/**
	 * Constructor
	 * @param $reviewAssignment ReviewAssignment
	 * @param $reviewRound ReviewRound
	 * @param $submission Submission
	 */
	public function __construct($reviewAssignment, $reviewRound, $submission) {
		parent::__construct($reviewAssignment, $reviewRound, $submission, 'controllers/grid/users/reviewer/form/reinstateReviewerForm.tpl');
	}

	/**
	 * @copydoc ReviewerNotifyActionForm::getEmailKey()
	 */
	protected function getEmailKey() {
		return 'REVIEW_REINSTATE';
	}

	/**
	 * @copydoc Form::execute()
	 * @return bool whether or not the review assignment was deleted successfully
	 */
	public function execute(...$functionArgs) {
		if (!parent::execute(...$functionArgs)) return false;

		$request = Application::get()->getRequest();
		$submission = $this->getSubmission();
		$reviewAssignment = $this->getReviewAssignment();

		// Reinstate the review assignment.
		$submissionDao = DAORegistry::getDAO('SubmissionDAO'); /* @var $submissionDao SubmissionDAO */
		$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /* @var $reviewAssignmentDao ReviewAssignmentDAO */
		$userDao = DAORegistry::getDAO('UserDAO'); /* @var $userDao UserDAO */

		if (isset($reviewAssignment) && $reviewAssignment->getSubmissionId() == $submission->getId() && !HookRegistry::call('EditorAction::reinstateReview', array(&$submission, $reviewAssignment))) {
			$reviewer = $userDao->getById($reviewAssignment->getReviewerId());
			if (!isset($reviewer)) return false;

			$reviewAssignment->setCancelled(false);
			$reviewAssignmentDao->updateObject($reviewAssignment);

			// Stamp the modification date
			$submission->stampModified();
			$submissionDao->updateObject($submission);

			// Insert a trivial notification to indicate the reviewer was reinstated successfully.
			$currentUser = $request->getUser();
			$notificationMgr = new NotificationManager();
			$notificationMgr->createTrivialNotification($currentUser->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => __('notification.reinstatedReviewer')));

			// Add log
			import('lib.pkp.classes.log.SubmissionLog');
			import('classes.log.SubmissionEventLogEntry');
			SubmissionLog::logEvent($request, $submission, SUBMISSION_LOG_REVIEW_REINSTATED, 'log.review.reviewReinstated', array('reviewAssignmentId' => $reviewAssignment->getId(), 'reviewerName' => $reviewer->getFullName(), 'submissionId' => $submission->getId(), 'stageId' => $reviewAssignment->getStageId(), 'round' => $reviewAssignment->getRound()));

			return true;
		}
		return false;
	}
}
