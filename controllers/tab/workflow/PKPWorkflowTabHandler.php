<?php

/**
 * @file controllers/tab/workflow/PKPWorkflowTabHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPWorkflowTabHandler
 *
 * @ingroup controllers_tab_workflow
 *
 * @brief Handle AJAX operations for workflow tabs.
 */

namespace PKP\controllers\tab\workflow;

use APP\core\Application;
use APP\core\Request;
use APP\handler\Handler;
use APP\notification\Notification;
use APP\notification\NotificationManager;
use APP\submission\Submission;
use APP\template\TemplateManager;
use PKP\core\JSONMessage;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\decision\DecisionType;
use PKP\notification\PKPNotification;
use PKP\security\authorization\WorkflowStageAccessPolicy;
use PKP\security\Role;

abstract class PKPWorkflowTabHandler extends Handler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment([Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_ASSISTANT], ['fetchTab']);
    }


    //
    // Extended methods from Handler
    //
    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        // Authorize stage id.
        $this->addPolicy(new WorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', $this->_identifyStageId($request), PKPApplication::WORKFLOW_TYPE_EDITORIAL));

        return parent::authorize($request, $args, $roleAssignments);
    }


    //
    // Public handler operations
    //
    /**
     * Fetch the specified workflow tab.
     *
     * @param array $args
     * @param Request $request
     *
     * @return JSONMessage JSON object
     */
    public function fetchTab($args, $request)
    {
        $this->setupTemplate($request);
        $templateMgr = TemplateManager::getManager($request);

        $stageId = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_WORKFLOW_STAGE);
        $templateMgr->assign('stageId', $stageId);

        /** @var Submission $submission */
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $templateMgr->assign('submission', $submission);

        switch ($stageId) {
            case WORKFLOW_STAGE_ID_SUBMISSION:
                return $templateMgr->fetchJson('controllers/tab/workflow/submission.tpl');
            case WORKFLOW_STAGE_ID_INTERNAL_REVIEW:
            case WORKFLOW_STAGE_ID_EXTERNAL_REVIEW:
                // Retrieve the authorized submission and stage id.
                $selectedStageId = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_WORKFLOW_STAGE);

                // Get all review rounds for this submission, on the current stage.
                $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
                $reviewRoundsFactory = $reviewRoundDao->getBySubmissionId($submission->getId(), $selectedStageId);
                $reviewRoundsArray = $reviewRoundsFactory->toAssociativeArray();
                $lastReviewRound = $reviewRoundDao->getLastReviewRoundBySubmissionId($submission->getId(), $selectedStageId);

                // Get the review round number of the last review round to be used
                // as the current review round tab index, if we have review rounds.
                if ($lastReviewRound) {
                    $lastReviewRoundNumber = $lastReviewRound->getRound();
                    $templateMgr->assign('lastReviewRoundNumber', $lastReviewRoundNumber);
                }

                // Add the round information to the template.
                $templateMgr->assign('reviewRounds', $reviewRoundsArray);
                $templateMgr->assign('reviewRoundOp', $this->_identifyReviewRoundOp($stageId));

                if ($submission->getStageId() == $selectedStageId && count($reviewRoundsArray) > 0) {
                    $newReviewRoundType = $this->getNewReviewRoundDecisionType($stageId);
                    $templateMgr->assign(
                        'newRoundUrl',
                        $newReviewRoundType->getUrl($request, $request->getContext(), $submission, $lastReviewRound->getId())
                    );
                }

                // Render the view.
                return $templateMgr->fetchJson('controllers/tab/workflow/review.tpl');
            case WORKFLOW_STAGE_ID_EDITING:
                // Assign banner notifications to the template.
                $notificationRequestOptions = [
                    Notification::NOTIFICATION_LEVEL_NORMAL => [
                        PKPNotification::NOTIFICATION_TYPE_ASSIGN_COPYEDITOR => [Application::ASSOC_TYPE_SUBMISSION, $submission->getId()],
                        PKPNotification::NOTIFICATION_TYPE_AWAITING_COPYEDITS => [Application::ASSOC_TYPE_SUBMISSION, $submission->getId()]],
                    Notification::NOTIFICATION_LEVEL_TRIVIAL => []
                ];
                $templateMgr->assign('editingNotificationRequestOptions', $notificationRequestOptions);
                return $templateMgr->fetchJson('controllers/tab/workflow/editorial.tpl');
            case WORKFLOW_STAGE_ID_PRODUCTION:
                $templateMgr = TemplateManager::getManager($request);
                $notificationRequestOptions = $this->getProductionNotificationOptions($submission->getId());
                $selectedStageId = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_WORKFLOW_STAGE);
                $templateMgr->assign('productionNotificationRequestOptions', $notificationRequestOptions);

                return $templateMgr->fetchJson('controllers/tab/workflow/production.tpl');
        }
    }

    /**
     * Setup variables for the template
     *
     * @param Request $request
     */
    public function setupTemplate($request)
    {
        parent::setupTemplate($request);

        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $stageId = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_WORKFLOW_STAGE);

        $templateMgr = TemplateManager::getManager($request);

        // Assign the authorized submission.
        $templateMgr->assign('submission', $submission);

        // Assign workflow stages related data.
        $templateMgr->assign('stageId', $stageId);
        $templateMgr->assign('submissionStageId', $submission->getStageId());

        // Get the right notifications type based on current stage id.
        $notificationMgr = new NotificationManager();
        $editorAssignmentNotificationType = $this->getEditorAssignmentNotificationTypeByStageId($stageId);

        // Define the workflow notification options.
        $notificationRequestOptions = [
            Notification::NOTIFICATION_LEVEL_TASK => [
                $editorAssignmentNotificationType => [Application::ASSOC_TYPE_SUBMISSION, $submission->getId()]
            ],
            Notification::NOTIFICATION_LEVEL_TRIVIAL => []
        ];

        $templateMgr->assign('workflowNotificationRequestOptions', $notificationRequestOptions);
    }

    /**
     * Return the editor assignment notification type based on stage id.
     *
     * @param int $stageId
     *
     * @return int
     */
    protected function getEditorAssignmentNotificationTypeByStageId($stageId)
    {
        switch ($stageId) {
            case WORKFLOW_STAGE_ID_SUBMISSION:
                return PKPNotification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_SUBMISSION;
            case WORKFLOW_STAGE_ID_EXTERNAL_REVIEW:
                return PKPNotification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_EXTERNAL_REVIEW;
            case WORKFLOW_STAGE_ID_EDITING:
                return PKPNotification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_EDITING;
            case WORKFLOW_STAGE_ID_PRODUCTION:
                return PKPNotification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_PRODUCTION;
        }
        return null;
    }

    /**
     * Get all production notification options to be used in the production stage tab.
     *
     * @param int $submissionId
     *
     * @return array
     */
    abstract protected function getProductionNotificationOptions($submissionId);

    /**
     * Get the decision type to create a new review round in this stage id
     *
     * @param int $stageId Must be one of the review stages, WORKFLOW_STAGE_ID_
     */
    abstract protected function getNewReviewRoundDecisionType(int $stageId): DecisionType;

    /**
     * Translate the requested operation to a stage id.
     *
     * @param Request $request
     *
     * @return int One of the WORKFLOW_STAGE_* constants.
     */
    private function _identifyStageId($request)
    {
        if ($stageId = $request->getUserVar('stageId')) {
            return (int) $stageId;
        }
    }

    /**
     * Identifies the review round.
     *
     * @param int $stageId
     *
     * @return string
     */
    private function _identifyReviewRoundOp($stageId)
    {
        switch ($stageId) {
            case WORKFLOW_STAGE_ID_INTERNAL_REVIEW:
                return 'internalReviewRound';
            case WORKFLOW_STAGE_ID_EXTERNAL_REVIEW:
                return 'externalReviewRound';
            default:
                fatalError('unknown review round id.');
        }
    }
}
