<?php

/**
 * @file pages/decision/DecisionHandler.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2003-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DecisionHandler
 *
 * @ingroup pages_decision
 *
 * @brief Handle requests to take an editorial decision.
 */

namespace PKP\pages\decision;

use APP\core\Application;
use APP\core\PageRouter;
use APP\core\Request;
use APP\facades\Repo;
use APP\handler\Handler;
use APP\submission\Submission;
use APP\template\TemplateManager;
use Illuminate\Support\Str;
use PKP\context\Context;
use PKP\core\Dispatcher;
use PKP\db\DAORegistry;
use PKP\decision\DecisionType;
use PKP\decision\types\interfaces\DecisionRetractable;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\DecisionWritePolicy;
use PKP\security\authorization\internal\SubmissionRequiredPolicy;
use PKP\security\authorization\UserRequiredPolicy;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;
use PKP\submission\Genre;
use PKP\submission\GenreDAO;
use PKP\submission\reviewRound\ReviewRound;
use PKP\submission\reviewRound\ReviewRoundDAO;

class DecisionHandler extends Handler
{
    /** @copydoc PKPHandler::_isBackendPage */
    public $_isBackendPage = true;

    protected DecisionType $decisionType;
    protected Submission $submission;
    protected ?ReviewRound $reviewRound = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_SUB_EDITOR],
            ['record']
        );
    }

    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments): bool
    {
        /** @var PageRouter */
        $router = $request->getRouter();
        $op = $router->getRequestedOp($request);

        if (!$op || $op !== 'record') {
            return false;
        }

        $this->addPolicy(new UserRequiredPolicy($request));
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
        $this->addPolicy(new SubmissionRequiredPolicy($request, $args, 'submissionId'));
        $this->addPolicy(new DecisionWritePolicy($request, $args, (int) $request->getUserVar('decision'), $request->getUser()));

        return parent::authorize($request, $args, $roleAssignments);
    }

    public function record($args, $request)
    {
        $this->setupTemplate($request);
        $dispatcher = $request->getDispatcher();
        $context = $request->getContext();
        $reviewRoundId = (int) $request->getUserVar('reviewRoundId');

        $this->decisionType = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_DECISION_TYPE);
        $this->submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        // Don't allow a decision unless the submission is at the correct stage
        if ($this->submission->getData('stageId') !== $this->decisionType->getStageId()) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }

        // Don't allow a decision in a review stage unless there is a valid review round
        if (in_array($this->decisionType->getStageId(), [WORKFLOW_STAGE_ID_INTERNAL_REVIEW, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW])) {
            if (!$reviewRoundId) {
                throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
            }
            $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
            $this->reviewRound = $reviewRoundDao->getById($reviewRoundId);
            if (!$this->reviewRound || $this->reviewRound->getSubmissionId() !== $this->submission->getId()) {
                throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
            }
        }

        // For a retractable decision, don't allow if it can not be retracted
        if ($this->decisionType instanceof DecisionRetractable && !$this->decisionType->canRetract($this->submission, $reviewRoundId)) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }

        // Don't allow a recommendation unless at least one deciding editor exists
        if (Repo::decision()->isRecommendation($this->decisionType->getDecision())) {
            // Replaces StageAssignmentDAO::getDecidingEditorIds
            $assignedEditorIds = StageAssignment::withSubmissionIds([$this->submission->getId()])
                ->withStageIds([$this->decisionType->getStageId()])
                ->withRoleIds([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR])
                ->withRecommendOnly(false)
                ->get()
                ->pluck('user_id')
                ->all();

            if (!$assignedEditorIds) {
                throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
            }
        }

        $steps = $this->decisionType->getSteps(
            $this->submission,
            $context,
            $request->getUser(),
            $this->reviewRound
        );

        $templateMgr = TemplateManager::getManager($request);

        $templateMgr->setState([
            'abandonDecisionLabel' => __('editor.decision.cancelDecision'),
            'cancelConfirmationPrompt' => __('editor.decision.cancelDecision.confirmation'),
            'decision' => $this->decisionType->getDecision(),
            'decisionCompleteLabel' => $this->decisionType->getCompletedLabel(),
            'decisionCompleteDescription' => $this->decisionType->getCompletedMessage($this->submission),
            'emailTemplatesApiUrl' => $dispatcher->url(
                $request,
                Application::ROUTE_API,
                $context->getData('urlPath'),
                'emailTemplates'
            ),
            'fileGenres' => $this->getFileGenres($context),
            'keepWorkingLabel' => __('common.keepWorking'),
            'reviewRoundId' => $this->reviewRound ? $this->reviewRound->getId() : null,
            'stageId' => $this->submission->getData('stageId'),
            'stepErrorMessage' => __('editor.decision.stepError'),
            'steps' => $steps->getState(),
            'submissionUrl' => $dispatcher->url(
                $request,
                Application::ROUTE_PAGE,
                $context->getData('urlPath'),
                'dashboard',
                'editorial',
                null,
                ['workflowSubmissionId' => $this->submission->getId()]
            ),
            'submissionApiUrl' => $dispatcher->url(
                $request,
                Application::ROUTE_API,
                $context->getData('urlPath'),
                'submissions/' . $this->submission->getId()
            ),
            'submissionListUrl' => $dispatcher->url(
                $request,
                Application::ROUTE_PAGE,
                $context->getData('urlPath'),
                'dashboard',
            ),
            'viewAllSubmissionsLabel' => __('submission.list.viewAllSubmissions'),
            'viewSubmissionLabel' => __('submission.list.viewSubmission'),
            'viewSubmissionSummaryLabel' => __('submission.list.viewSubmissionSummary')
        ]);

        $templateMgr->assign([
            'breadcrumbs' => $this->getBreadcrumb($this->submission, $context, $request, $dispatcher),
            'decisionType' => $this->decisionType,
            'pageComponent' => 'DecisionPage',
            'pageWidth' => TemplateManager::PAGE_WIDTH_WIDE,
            'pageTitle' => join(
                __('common.titleSeparator'),
                [
                    $this->decisionType->getLabel(),
                    $this->submission->getCurrentPublication()->getShortAuthorString()
                        ? $this->submission->getCurrentPublication()->getShortAuthorString()
                        : $this->submission->getCurrentPublication()->getLocalizedFullTitle(),
                ]
            ),
            'reviewRound' => $this->reviewRound,
            'submission' => $this->submission,
        ]);

        $templateMgr->display('decision/record.tpl');
    }

    protected function getBreadcrumb(Submission $submission, Context $context, Request $request, Dispatcher $dispatcher)
    {
        $currentPublication = $submission->getCurrentPublication();
        $submissionTitle = Str::of(
            join(
                __('common.commaListSeparator'),
                [
                    $currentPublication->getShortAuthorString(),
                    $currentPublication->getLocalizedFullTitle(null, 'html'),
                ]
            )
        );
        $submissionTitle = $submissionTitle->limit(50, '...');

        return [
            [
                'id' => 'dashboard',
                'name' => __('navigation.dashboard'),
                'url' => $dispatcher->url(
                    $request,
                    Application::ROUTE_PAGE,
                    $context->getData('urlPath'),
                    'dashboard'
                ),
            ],
            [
                'id' => 'submission',
                'name' => (string) $submissionTitle,
                'format' => 'html',
                'url' => $dispatcher->url(
                    $request,
                    Application::ROUTE_PAGE,
                    $context->getData('urlPath'),
                    'dashboard',
                    'editorial',
                    null,
                    ['workflowSubmissionId' => $submission->getId()]
                ),
            ],
            [
                'id' => 'decision',
                'name' => $this->decisionType->getLabel(),
            ]
        ];
    }

    protected function getFileGenres(Context $context): array
    {
        $fileGenres = [];

        /** @var GenreDAO $genreDao */
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $genreResults = $genreDao->getEnabledByContextId($context->getId());
        /** @var Genre $genre */
        while ($genre = $genreResults->next()) {
            $fileGenres[] = [
                'id' => $genre->getId(),
                'name' => $genre->getLocalizedName(),
                'isPrimary' => !$genre->getSupplementary() && !$genre->getDependent(),
            ];
        }

        return $fileGenres;
    }
}
