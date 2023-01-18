<?php

/**
 * @file controllers/grid/users/stageParticipant/form/AddParticipantForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AddParticipantForm
 * @ingroup controllers_grid_users_stageParticipant_form
 *
 * @brief Form for adding a stage participant
 */

namespace PKP\controllers\grid\users\stageParticipant\form;

use APP\core\Application;
use APP\facades\Repo;
use APP\template\TemplateManager;
use PKP\db\DAORegistry;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;
use PKP\userGroup\relationships\UserGroupStage;
use PKP\userGroup\UserGroup;

class AddParticipantForm extends PKPStageParticipantNotifyForm
{
    /** @var Submission The submission associated with the submission contributor being edited */
    public $_submission;

    /** @var int $_assignmentId Used for edit the assignment */
    public $_assignmentId;

    /** @var array $_managerGroupIds Contains all manager group_ids  */
    public $_managerGroupIds;

    /** @var array $_possibleRecommendOnlyUserGroupIds Contains all group_ids that can have the recommendOnly field available for change  */
    public $_possibleRecommendOnlyUserGroupIds;

    /** @var int $_contextId the current Context Id */
    public $_contextId;

    /**
     * Constructor.
     *
     * @param Submission $submission
     * @param int $stageId STAGE_ID_...
     * @param int $assignmentId Optional - Used for edit the assignment
     */
    public function __construct($submission, $stageId, $assignmentId = null)
    {
        parent::__construct($submission->getId(), ASSOC_TYPE_SUBMISSION, $stageId, 'controllers/grid/users/stageParticipant/addParticipantForm.tpl');
        $this->_submission = $submission;
        $this->_stageId = $stageId;
        $this->_assignmentId = $assignmentId;
        $this->_contextId = $submission->getContextId();

        // add checks in addition to anything that the Notification form may apply.
        // FIXME: should use a custom validator to check that the userId belongs to this group.
        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'userGroupId', 'required', 'editor.submission.addStageParticipant.form.userGroupRequired'));
        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));

        $this->initialize();
    }

    //
    // Getters and Setters
    //
    /**
     * Get the Submission
     *
     * @return Submission
     */
    public function getSubmission()
    {
        return $this->_submission;
    }

    /**
     * Initialize private attributes that need to be used through all functions.
     */
    public function initialize()
    {
        // assign all user group IDs with ROLE_ID_MANAGER or ROLE_ID_SUB_EDITOR
        $this->_managerGroupIds =  Repo::userGroup()->getArrayIdByRoleId(Role::ROLE_ID_MANAGER, $this->_contextId);
        $subEditorGroupIds = Repo::userGroup()->getArrayIdByRoleId(Role::ROLE_ID_SUB_EDITOR, $this->_contextId);
        $this->_possibleRecommendOnlyUserGroupIds = array_merge($this->_managerGroupIds, $subEditorGroupIds);
    }

    /**
     * Determine whether the specified user group is potentially restricted from editing metadata.
     *
     * Subeditors can not change their own permissions.
     */
    protected function _isChangePermitMetadataAllowed(UserGroup $userGroup, int $userId): bool 
    {
        $currentUser = Application::get()->getRequest()->getUser();

        if ($currentUser->getId() === $userId && $userGroup->getRoleId() === Role::ROLE_ID_SUB_EDITOR) {
            return false;
        }

        return $userGroup->getRoleId() !== Role::ROLE_ID_MANAGER;
    }

    /**
     * Determine whether the specified group is potentially required to make recommendations rather than decisions.
     *
     * Subeditors can not change their own permissions.
     */
    protected function _isChangeRecommendOnlyAllowed(UserGroup $userGroup, int $userId): bool
    {
        $currentUser = Application::get()->getRequest()->getUser();

        if ($currentUser->getId() === $userId && $userGroup->getRoleId() === Role::ROLE_ID_SUB_EDITOR) {
            return false;
        }

        return in_array($userGroup->getRoleId(), [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR]);
    }

    /**
     * @copydoc Form::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $userGroups = Repo::userGroup()->getUserGroupsByStage(
            $request->getContext()->getId(),
            $this->getStageId()
        );

        $userGroupOptions = [];
        foreach ($userGroups as $userGroup) {
            // Exclude reviewers.
            if ($userGroup->getRoleId() == Role::ROLE_ID_REVIEWER) {
                continue;
            }
            $userGroupOptions[$userGroup->getId()] = $userGroup->getLocalizedName();
        }

        $templateMgr = TemplateManager::getManager($request);
        $keys = array_keys($userGroupOptions);
        $templateMgr->assign([
            'userGroupOptions' => $userGroupOptions,
            'selectedUserGroupId' => array_shift($keys), // assign the first element as selected
            'possibleRecommendOnlyUserGroupIds' => $this->_possibleRecommendOnlyUserGroupIds,
            'recommendOnlyUserGroupIds' => Repo::userGroup()->getCollector()
                ->filterByContextIds([$request->getContext()->getId()])
                ->filterByIsRecommendOnly()
                ->getMany()
                ->toArray(),
            'notPossibleEditSubmissionMetadataPermissionChange' => $this->_managerGroupIds,
            'permitMetadataEditUserGroupIds' => Repo::userGroup()->getCollector()
                ->filterByContextIds([$request->getContext()->getId()])
                ->filterByPermitMetadataEdit(true)
                ->getMany()
                ->toArray(),
            'submissionId' => $this->getSubmission()->getId(),
            'userGroupId' => '',
            'userIdSelected' => '',
        ]);

        if ($this->_assignmentId) {
            /** @var StageAssignmentDAO $stageAssignmentDao */
            $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */

            /** @var StageAssignment $stageAssignment */
            $stageAssignment = $stageAssignmentDao->getById($this->_assignmentId);

            $currentUser = Repo::user()->get($stageAssignment->getUserId());

            /** @var UserGroup $userGroup */
            $userGroup = Repo::userGroup()->get($stageAssignment->getUserGroupId());

            $templateMgr->assign([
                'assignmentId' => $this->_assignmentId,
                'currentUserName' => $currentUser->getFullName(),
                'currentUserGroup' => $userGroup->getLocalizedName(),
                'userGroupId' => $stageAssignment->getUserGroupId(),
                'userIdSelected' => $stageAssignment->getUserId(),
                'currentAssignmentRecommendOnly' => $stageAssignment->getRecommendOnly(),
                'currentAssignmentPermitMetadataEdit' => $stageAssignment->getCanChangeMetadata(),
                'isChangePermitMetadataAllowed' => $this->_isChangePermitMetadataAllowed($userGroup, $stageAssignment->getUserId()),
                'isChangeRecommendOnlyAllowed' => $this->_isChangeRecommendOnlyAllowed($userGroup, $stageAssignment->getUserId()),
            ]);
        }


        // If submission is in review, add a list of reviewer Ids that should not be
        // assigned as participants because they have anonymous peer reviews in progress
        $anonymousReviewerIds = [];
        if (in_array($this->getSubmission()->getStageId(), [WORKFLOW_STAGE_ID_INTERNAL_REVIEW, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW])) {
            $anonymousReviewMethods = [
                \PKP\submission\reviewAssignment\ReviewAssignment::SUBMISSION_REVIEW_METHOD_ANONYMOUS,
                \PKP\submission\reviewAssignment\ReviewAssignment::SUBMISSION_REVIEW_METHOD_DOUBLEANONYMOUS
            ];
            $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
            $reviewAssignments = $reviewAssignmentDao->getBySubmissionId($this->getSubmission()->getId());
            $anonymousReviews = array_filter($reviewAssignments, function ($reviewAssignment) use ($anonymousReviewMethods) {
                return in_array($reviewAssignment->getReviewMethod(), $anonymousReviewMethods) && !$reviewAssignment->getDeclined();
            });
            $anonymousReviewerIds = array_map(function ($reviewAssignment) {
                return $reviewAssignment->getReviewerId();
            }, $anonymousReviews);
        }
        $templateMgr->assign([
            'anonymousReviewerIds' => array_values(array_unique($anonymousReviewerIds)),
            'anonymousReviewerWarning' => __('editor.submission.addStageParticipant.form.reviewerWarning'),
            'anonymousReviewerWarningOk' => __('common.ok'),
        ]);

        return parent::fetch($request, $template, $display);
    }

    /**
     * @copydoc Form::readInputData()
     */
    public function readInputData()
    {
        $this->readUserVars([
            'userGroupId',
            'userId',
            'message',
            'template',
            'recommendOnly',
            'canChangeMetadata',
        ]);
    }

    /**
     * @copydoc Form::validate()
     */
    public function validate($callHooks = true)
    {
        $userGroupId = (int) $this->getData('userGroupId');
        $userId = (int) $this->getData('userId');

        return Repo::userGroup()->userInGroup($userId, $userGroupId) && Repo::userGroup()->get($userGroupId);
    }

    /**
     * @see Form::execute()
     *
     * @return array ($userGroupId, $userId)
     */
    public function execute(...$functionParams)
    {
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */

        $submission = $this->getSubmission();
        $userGroup = Repo::userGroup()->get((int) $this->getData('userGroupId'));
        $userId = (int) $this->getData('userId');
        $recommendOnly = $this->_isChangeRecommendOnlyAllowed($userGroup, $userId) ? (bool) $this->getData('recommendOnly') : false;
        $canChangeMetadata = $this->_isChangePermitMetadataAllowed($userGroup, $userId) ? (bool) $this->getData('canChangeMetadata') : true;

        // sanity check
        if (UserGroupStage::withStageId($this->getStageId())->withUserGroupId($userGroup->getId())->get()->isNotEmpty()) {
            $updated = false;

            if ($this->_assignmentId) {
                /** @var StageAssignment $stageAssignment */
                $stageAssignment = $stageAssignmentDao->getById($this->_assignmentId);

                if ($stageAssignment) {
                    $stageAssignment->setRecommendOnly($recommendOnly);
                    $stageAssignment->setCanChangeMetadata($canChangeMetadata);
                    $stageAssignmentDao->updateObject($stageAssignment);
                    $updated = true;
                }
            }

            if (!$updated) {
                // insert the assignment
                $stageAssignment = $stageAssignmentDao->build($submission->getId(), $userGroup->getId(), $userId, $recommendOnly, $canChangeMetadata);
            }
        }

        parent::execute(...$functionParams);
        return [$userGroup->getId(), $userId, $stageAssignment->getId()];
    }

    /**
     * whether or not to require a message field
     *
     * @return bool
     */
    public function isMessageRequired()
    {
        return false;
    }
}
