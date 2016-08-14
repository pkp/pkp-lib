<?php

/**
 * @file controllers/grid/users/reviewer/form/ReviewReminderForm.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ReviewReminderForm
 * @ingroup controllers_grid_users_reviewer_form
 *
 * @brief Form for sending a review reminder to a reviewer
 */

import('lib.pkp.classes.form.Form');

class ReviewReminderForm extends Form {
	/** The review assignment associated with the reviewer **/
	var $_reviewAssignment;

	/** The submission associated with the review assignment **/
	var $_submission;

	/**
	 * Constructor.
	 */
	function ReviewReminderForm($reviewAssignment) {
		parent::Form('controllers/grid/users/reviewer/form/reviewReminderForm.tpl');
		$this->_reviewAssignment = $reviewAssignment;

		// Validation checks for this form
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	//
	// Getters and Setters
	//
	/**
	 * Get the review assignment
	 * @return ReviewAssignment
	 */
	function getReviewAssignment() {
		return $this->_reviewAssignment;
	}

	/**
	 * Get the submission
	 * @return Submission
	 */
	function getSubmission() {
		return $this->_submission;
	}

	/**
	 * Set the submission
	 * @param $submission Submission
	 */
	function setSubmission($submission) {
		$this->_submission = $submission;
	}


	//
	// Overridden template methods
	//
	/**
	 * Initialize form data from the associated author.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function initData($args, $request) {
		$userDao = DAORegistry::getDAO('UserDAO');
		$user = $request->getUser();
		$context = $request->getContext();

		$reviewAssignment = $this->getReviewAssignment();
		$reviewerId = $reviewAssignment->getReviewerId();
		$reviewer = $userDao->getById($reviewerId);

		$submissionDao = Application::getSubmissionDAO();
		$submission = $submissionDao->getById($reviewAssignment->getSubmissionId());
		$this->setSubmission($submission);

		import('lib.pkp.classes.mail.SubmissionMailTemplate');
		$email = new SubmissionMailTemplate($submission, 'REVIEW_REMIND');

		// Format the review due date
		$reviewDueDate = strtotime($reviewAssignment->getDateDue());
		$dateFormatShort = Config::getVar('general', 'date_format_short');
		if ($reviewDueDate == -1) $reviewDueDate = $dateFormatShort; // Default to something human-readable if no date specified
		else $reviewDueDate = strftime($dateFormatShort, $reviewDueDate);

		$dispatcher = $request->getDispatcher();
		$paramArray = array(
			'reviewerName' => $reviewer->getFullName(),
			'reviewDueDate' => $reviewDueDate,
			'editorialContactSignature' => $user->getContactSignature(),
			'reviewerUserName' => $reviewer->getUsername(),
			'passwordResetUrl' => $dispatcher->url($request, ROUTE_PAGE, null, 'login', 'resetPassword', $reviewer->getUsername(), array('confirm' => Validation::generatePasswordResetHash($reviewer->getId()))),
			'submissionReviewUrl' => $dispatcher->url($request, ROUTE_PAGE, null, 'reviewer', 'submission', null, array('submissionId' => $reviewAssignment->getSubmissionId()))
		);
		$email->assignParams($paramArray);

		$this->setData('stageId', $reviewAssignment->getStageId());
		$this->setData('reviewAssignmentId', $reviewAssignment->getId());
		$this->setData('submissionId', $submission->getId());
		$this->setData('reviewAssignment', $reviewAssignment);
		$this->setData('reviewerName', $reviewer->getFullName() . ' <' . $reviewer->getEmail() . '>');
		$this->setData('message', $email->getBody());
		$this->setData('reviewDueDate', $reviewDueDate);
	}

	/**
	 * @copydoc Form::fetch()
	 */
	function fetch($request) {
		$context = $request->getContext();
		$user = $request->getUser();

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('emailVariables', array(
			'reviewerName' => __('user.name'),
			'reviewDueDate' => __('reviewer.submission.reviewDueDate'),
			'submissionReviewUrl' => __('common.url'),
			'submissionTitle' => __('submission.title'),
			'passwordResetUrl' => __('common.url'),
			'contextName' => $context->getLocalizedName(),
			'editorialContactSignature' => $user->getContactSignature(),
		));
		return parent::fetch($request);
	}

	/**
	 * @copydoc Form::fetch()
	 */
	function fetch($request) {
		$context = $request->getContext();
		$user = $request->getUser();

		// Get the review method options.
		$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
		$reviewMethods = $reviewAssignmentDao->getReviewMethodsTranslationKeys();
		$submission = $this->getSubmission();

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('reviewMethods', $reviewMethods);
		$reviewFormDao = DAORegistry::getDAO('ReviewFormDAO');
		$reviewForms = array(0 => __('editor.article.selectReviewForm'));
		$reviewFormsIterator = $reviewFormDao->getActiveByAssocId(Application::getContextAssocType(), $context->getId());
		while ($reviewForm = $reviewFormsIterator->next()) {
			$reviewForms[$reviewForm->getId()] = $reviewForm->getLocalizedTitle();
		}
		$templateMgr->assign('reviewForms', $reviewForms);
		$templateMgr->assign('emailVariables', array(
			'reviewerName' => __('user.name'),
			'reviewDueDate' => __('reviewer.submission.reviewDueDate'),
			'submissionReviewUrl' => __('common.url'),
			'submissionTitle' => __('submission.title'),
			'passwordResetUrl' => __('common.url'),
			'contextName' => $context->getLocalizedName(),
			'editorialContactSignature' => $user->getContactSignature(),
		));
		// Allow the default template
		$templateKeys[] = $this->_getMailTemplateKey($request->getContext());

		foreach ($templateKeys as $templateKey) {
			$template = new SubmissionMailTemplate($submission, $templateKey, null, null, null, false);
			$template->assignParams(array());
			$templates[$templateKey] = $template->getSubject();
		}

		$templateMgr->assign('templates', $templates);

		return parent::fetch($request);
	}

	/**
	 * Assign form data to user-submitted data.
	 * @see Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(array(
			'message',
			'reviewDueDate',
		));
	}

	/**
	 * Save review assignment
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function execute($args, $request) {
		$userDao = DAORegistry::getDAO('UserDAO');
		$submissionDao = Application::getSubmissionDAO();

		$reviewAssignment = $this->getReviewAssignment();
		$reviewerId = $reviewAssignment->getReviewerId();
		$reviewer = $userDao->getById($reviewerId);
		$submission = $submissionDao->getById($reviewAssignment->getSubmissionId());
		$reviewDueDate = $this->getData('reviewDueDate');
		$dispatcher = $request->getDispatcher();
		$user = $request->getUser();

		import('lib.pkp.classes.mail.SubmissionMailTemplate');
		$email = new SubmissionMailTemplate($submission, 'REVIEW_REMIND', null, null, null, false);

		$email->addRecipient($reviewer->getEmail(), $reviewer->getFullName());
		$email->setBody($this->getData('message'));
		$email->assignParams(array(
			'reviewerName' => $reviewer->getFullName(),
			'reviewDueDate' => $reviewDueDate,
			'passwordResetUrl' => $dispatcher->url($request, ROUTE_PAGE, null, 'login', 'resetPassword', $reviewer->getUsername(), array('confirm' => Validation::generatePasswordResetHash($reviewer->getId()))),
			'submissionReviewUrl' => $dispatcher->url($request, ROUTE_PAGE, null, 'reviewer', 'submission', null, array('submissionId' => $reviewAssignment->getSubmissionId())),
			'editorialContactSignature' => $user->getContactSignature(),
		));
		$email->send($request);

		// update the ReviewAssignment with the reminded and modified dates
		$reviewAssignment->setDateReminded(Core::getCurrentDate());
		$reviewAssignment->stampModified();
		$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
		$reviewAssignmentDao->updateObject($reviewAssignment);
	}

	/**
	 * Get the email template key depending on if reviewer one click access is
	 * enabled or not.
	 *
	 * @param mixed $context Context
	 * @return int Email template key
	 */
	function _getMailTemplateKey($context) {
		$templateKey = 'REVIEW_REMIND';
		if ($context->getSetting('reviewerAccessKeysEnabled')) {
			$templateKey = 'REVIEW_REMIND_ONECLICK';
		}

		return $templateKey;
	}
}

?>
