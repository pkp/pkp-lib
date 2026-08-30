<?php

/**
 * @file lib/pkp/controllers/grid/users/stageParticipant/form/RemoveParticipantForm.php
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

namespace PKP\controllers\grid\users\stageParticipant\form;

use PKP\form\Form;
use APP\facades\Repo;
use APP\core\Application;
use APP\template\TemplateManager;
use APP\notification\NotificationManager;
use PKP\controllers\grid\queries\traits\StageMailable;
use Illuminate\Support\Facades\Mail;

class RemoveParticipantForm extends Form {
    use StageMailable;

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

		$this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));
	}

	/**
	 * Initialize form data
	 */
	function initData() {
		$request = Application::get()->getRequest();
        $user = $request->getUser();
        $submission = $this->_submission;
        $userTobeRemovedId = $this->_stageAssignment->getUserId();
        $userToBeRemoved = Repo::user()->get($userTobeRemovedId, true);

        $defaultMessage = __('editor.submission.removeStageParticipant.email.body', [
            'userName' => $userToBeRemoved->getFullName(),
            'contextName' => $request->getContext()->getLocalizedName(),
            'submissionTitle' => $submission->getLocalizedTitle(),
            'senderName' => $user->getFullName(),
            'senderEmail' => $user->getEmail()
        ]);

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

        $user = Repo::user()->get($this->_stageAssignment->getUserId(), true);
		if (!$user) return parent::execute(...$functionArgs);

        $mailable = $this->getStageMailable($request->getContext(), $submission);
        $mailable
            ->sender($request->getUser())
            ->recipients([$user])
            ->body($this->getData('personalMessage'))
            ->subject(__('editor.submission.removeStageParticipant'));

		Mail::send($mailable);

		return parent::execute(...$functionArgs);
	}

    /**
     * Get the stage ID
     *
     * @return int
     */
    public function getStageId()
    {
        return $this->_stageId;
    }
}