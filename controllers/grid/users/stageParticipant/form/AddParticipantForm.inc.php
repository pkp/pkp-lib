<?php

/**
 * @file controllers/grid/users/stageParticipant/form/AddParticipantForm.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
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

	/** @var $_assignmentId int Used for edit the assignment **/
	var $_assignmentId;

	/** @var $_isChangePermitMetadataAllowed bool true if permit_metadata_edit field is allowed to change  **/
	var $_isChangePermitMetadataAllowed = false;

	/** @var $_isChangeRecommentOnlyAllowed bool true if recommend_only field is allowed to change  **/
	var $_isChangeRecommentOnlyAllowed = false;

	/** @var $_managerGroupIds array Contains all manager group_ids  **/
	var $_managerGroupIds;

	/** @var $_possibleRecommendOnlyUserGroupIds array Contains all group_ids that can have the recommendOnly field available for change  **/
	var $_possibleRecommendOnlyUserGroupIds;

	/** @var $_contextId int the current Context Id **/
	var $_contextId;

	/**
	 * Constructor.
	 * @param $submission Submission
	 * @param $stageId int STAGE_ID_...
	 * @param $assignmentId int Optional - Used for edit the assignment
	 */
	function __construct($submission, $stageId, $assignmentId = null) {
		parent::__construct($submission->getId(), ASSOC_TYPE_SUBMISSION, $stageId, 'controllers/grid/users/stageParticipant/addParticipantForm.tpl');
		$this->_submission = $submission;
		$this->_stageId = $stageId;
		$this->_assignmentId = $assignmentId;
		$this->_contextId = $submission->getContextId();

		// add checks in addition to anything that the Notification form may apply.
		// FIXME: should use a custom validator to check that the userId belongs to this group.
		$this->addCheck(new FormValidator($this, 'userGroupId', 'required', 'editor.submission.addStageParticipant.form.userGroupRequired'));
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));

		$this->initialize();
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
	 * Initialize private attributes that need to be used through all functions.
	 */
	function initialize() {
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');

		// assign all user group IDs with ROLE_ID_MANAGER or ROLE_ID_SUB_EDITOR
		$this->_managerGroupIds = $userGroupDao->getUserGroupIdsByRoleId(ROLE_ID_MANAGER, $this->_contextId);
		$subEditorGroupIds = $userGroupDao->getUserGroupIdsByRoleId(ROLE_ID_SUB_EDITOR, $this->_contextId);
		$this->_possibleRecommendOnlyUserGroupIds = array_merge($this->_managerGroupIds, $subEditorGroupIds);

		if ($this->_assignmentId) {
			/** @var $stageAssignmentDao StageAssignmentDAO */
			$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');

			/** @var $stageAssignment StageAssignment */
			$stageAssignment = $stageAssignmentDao->getById($this->_assignmentId);
			$this->_isChangePermitMetadataAllowed = !in_array($stageAssignment->getUserGroupId(), $this->_managerGroupIds);
			$this->_isChangeRecommentOnlyAllowed = in_array($stageAssignment->getUserGroupId(), $this->_possibleRecommendOnlyUserGroupIds);
		}
	}

	/**
	 * @copydoc Form::fetch()
	 */
	function fetch($request, $template = null, $display = false) {
		$this->initialize($request);

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

		$templateMgr->assign('possibleRecommendOnlyUserGroupIds', $this->_possibleRecommendOnlyUserGroupIds);
		// assign user group IDs with recommendOnly option set
		$templateMgr->assign('recommendOnlyUserGroupIds', $userGroupDao->getRecommendOnlyGroupIds($request->getContext()->getId()));

		$templateMgr->assign('notPossibleEditSubmissionMetadataPermissionChange', $this->_managerGroupIds);
		$templateMgr->assign('permitMetadataEditUserGroupIds', $userGroupDao->getPermitMetadataEditGroupIds($request->getContext()->getId()));

		// assign the vars required for the request
		$templateMgr->assign('submissionId', $this->getSubmission()->getId());

		$templateMgr->assign('userGroupId', '');
		$templateMgr->assign('userIdSelected', '');

		if ($this->_assignmentId) {
			/** @var $stageAssignmentDao StageAssignmentDAO */
			$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');

			/** @var $stageAssignment StageAssignment */
			$stageAssignment = $stageAssignmentDao->getById($this->_assignmentId);

			$userDao = DAORegistry::getDAO('UserDAO');
			/** @var $currentUser User */
			$currentUser = $userDao->getById($stageAssignment->getUserId());

			$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
			/** @var $userGroup UserGroup */
			$userGroup = $userGroupDao->getById($stageAssignment->getUserGroupId());

			$templateMgr->assign('assignmentId', $this->_assignmentId);
			$templateMgr->assign('currentUserName', $currentUser->getFullName());
			$templateMgr->assign('currentUserGroup', $userGroup->getLocalizedName());
			$templateMgr->assign('userGroupId', $stageAssignment->getUserGroupId());
			$templateMgr->assign('userIdSelected', $stageAssignment->getUserId());
			$templateMgr->assign('currentAssignmentRecommentOnly', $stageAssignment->getRecommendOnly());
			$templateMgr->assign('currentAssignmentPermitMetadataEdit', $stageAssignment->getCanChangeMetadata());
			$templateMgr->assign('isChangePermitMetadataAllowed', $this->_isChangePermitMetadataAllowed);
			$templateMgr->assign('isChangeRecommentOnlyAllowed', $this->_isChangeRecommentOnlyAllowed);
		}


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
			'canChangeMetadata',
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
		$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var $stageAssignmentDao StageAssignmentDAO */
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var $userGroupDao UserGroupDAO */

		$submission = $this->getSubmission();
		$userGroupId = (int) $this->getData('userGroupId');
		$userId = (int) $this->getData('userId');
		$recommendOnly = $this->getData('recommendOnly')?true:false;
		$canChangeMetadata = $this->getData('canChangeMetadata')?true:false;

		// sanity check
		if ($userGroupDao->userGroupAssignedToStage($userGroupId, $this->getStageId())) {
			$updated = false;

			if ($this->_assignmentId) {
				/** @var $stageAssignment StageAssignment */
				$stageAssignment = $stageAssignmentDao->getById($this->_assignmentId);

				if ($stageAssignment) {
					if ($this->_isChangeRecommentOnlyAllowed) {
						$stageAssignment->setRecommendOnly($recommendOnly);
					}

					if ($this->_isChangePermitMetadataAllowed) {
						$stageAssignment->setCanChangeMetadata($canChangeMetadata);
					}

					$stageAssignmentDao->updateObject($stageAssignment);
					$updated = true;
				}
			}

			if (!$updated) {
				// insert the assignment
				$stageAssignment = $stageAssignmentDao->build($submission->getId(), $userGroupId, $userId, $recommendOnly, $canChangeMetadata);
			}
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


