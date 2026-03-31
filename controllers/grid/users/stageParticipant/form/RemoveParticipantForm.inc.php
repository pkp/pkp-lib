<?php

/**
 * @file lib/pkp/controllers/grid/users/stageParticipant/form/RemoveParticipantForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RemoveParticipantForm
 * @ingroup controllers_grid_users_stageParticipant_form
 *
 * @brief Form to optionally notify a user when removing them as a stage participant.
 */

import('lib.pkp.classes.form.Form');

class RemoveParticipantForm extends Form {
	/** @var Submission */
	var $_submission;

	/** @var int */
	var $_stageId;

	/** @var StageAssignment */
	var $_stageAssignment;

	/**
	 * Constructor
	 * @param $submission Submission
	 * @param $stageAssignment StageAssignment
	 * @param $stageId int
	 */
	function __construct($submission, $stageAssignment, $stageId) {
		parent::__construct('controllers/grid/users/stageParticipant/removeParticipantForm.tpl');
		$this->_submission = $submission;
		$this->_stageAssignment = $stageAssignment;
		$this->_stageId = $stageId;

		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	/**
	 * Initialize form data
	 */
	function initData() {
		$request = Application::get()->getRequest();
        $user = $request->getUser();
        $submission = $this->_submission;
        $userTobeRemovedId = $this->_stageAssignment->getUserId();
        $userDao = DAORegistry::getDAO('UserDAO'); /* @var $userDao UserDAO */
        $userToBeRemoved = $userDao->getById($userTobeRemovedId);

        $defaultMessage = __('editor.submission.removeStageParticipant.email.body', [
            'userName' => $userToBeRemoved->getFullName(),
            'contextName' => $request->getContext()->getLocalizedName(),
            'submissionTitle' => $submission->getLocalizedTitle()
        ]);
        $defaultMessage .= $user->getContactSignature();

        $this->setData('assignmentId', $this->_stageAssignment->getId());
        $this->setData('stageId', $this->_stageId);
        $this->setData('submissionId', $submission->getId());
        $this->setData('personalMessage', $defaultMessage);
        $this->setData('skipEmail', false);
	}

	/**
	 * @copydoc Form::fetch()
	 */
	function fetch($request, $template = null, $display = false) {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign(array(
			'reviewRoundId' => null,
			'reviewerId' => $this->_stageAssignment->getUserId(),
			'assignmentId' => $this->_stageAssignment->getId(),
			'stageId' => $this->_stageId,
			'submissionId' => $this->_submission->getId(),
			'personalMessage' => $this->getData('personalMessage'),
		));
		return parent::fetch($request, $template, $display);
	}

	/**
	 * @copydoc Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(array('assignmentId', 'stageId', 'submissionId', 'personalMessage', 'skipEmail'));
	}

	/**
	 * @copydoc Form::validate()
	 */
	function validate(...$functionArgs) {
		return parent::validate(...$functionArgs);
	}

	/**
	 * @copydoc Form::execute()
	 */
	function execute(...$functionArgs) {
		$request = Application::get()->getRequest();
		if ($this->getData('skipEmail')) return parent::execute(...$functionArgs);

		$submission = $this->_submission;
		$fromUser = $request->getUser();

		$userDao = DAORegistry::getDAO('UserDAO'); /* @var $userDao UserDAO */
		$user = $userDao->getById($this->_stageAssignment->getUserId());
		if (!$user) return parent::execute(...$functionArgs);

		import('classes.mail.ArticleMailTemplate');
		$mail = new ArticleMailTemplate($submission, 'NOTIFICATION_CENTER_DEFAULT');
		$mail->addRecipient($user->getEmail(), $user->getFullName());
		$mail->setSubject(__('editor.submission.removeStageParticipant'));
		$mail->setBody($this->getData('personalMessage'));

		if ($mail->isEnabled() && !$mail->send($request)) {
			$notificationMgr = new NotificationManager();
			$notificationMgr->createTrivialNotification($fromUser->getId(), NOTIFICATION_TYPE_ERROR, array('contents' => __('email.compose.error')));
		}

		return parent::execute(...$functionArgs);
	}
}