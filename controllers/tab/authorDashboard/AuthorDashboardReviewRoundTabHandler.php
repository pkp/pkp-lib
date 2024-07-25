<?php

/**
 * @file controllers/tab/authorDashboard/AuthorDashboardReviewRoundTabHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AuthorDashboardReviewRoundTabHandler
 *
 * @ingroup controllers_tab_authorDashboard
 *
 * @brief Handle AJAX operations for review round tabs on author dashboard page.
 */

namespace PKP\controllers\tab\authorDashboard;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\pages\authorDashboard\AuthorDashboardHandler;
use APP\template\TemplateManager;
use PKP\core\JSONMessage;
use PKP\log\SubmissionEmailLogEventType;
use PKP\notification\Notification;
use PKP\security\authorization\internal\ReviewRoundRequiredPolicy;
use PKP\security\authorization\internal\WorkflowStageRequiredPolicy;
use PKP\security\Role;
use PKP\submission\reviewAssignment\ReviewAssignment;

class AuthorDashboardReviewRoundTabHandler extends AuthorDashboardHandler
{
    /** @var bool Overwrite backend page handling of AuthorDashboardHandler */
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
     * @param array $args
     * @param Request $request
     *
     * @return JSONMessage JSON object
     */
    public function fetchReviewRoundInfo($args, $request)
    {
        $this->setupTemplate($request);
        $templateMgr = TemplateManager::getManager($request);

        $reviewRound = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_REVIEW_ROUND);
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $stageId = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_WORKFLOW_STAGE);
        if ($stageId !== WORKFLOW_STAGE_ID_INTERNAL_REVIEW && $stageId !== WORKFLOW_STAGE_ID_EXTERNAL_REVIEW) {
            throw new \Exception('Invalid Stage Id');
        }

        $templateMgr->assign([
            'stageId' => $stageId,
            'reviewRoundId' => $reviewRound->getId(),
            'submission' => $submission,
            'reviewRoundNotificationRequestOptions' => [
                Notification::NOTIFICATION_LEVEL_NORMAL => [
                    Notification::NOTIFICATION_TYPE_REVIEW_ROUND_STATUS => [Application::ASSOC_TYPE_REVIEW_ROUND, $reviewRound->getId()]],
                Notification::NOTIFICATION_LEVEL_TRIVIAL => []
            ],
        ]);

        // If open reviews exist, show the reviewers grid
        $reviewAssignments = Repo::reviewAssignment()->getCollector()
            ->filterByReviewRoundIds([$reviewRound->getId()])
            ->filterByReviewMethods([ReviewAssignment::SUBMISSION_REVIEW_METHOD_OPEN])
            ->getMany();

        if ($reviewAssignments->isNotEmpty()) {
            $templateMgr->assign('showReviewerGrid', true);
        }

        // Display notification emails to the author related to editorial decisions
        $templateMgr->assign([
            'submissionEmails' => Repo::emailLogEntry()->getByEventType(
                $submission->getId(),
                SubmissionEmailLogEventType::EDITOR_NOTIFY_AUTHOR,
                Application::ASSOC_TYPE_SUBMISSION,
                $request->getUser()->getId()
            ),
            'showReviewAttachments' => true,
        ]);

        return $templateMgr->fetchJson('authorDashboard/reviewRoundInfo.tpl');
    }
}
