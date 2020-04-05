<?php
/**
 * @file controllers/grid/users/reviewer/form/ReviewerNotifyActionForm.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerNotifyActionForm
 * @ingroup controllers_grid_users_reviewer_form
 *
 * @brief Perform an action on a review including a reviewer notification email.
 */

import('lib.pkp.classes.form.Form');

abstract class ReviewerNotifyActionForm extends Form {
	/** The review assignment to alter */
	var $_reviewAssignment;

	/** The submission associated with the review assignment **/
	var $_submission;

	/** The review round associated with the review assignment **/
	var $_reviewRound;

	/**
	 * Constructor
	 * @param $reviewAssignment ReviewAssignment
	 * @param $reviewRound ReviewRound
	 * @param $submission Submission
	 * @param $template string
	 */
	public function __construct($reviewAssignment, $reviewRound, $submission, $template) {
		$this->setReviewAssignment($reviewAssignment);
		$this->setReviewRound($reviewRound);
		$this->setSubmission($submission);
		parent::__construct($template);
	}

	protected abstract function getEmailKey();

	//
	// Overridden template methods
	//
	/**
	 * @copydoc Form::initData
	 */
	public function initData() {
		$request = Application::get()->getRequest();
		$context = $request->getContext();
		$submission = $this->getSubmission();
		$reviewAssignment = $this->getReviewAssignment();
		$reviewRound = $this->getReviewRound();
		$reviewerId = $reviewAssignment->getReviewerId();

		$this->setData(array(
			'submissionId' => $submission->getId(),
			'stageId' => $reviewRound->getStageId(),
			'reviewRoundId' => $reviewRound->getId(),
			'reviewAssignmentId' => $reviewAssignment->getId(),
			'dateConfirmed' => $reviewAssignment->getDateConfirmed(),
			'reviewerId' => $reviewerId,
		));

		import('lib.pkp.classes.mail.SubmissionMailTemplate');
		$template = new SubmissionMailTemplate($submission, $this->getEmailKey());
		if ($template) {
			$userDao = DAORegistry::getDAO('UserDAO'); /* @var $userDao UserDAO */
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
	 * @copydoc Form::execute()
	 * @return bool whether or not the review assignment was modified successfully
	 */
	public function execute(...$functionArgs) {
		$request = Application::get()->getRequest();
		$submission = $this->getSubmission();
		$reviewAssignment = $this->getReviewAssignment();

		// Notify the reviewer via email.
		import('lib.pkp.classes.mail.SubmissionMailTemplate');
		$mail = new SubmissionMailTemplate($submission, $this->getEmailKey(), null, null, false);

		if ($mail->isEnabled() && !$this->getData('skipEmail')) {
			$userDao = DAORegistry::getDAO('UserDAO'); /* @var $userDao UserDAO */
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
		parent::execute(...$functionArgs);
		return true;
	}

	/**
	 * @copydoc Form::readInputData()
	 */
	public function readInputData() {
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
	 * @param mixed $reviewAssignment ReviewAssignment
	 */
	public function setReviewAssignment($reviewAssignment) {
		$this->_reviewAssignment = $reviewAssignment;
	}

	/**
	 * Get the ReviewAssignment
	 * @return ReviewAssignment
	 */
	public function getReviewAssignment() {
		return $this->_reviewAssignment;
	}

	/**
	 * Set the ReviewRound
	 * @param mixed $reviewRound ReviewRound
	 */
	public function setReviewRound($reviewRound) {
		$this->_reviewRound = $reviewRound;
	}

	/**
	 * Get the ReviewRound
	 * @return ReviewRound
	 */
	public function getReviewRound() {
		return $this->_reviewRound;
	}

	/**
	 * Set the submission
	 * @param $submission Submission
	 */
	public function setSubmission($submission) {
		$this->_submission = $submission;
	}

	/**
	 * Get the submission
	 * @return Submission
	 */
	public function getSubmission() {
		return $this->_submission;
	}
}
