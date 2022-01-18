<?php

/**
 * @file pages/workflow/PKPDecisionHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPDecisionHandler
 * @ingroup pages_decision
 *
 * @brief Handle requests to take an editorial decision.
 */

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\handler\Handler;
use APP\i18n\AppLocale;
use APP\submission\Submission;
use APP\template\TemplateManager;
use PKP\context\Context;
use PKP\core\Dispatcher;
use PKP\db\DAORegistry;
use PKP\decision\Type;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\DecisionWritePolicy;
use PKP\security\authorization\internal\SubmissionRequiredPolicy;
use PKP\security\Role;
use PKP\submission\Genre;
use PKP\submission\GenreDAO;
use PKP\submission\reviewRound\ReviewRound;
use PKP\submission\reviewRound\ReviewRoundDAO;
use Stringy\Stringy;

class PKPDecisionHandler extends Handler
{
    /** @copydoc PKPHandler::_isBackendPage */
    public $_isBackendPage = true;

    protected Type $decisionType;
    protected Submission $submission;
    protected ?ReviewRound $reviewRound = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR],
            ['record']
        );
    }

    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments): bool
    {
        $op = $request->getRouter()->getRequestedOp($request);

        if (!$op || $op !== 'record') {
            return false;
        }

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
        AppLocale::requireComponents([
            LOCALE_COMPONENT_PKP_SUBMISSION,
            LOCALE_COMPONENT_APP_SUBMISSION,
            LOCALE_COMPONENT_PKP_EDITOR,
            LOCALE_COMPONENT_APP_EDITOR,
        ]);

        $this->decisionType = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_DECISION_TYPE);
        $this->submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        // Don't allow a decision unless the submission is at the correct stage
        if ($this->submission->getData('stageId') !== $this->decisionType->getStageId()) {
            $request->getDispatcher()->handle404();
        }

        // Don't allow a decision in a review stage unless there is a valid review round
        if (in_array($this->decisionType->getStageId(), [WORKFLOW_STAGE_ID_INTERNAL_REVIEW, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW])) {
            $reviewRoundId = (int) $request->getUserVar('reviewRoundId');
            if (!$reviewRoundId) {
                $request->getDispatcher()->handle404();
            }
            $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
            $this->reviewRound = $reviewRoundDao->getById($reviewRoundId);
            if (!$this->reviewRound || $this->reviewRound->getSubmissionId() !== $this->submission->getId()) {
                $request->getDispatcher()->handle404();
            }
        }

        // Don't allow a recommendation unless at least one deciding editor exists
        if (Repo::decision()->isRecommendation($this->decisionType->getDecision())) {
            /** @var StageAssignmentDAO $stageAssignmentDao  */
            $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
            $assignedEditorIds = $stageAssignmentDao->getDecidingEditorIds($this->submission->getId(), $this->decisionType->getStageId());
            if (!$assignedEditorIds) {
                $request->getDispatcher()->handle404();
            }
        }

        $workflow = $this->decisionType->getWorkflow(
            $this->submission,
            $context,
            $request->getUser(),
            $this->reviewRound
        );

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('pageComponent', 'DecisionPage');

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
            'stepErrorMessage' => __('editor.decision.stepError'),
            'stageId' => $this->submission->getStageId(),
            'submissionUrl' => $dispatcher->url(
                $request,
                Application::ROUTE_PAGE,
                $context->getData('urlPath'),
                'workflow',
                'access',
                [$this->submission->getId()]
            ),
            'submissionApiUrl' => $dispatcher->url(
                $request,
                Application::ROUTE_API,
                $context->getData('urlPath'),
                'submissions/' . $this->submission->getId()
            ),
            'viewSubmissionLabel' => __('submission.list.viewSubmission'),
            'workflow' => $workflow->getState(),
        ]);

        $templateMgr->assign([
            'breadcrumbs' => $this->getBreadcrumb($this->submission, $context, $request, $dispatcher),
            'decisionType' => $this->decisionType,
            'pageWidth' => TemplateManager::PAGE_WIDTH_WIDE,
            'reviewRound' => $this->reviewRound,
            'submission' => $this->submission,
        ]);

        $templateMgr->display('decision/record.tpl');
    }

    protected function getBreadcrumb(Submission $submission, Context $context, Request $request, Dispatcher $dispatcher)
    {
        $currentPublication = $submission->getCurrentPublication();
        $submissionTitle = Stringy::create(
            join(
                __('common.commaListSeparator'),
                [
                    $currentPublication->getShortAuthorString(),
                    $currentPublication->getLocalizedFullTitle(),
                ]
            )
        );
        if ($submissionTitle->length() > 50) {
            $submissionTitle = $submissionTitle->safeTruncate(50)
                ->append('...');
        }

        return [
            [
                'id' => 'submissions',
                'name' => __('navigation.submissions'),
                'url' => $dispatcher->url(
                    $request,
                    Application::ROUTE_PAGE,
                    $context->getData('urlPath'),
                    'submissions'
                ),
            ],
            [
                'id' => 'submission',
                'name' => $submissionTitle,
                'url' => $dispatcher->url(
                    $request,
                    Application::ROUTE_PAGE,
                    $context->getData('urlPath'),
                    'workflow',
                    'access',
                    [$submission->getId()]
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
