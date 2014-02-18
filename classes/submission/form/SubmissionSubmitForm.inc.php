<?php
/**
 * @defgroup submission_form Submission Forms
 */

/**
 * @file classes/submission/form/SubmissionSubmitForm.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionSubmitForm
 * @ingroup submission_form
 *
 * @brief Base class for author submit forms.
 */

import('lib.pkp.classes.form.Form');

class SubmissionSubmitForm extends Form {
	/** @var Context */
	var $context;

	/** @var int the ID of the submission */
	var $submissionId;

	/** @var Submission current submission */
	var $submission;

	/** @var int the current step */
	var $step;

	/** @var boolean whether or not the user has the ability to expedite this submission */
	var $_canExpedite;

	/**
	 * Constructor.
	 * @param $submission object
	 * @param $step int
	 */
	function SubmissionSubmitForm($context, $submission, $step) {
		parent::Form(sprintf('submission/form/step%d.tpl', $step));
		$this->addCheck(new FormValidatorPost($this));
		$this->step = (int) $step;
		$this->submission = $submission;
		$this->submissionId = $submission ? $submission->getId() : null;
		$this->context = $context;

		// Determine whether or not the current user belongs to a manager, editor, or assistant group
		// and could potentially expedite this submission.
		$request = Application::getRequest();
		$user = $request->getUser();
		$userGroupAssignmentDao = DAORegistry::getDAO('UserGroupAssignmentDAO');
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		$userGroupAssignments = $userGroupAssignmentDao->getByUserId($user->getId(), $context->getId());
		if (!$userGroupAssignments->wasEmpty()) {
			while ($userGroupAssignment = $userGroupAssignments->next()) {
				$userGroup = $userGroupDao->getById($userGroupAssignment->getUserGroupId());
				if (in_array($userGroup->getRoleId(), array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT))) {
					$this->_canExpedite = true;
					break;
				}
			}
		}

	}

	/**
	 * Fetch the form.
	 */
	function fetch($request) {
		$templateMgr = TemplateManager::getManager($request);

		$templateMgr->assign('submissionId', $this->submissionId);
		$templateMgr->assign('submitStep', $this->step);
		$templateMgr->assign('canExpedite', $this->canExpedite());

		if (isset($this->submission)) {
			$submissionProgress = $this->submission->getSubmissionProgress();
		} else {
			$submissionProgress = 1;
		}
		$templateMgr->assign('submissionProgress', $submissionProgress);
		return parent::fetch($request);
	}

	/**
	 * Whether or not this user can expedite this submission.
	 * @return boolean
	 */
	function canExpedite() {
		return $this->_canExpedite;
	}
}

?>
