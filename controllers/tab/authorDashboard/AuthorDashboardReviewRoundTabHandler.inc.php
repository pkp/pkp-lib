<?php

/**
 * @file controllers/tab/authorDashboard/AuthorDashboardReviewRoundTabHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AuthorDashboardReviewRoundTabHandler
 * @ingroup controllers_tab_authorDashboard
 *
 * @brief Handle AJAX operations for review round tabs on author dashboard page.
 */

// Import the base Handler.
import('pages.authorDashboard.AuthorDashboardHandler');

use APP\notification\Notification;
use APP\template\TemplateManager;
use APP\workflow\EditorDecisionActionsManager;

use PKP\core\JSONMessage;
use PKP\log\SubmissionEmailLogEntry;
use PKP\notification\PKPNotification;
use PKP\security\authorization\internal\ReviewRoundRequiredPolicy;
use PKP\security\authorization\internal\WorkflowStageRequiredPolicy;
use PKP\security\Role;

class AuthorDashboardReviewRoundTabHandler extends AuthorDashboardHandler
{
    /** @var boolean Overwrite backend page handling of AuthorDashboardHandler */
    public $_isBackendPage = false;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment([Role::ROLE_ID_AUTHOR], ['fetchReviewRoundInfo']);
    }


    //
    // Extended methods from Handler
    //
    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $stageId = (int)$request->getUserVar('stageId');

        // Authorize stage id.
        $this->addPolicy(new WorkflowStageRequiredPolicy($stageId));

        // We need a review round id in request.
        $this->addPolicy(new ReviewRoundRequiredPolicy($request, $args));

        return parent::authorize($request, $args, $roleAssignments);
    }


    //
    // Public handler operations
    //
    /**
     * Fetch information for the author on the specified review round
     *
     * @param $args array
     * @param $request Request
     *
     * @return JSONMessage JSON object
     */
    public function fetchReviewRoundInfo($args, $request)
    {
        $this->setupTemplate($request);
        $templateMgr = TemplateManager::getManager($request);

        $reviewRound = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ROUND);
        $submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
        $stageId = $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);
        if ($stageId !== WORKFLOW_STAGE_ID_INTERNAL_REVIEW && $stageId !== WORKFLOW_STAGE_ID_EXTERNAL_REVIEW) {
            fatalError('Invalid Stage Id');
        }

        $templateMgr->assign([
            'stageId' => $stageId,
            'reviewRoundId' => $reviewRound->getId(),
            'submission' => $submission,
            'reviewRoundNotificationRequestOptions' => [
                Notification::NOTIFICATION_LEVEL_NORMAL => [
                    PKPNotification::NOTIFICATION_TYPE_REVIEW_ROUND_STATUS => [ASSOC_TYPE_REVIEW_ROUND, $reviewRound->getId()]],
                Notification::NOTIFICATION_LEVEL_TRIVIAL => []
            ],
        ]);

        // If open reviews exist, show the reviewers grid
        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
        if ($reviewAssignmentDao->getOpenReviewsByReviewRoundId($reviewRound->getId())) {
            $templateMgr->assign('showReviewerGrid', true);
        }

        // Editor has taken an action and sent an email; Display the email
        if ((new EditorDecisionActionsManager())->getEditorTakenActionInReviewRound($request->getContext(), $reviewRound)) {
            $submissionEmailLogDao = DAORegistry::getDAO('SubmissionEmailLogDAO'); /** @var SubmissionEmailLogDAO $submissionEmailLogDao */
            $user = $request->getUser();
            $templateMgr->assign([
                'submissionEmails' => $submissionEmailLogDao->getByEventType($submission->getId(), SubmissionEmailLogEntry::SUBMISSION_EMAIL_EDITOR_NOTIFY_AUTHOR, $user->getId()),
                'showReviewAttachments' => true,
            ]);
        }

        return $templateMgr->fetchJson('authorDashboard/reviewRoundInfo.tpl');
    }
}
