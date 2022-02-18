<?php

/**
 * @file controllers/tab/workflow/PKPWorkflowTabHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPWorkflowTabHandler
 * @ingroup controllers_tab_workflow
 *
 * @brief Handle AJAX operations for workflow tabs.
 */

use APP\handler\Handler;
use APP\notification\Notification;
use APP\notification\NotificationManager;
use APP\template\TemplateManager;
use APP\workflow\EditorDecisionActionsManager;
use PKP\core\JSONMessage;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
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
        $this->addRoleAssignment([Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_MANAGER, Role::ROLE_ID_ASSISTANT], ['fetchTab']);
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

        $stageId = $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);
        $templateMgr->assign('stageId', $stageId);

        $submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION); /** @var Submission $submission */
        $templateMgr->assign('submission', $submission);

        switch ($stageId) {
            case WORKFLOW_STAGE_ID_SUBMISSION:
                return $templateMgr->fetchJson('controllers/tab/workflow/submission.tpl');
            case WORKFLOW_STAGE_ID_INTERNAL_REVIEW:
            case WORKFLOW_STAGE_ID_EXTERNAL_REVIEW:
                // Retrieve the authorized submission and stage id.
                $selectedStageId = $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);

                // Get all review rounds for this submission, on the current stage.
                $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
                $reviewRoundsFactory = $reviewRoundDao->getBySubmissionId($submission->getId(), $selectedStageId);
                $reviewRoundsArray = $reviewRoundsFactory->toAssociativeArray();
                $lastReviewRound = $reviewRoundDao->getLastReviewRoundBySubmissionId($submission->getId(), $selectedStageId);

                // Get the review round number of the last review round to be used
                // as the current review round tab index, if we have review rounds.
                if ($lastReviewRound) {
                    $lastReviewRoundNumber = $lastReviewRound->getRound();
                    $lastReviewRoundId = $lastReviewRound->getId();
                    $templateMgr->assign('lastReviewRoundNumber', $lastReviewRoundNumber);
                } else {
                    $lastReviewRoundId = null;
                }

                // Add the round information to the template.
                $templateMgr->assign('reviewRounds', $reviewRoundsArray);
                $templateMgr->assign('reviewRoundOp', $this->_identifyReviewRoundOp($stageId));

                if ($submission->getStageId() == $selectedStageId && count($reviewRoundsArray) > 0) {
                    $dispatcher = $request->getDispatcher();

                    $newRoundAction = new LinkAction(
                        'newRound',
                        new AjaxModal(
                            $dispatcher->url(
                                $request,
                                PKPApplication::ROUTE_COMPONENT,
                                null,
                                'modals.editorDecision.EditorDecisionHandler',
                                'newReviewRound',
                                null,
                                [
                                    'submissionId' => $submission->getId(),
                                    'decision' => EditorDecisionActionsManager::SUBMISSION_EDITOR_DECISION_NEW_ROUND,
                                    'stageId' => $selectedStageId,
                                    'reviewRoundId' => $lastReviewRoundId
                                ]
                            ),
                            __('editor.submission.newRound'),
                            'modal_add_item'
                        ),
                        __('editor.submission.newRound'),
                        'add_item_small'
                    );
                    $templateMgr->assign('newRoundAction', $newRoundAction);
                }

                // Render the view.
                return $templateMgr->fetchJson('controllers/tab/workflow/review.tpl');
            case WORKFLOW_STAGE_ID_EDITING:
                // Assign banner notifications to the template.
                $notificationRequestOptions = [
                    Notification::NOTIFICATION_LEVEL_NORMAL => [
                        PKPNotification::NOTIFICATION_TYPE_ASSIGN_COPYEDITOR => [ASSOC_TYPE_SUBMISSION, $submission->getId()],
                        PKPNotification::NOTIFICATION_TYPE_AWAITING_COPYEDITS => [ASSOC_TYPE_SUBMISSION, $submission->getId()]],
                    Notification::NOTIFICATION_LEVEL_TRIVIAL => []
                ];
                $templateMgr->assign('editingNotificationRequestOptions', $notificationRequestOptions);
                return $templateMgr->fetchJson('controllers/tab/workflow/editorial.tpl');
            case WORKFLOW_STAGE_ID_PRODUCTION:
                $templateMgr = TemplateManager::getManager($request);
                $notificationRequestOptions = $this->getProductionNotificationOptions($submission->getId());
                $selectedStageId = $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);
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

        $submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
        $stageId = $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);

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
                $editorAssignmentNotificationType => [ASSOC_TYPE_SUBMISSION, $submission->getId()]
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
