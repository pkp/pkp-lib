<?php

/**
 * @file controllers/tab/authorDashboard/AuthorDashboardTabHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AuthorDashboardTabHandler
 * @ingroup controllers_tab_authorDashboard
 *
 * @brief Handle AJAX operations for authorDashboard tabs.
 */

use APP\handler\Handler;
use APP\notification\Notification;
use APP\template\TemplateManager;
use PKP\core\JSONMessage;

use PKP\log\SubmissionEmailLogEntry;
use PKP\notification\PKPNotification;
use PKP\security\authorization\AuthorDashboardAccessPolicy;
use PKP\security\Role;

class AuthorDashboardTabHandler extends Handler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment([Role::ROLE_ID_AUTHOR], ['fetchTab']);
    }


    //
    // Extended methods from Handler
    //
    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new AuthorDashboardAccessPolicy($request, $args, $roleAssignments), true);

        return parent::authorize($request, $args, $roleAssignments);
    }


    //
    // Public handler operations
    //
    /**
     * Fetch the specified authorDashboard tab.
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

        $stageId = $request->getUserVar('stageId');
        $templateMgr->assign('stageId', $stageId);

        $submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
        $templateMgr->assign('submission', $submission);

        // Check if current author can access CopyeditFilesGrid within copyedit stage
        $canAccessCopyeditingStage = true;
        $userAllowedStages = $this->getAuthorizedContextObject(ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES);
        if (!array_key_exists(WORKFLOW_STAGE_ID_EDITING, $userAllowedStages)) {
            $canAccessCopyeditingStage = false;
        }
        $templateMgr->assign('canAccessCopyeditingStage', $canAccessCopyeditingStage);

        // Workflow-stage specific "upload file" action.
        $currentStage = $submission->getStageId();

        $templateMgr->assign('lastReviewRoundNumber', $this->_getLastReviewRoundNumber($submission, $currentStage));

        if (in_array($stageId, [WORKFLOW_STAGE_ID_INTERNAL_REVIEW, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW])) {
            $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
            $templateMgr->assign('reviewRounds', $reviewRoundDao->getBySubmissionId($submission->getId(), $stageId)->toArray());
        }

        // If the submission is in or past the editorial stage,
        // assign the editor's copyediting emails to the template
        $submissionEmailLogDao = DAORegistry::getDAO('SubmissionEmailLogDAO'); /** @var SubmissionEmailLogDAO $submissionEmailLogDao */
        $user = $request->getUser();

        // Define the notification options.
        $templateMgr->assign(
            'authorDashboardNotificationRequestOptions',
            $this->_getNotificationRequestOptions($submission)
        );

        switch ($stageId) {
            case WORKFLOW_STAGE_ID_SUBMISSION:
                return $templateMgr->fetchJson('controllers/tab/authorDashboard/submission.tpl');
            case WORKFLOW_STAGE_ID_INTERNAL_REVIEW:
                return $templateMgr->fetchJson('controllers/tab/authorDashboard/internalReview.tpl');
            case WORKFLOW_STAGE_ID_EXTERNAL_REVIEW:
                return $templateMgr->fetchJson('controllers/tab/authorDashboard/externalReview.tpl');
            case WORKFLOW_STAGE_ID_EDITING:
                $templateMgr->assign('copyeditingEmails', $submissionEmailLogDao->getByEventType($submission->getId(), SubmissionEmailLogEntry::SUBMISSION_EMAIL_COPYEDIT_NOTIFY_AUTHOR, $user->getId()));
                return $templateMgr->fetchJson('controllers/tab/authorDashboard/editorial.tpl');
            case WORKFLOW_STAGE_ID_PRODUCTION:
                $templateMgr->assign([
                    'productionEmails' => $submissionEmailLogDao->getByEventType($submission->getId(), SubmissionEmailLogEntry::SUBMISSION_EMAIL_PROOFREAD_NOTIFY_AUTHOR, $user->getId()),
                ]);
                return $templateMgr->fetchJson('controllers/tab/authorDashboard/production.tpl');
        }
    }

    /**
     * Get the last review round numbers in an array by stage name.
     *
     * @param Submission $submission
     * @param int $stageId WORKFLOW_STAGE_ID_...
     *
     * @return int Round number, 0 if none.
     */
    protected function _getLastReviewRoundNumber($submission, $stageId)
    {
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
        $lastExternalReviewRound = $reviewRoundDao->getLastReviewRoundBySubmissionId($submission->getId(), $stageId);
        if ($lastExternalReviewRound) {
            return $lastExternalReviewRound->getRound();
        }
        return 0;
    }

    /**
     * Get the notification request options.
     *
     * @param Submission $submission
     *
     * @return array
     */
    protected function _getNotificationRequestOptions($submission)
    {
        $submissionAssocTypeAndIdArray = [ASSOC_TYPE_SUBMISSION, $submission->getId()];
        return [
            Notification::NOTIFICATION_LEVEL_TASK => [
                PKPNotification::NOTIFICATION_TYPE_PENDING_EXTERNAL_REVISIONS => $submissionAssocTypeAndIdArray],
            Notification::NOTIFICATION_LEVEL_NORMAL => [
                PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_ACCEPT => $submissionAssocTypeAndIdArray,
                PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_EXTERNAL_REVIEW => $submissionAssocTypeAndIdArray,
                PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_PENDING_REVISIONS => $submissionAssocTypeAndIdArray,
                PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_RESUBMIT => $submissionAssocTypeAndIdArray,
                PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_NEW_ROUND => $submissionAssocTypeAndIdArray,
                PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_DECLINE => $submissionAssocTypeAndIdArray,
                PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_REVERT_DECLINE => $submissionAssocTypeAndIdArray,
                PKPNotification::NOTIFICATION_TYPE_EDITOR_DECISION_SEND_TO_PRODUCTION => $submissionAssocTypeAndIdArray],
            Notification::NOTIFICATION_LEVEL_TRIVIAL => []
        ];
    }
}
