<?php

/**
 * @file controllers/grid/users/stageParticipant/form/AddParticipantForm.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AddParticipantForm
 * @ingroup controllers_grid_users_stageParticipant_form
 *
 * @brief Form for adding a stage participant
 */

import('controllers.grid.users.stageParticipant.form.StageParticipantNotifyForm');

class AddParticipantForm extends StageParticipantNotifyForm {
	/** @var Submission The submission associated with the submission contributor being edited **/
	var $_submission;

	/**
	 * Constructor.
	 * @param $submission Submission
	 * @param $stageId int STAGE_ID_...
	 */
	function __construct($submission, $stageId) {
		parent::__construct($submission->getId(), ASSOC_TYPE_SUBMISSION, $stageId, 'controllers/grid/users/stageParticipant/addParticipantForm.tpl');
		$this->_submission = $submission;
		$this->_stageId = $stageId;

		// add checks in addition to anything that the Notification form may apply.
		// FIXME: should use a custom validator to check that the userId belongs to this group.
		$this->addCheck(new FormValidator($this, 'userGroupId', 'required', 'editor.submission.addStageParticipant.form.userGroupRequired'));
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	//
	// Getters and Setters
	//
	/**
	 * Get the Submission
	 * @return Submission
	 */
	function getSubmission() {
		return $this->_submission;
	}

	/**
	 * @copydoc Form::fetch()
	 */
	function fetch($request, $template = null, $display = false) {
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		$userGroups = $userGroupDao->getUserGroupsByStage(
			$request->getContext()->getId(),
			$this->getStageId()
		);

		$userGroupOptions = array();
		while ($userGroup = $userGroups->next()) {
			// Exclude reviewers.
			if ($userGroup->getRoleId() == ROLE_ID_REVIEWER) continue;
			$userGroupOptions[$userGroup->getId()] = $userGroup->getLocalizedName();
		}

		$templateMgr = TemplateManager::getManager($request);

		// assign the user groups options
		$templateMgr->assign('userGroupOptions', $userGroupOptions);
		// assigned the first element as selected
		$keys = array_keys($userGroupOptions);
		$templateMgr->assign('selectedUserGroupId', array_shift($keys));
		// assign all user group IDs with ROLE_ID_MANAGER or ROLE_ID_SUB_EDITOR
		$managerGroupIds = $userGroupDao->getUserGroupIdsByRoleId(ROLE_ID_MANAGER, $request->getContext()->getId());
		$subEditorGroupIds = $userGroupDao->getUserGroupIdsByRoleId(ROLE_ID_SUB_EDITOR, $request->getContext()->getId());
		$possibleRecommendOnlyUserGroupIds = array_merge($managerGroupIds, $subEditorGroupIds);
		$templateMgr->assign('possibleRecommendOnlyUserGroupIds', $possibleRecommendOnlyUserGroupIds);
		// assign user group IDs with recommendOnly option set
		$templateMgr->assign('recommendOnlyUserGroupIds', $userGroupDao->getRecommendOnlyGroupIds($request->getContext()->getId()));

		// assign the vars required for the request
		$templateMgr->assign('submissionId', $this->getSubmission()->getId());

		// If submission is in review, add a list of reviewer Ids that should not be
		// assigned as participants because they have blind peer reviews in progress
		import('lib.pkp.classes.submission.reviewAssignment.ReviewAssignment');
		$blindReviewerIds = array();
		if (in_array($this->getSubmission()->getStageId(), array(WORKFLOW_STAGE_ID_INTERNAL_REVIEW, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW))) {
			$blindReviewMethods = array(SUBMISSION_REVIEW_METHOD_BLIND, SUBMISSION_REVIEW_METHOD_DOUBLEBLIND);
			$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
			$reviewAssignments = $reviewAssignmentDao->getBySubmissionId($this->getSubmission()->getId());
			$blindReviews = array_filter($reviewAssignments, function($reviewAssignment) use ($blindReviewMethods) {
				return in_array($reviewAssignment->getReviewMethod(), $blindReviewMethods) && !$reviewAssignment->getDeclined();
			});
			$blindReviewerIds = array_map(function($reviewAssignment) {
				return $reviewAssignment->getReviewerId();
			}, $blindReviews);

		}
		$templateMgr->assign(array(
			'blindReviewerIds' => array_values(array_unique($blindReviewerIds)),
			'blindReviewerWarning' => __('editor.submission.addStageParticipant.form.reviewerWarning'),
			'blindReviewerWarningOk' => __('common.ok'),
		));

		return parent::fetch($request, $template, $display);
	}

	/**
	 * @copydoc Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(array(
			'userGroupId',
			'userId',
			'message',
			'template',
			'recommendOnly',
		));
	}

	/**
	 * @copydoc Form::validate()
	 */
	function validate($callHooks = true) {
		$userGroupId = (int) $this->getData('userGroupId');
		$userId = (int) $this->getData('userId');
		$submission = $this->getSubmission();

		$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
		return $userGroupDao->userInGroup($userId, $userGroupId) && $userGroupDao->getById($userGroupId, $submission->getContextId());
	}

	/**
	 * @see Form::execute()
	 * @return array ($userGroupId, $userId)
	 */
	function execute() {
		$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /* @var $stageAssignmentDao StageAssignmentDAO */
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */

		$submission = $this->getSubmission();
		$userGroupId = (int) $this->getData('userGroupId');
		$userId = (int) $this->getData('userId');
		$recommendOnly = $this->getData('recommendOnly')?true:false;

		// sanity check
		if ($userGroupDao->userGroupAssignedToStage($userGroupId, $this->getStageId())) {
			// insert the assignment
			$stageAssignment = $stageAssignmentDao->build($submission->getId(), $userGroupId, $userId, $recommendOnly);
		}

		parent::execute();
		return array($userGroupId, $userId, $stageAssignment->getId());
	}

	/**
	 * whether or not to require a message field
	 * @return boolean
	 */
	function isMessageRequired() {
		return false;
	}
}

?>
