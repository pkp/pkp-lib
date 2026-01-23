<?php

/**
 * @file pages/reviewResponse/ReviewResponseHandler.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewResponseHandler
 *
 * @ingroup pages_reviewResponse
 *
 * @brief Handles page requests to review response page
 */

namespace PKP\pages\reviewResponse;

use APP\core\Request;
use APP\facades\Repo;
use APP\handler\Handler;
use APP\template\TemplateManager;
use PKP\components\RequestReviewResponsePage;
use PKP\db\DAORegistry;
use PKP\security\authorization\internal\ReviewRoundRequiredPolicy;
use PKP\security\authorization\ReviewStageAccessPolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\Role;

class ReviewResponseHandler extends Handler
{
    /** @copydoc PKPHandler::_isBackendPage */
    public $_isBackendPage = true;


    public function __construct()
    {
        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_SUB_EDITOR],
            'requestAuthorResponse'
        );

        parent::__construct();
    }

    /** @inheritDoc */
    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments, 'submissionId'));
        $this->addPolicy(new ReviewStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', (int)$request->getUserVar('stageId')));
        $this->addPolicy(new ReviewRoundRequiredPolicy($request, $args, 'reviewRoundId', ['requestAuthorResponse']));

        return parent::authorize($request, $args, $roleAssignments);
    }


    /**
     * Returns page to request a review response from authors.
     */
    public function requestAuthorResponse(array $args, Request $request): void
    {
        $reviewRoundId = (int)$request->getUserVar('reviewRoundId');
        $stageId = (int)$request->getUserVar('stageId');

        /** @var \PKP\submission\reviewRound\ReviewRoundDAO $reviewRoundDao */
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
        $reviewRound = $reviewRoundDao->getById($reviewRoundId);

        if (!$reviewRound) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }

        /**
         * Ensure the review round belongs to the current context.
         */
        $submission = Repo::submission()->get($reviewRound->getSubmissionId());
        $context = $request->getContext();

        if (!$submission || $context->getId() !== $submission->getData('contextId')) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }

        $requestReviewAuthorResponsePage = new RequestReviewResponsePage(
            reviewRound: $reviewRound,
            submission: $submission,
            stageId: $stageId,
            context: $context,
            locales: $context->getSupportedLocales(),
        );

        $this->setupTemplate($request);
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->setState([
            'pageInitConfig' => $requestReviewAuthorResponsePage->getConfig()
        ]);

        $templateMgr->assign([
            'breadcrumbs' => $requestReviewAuthorResponsePage->getBreadcrumb($submission, $context, $request),
            'pageWidth' => TemplateManager::PAGE_WIDTH_FULL,
        ]);

        $templateMgr->display('reviewResponse/requestAuthorResponse.tpl');
    }
}
