<?php

/**
 * @file pages/workflow/PKPWorkflowHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPWorkflowHandler
 *
 * @ingroup pages_reviewer
 *
 * @brief Handle requests for the submission workflow.
 */

namespace PKP\pages\workflow;

use APP\components\forms\publication\PublishForm;
use APP\core\Application;
use APP\core\PageRouter;
use APP\core\Request;
use APP\facades\Repo;
use APP\handler\Handler;
use APP\publication\Publication;
use APP\submission\Submission;
use APP\template\TemplateManager;
use Exception;
use Illuminate\Support\Enumerable;
use PKP\components\forms\FormComponent;
use PKP\components\forms\publication\PKPCitationsForm;
use PKP\components\forms\publication\PKPMetadataForm;
use PKP\components\forms\publication\PKPPublicationLicenseForm;
use PKP\components\forms\publication\TitleAbstractForm;
use PKP\components\forms\submission\ChangeSubmissionLanguageMetadataForm;
use PKP\components\listPanels\ContributorsListPanel;
use PKP\components\PublicationSectionJats;
use PKP\config\Config;
use PKP\context\Context;
use PKP\core\JSONMessage;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\decision\Decision;
use PKP\facades\Locale;
use PKP\notification\Notification;
use PKP\plugins\PluginRegistry;
use PKP\security\authorization\internal\SubmissionCompletePolicy;
use PKP\security\authorization\internal\SubmissionRequiredPolicy;
use PKP\security\authorization\internal\UserAccessibleWorkflowStageRequiredPolicy;
use PKP\security\authorization\WorkflowStageAccessPolicy;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;
use PKP\submission\GenreDAO;
use PKP\submission\PKPSubmission;
use PKP\submission\reviewRound\ReviewRoundDAO;
use PKP\user\User;
use PKP\userGroup\UserGroup;
use PKP\workflow\WorkflowStageDAO;

abstract class PKPWorkflowHandler extends Handler
{
    /** @copydoc PKPHandler::_isBackendPage */
    public $_isBackendPage = true;

    //
    // Implement template methods from PKPHandler
    //
    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        /** @var PageRouter */
        $router = $request->getRouter();
        $operation = $router->getRequestedOp($request);

        if ($operation == 'access') {
            // Authorize requested submission.
            $this->addPolicy(new SubmissionRequiredPolicy($request, $args, 'submissionId'));

            // This policy will deny access if user has no accessible workflow stage.
            // Otherwise it will build an authorized object with all accessible
            // workflow stages and authorize user operation access.
            $this->addPolicy(new UserAccessibleWorkflowStageRequiredPolicy($request, PKPApplication::WORKFLOW_TYPE_EDITORIAL));

            $this->markRoleAssignmentsChecked();
        } else {
            $this->addPolicy(new SubmissionCompletePolicy($request, $args, 'submissionId'));
            $this->addPolicy(new WorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', $this->identifyStageId($request, $args), PKPApplication::WORKFLOW_TYPE_EDITORIAL));
        }

        return parent::authorize($request, $args, $roleAssignments);
    }


    //
    // Public handler methods
    //
    /**
     * Redirect users to their most appropriate
     * submission workflow stage.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function access($args, $request)
    {

        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        $currentStageId = $submission->getData('stageId');
        $accessibleWorkflowStages = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES);
        $workflowRoles = Application::getWorkflowTypeRoles();
        $editorialWorkflowRoles = $workflowRoles[PKPApplication::WORKFLOW_TYPE_EDITORIAL];

        // Get the closest workflow stage that user has an assignment.
        $workingStageId = null;
        for ($workingStageId = $currentStageId; $workingStageId >= WORKFLOW_STAGE_ID_SUBMISSION; $workingStageId--) {
            if (isset($accessibleWorkflowStages[$workingStageId]) && array_intersect($editorialWorkflowRoles, $accessibleWorkflowStages[$workingStageId] ?? [])) {
                break;
            }
        }

        // If no stage was found, user still have access to future stages of the
        // submission. Try to get the closest future workflow stage.
        if ($workingStageId == null) {
            for ($workingStageId = $currentStageId; $workingStageId <= WORKFLOW_STAGE_ID_PRODUCTION; $workingStageId++) {
                if (isset($accessibleWorkflowStages[$workingStageId]) && array_intersect($editorialWorkflowRoles, $accessibleWorkflowStages[$workingStageId] ?? [])) {
                    break;
                }
            }
        }

        assert(isset($workingStageId));

        $router = $request->getRouter();
        if (Config::getVar('features', 'enable_new_submission_listing')) {
            return $request->redirectUrl($router->url($request, null, 'dashboard', 'editorial', null, ['workflowSubmissionId' => $submission->getId()]));
        }
        $request->redirectUrl($router->url($request, null, 'workflow', 'index', [$submission->getId(), $workingStageId]));

    }

    /**
     * Show the workflow stage, with the stage path as an #anchor.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function index($args, $request)
    {

        if (Config::getVar('features', 'enable_new_submission_listing')) {
            $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
            $router = $request->getRouter();
            return $request->redirectUrl($router->url($request, null, 'dashboard', 'editorial', null, ['workflowSubmissionId' => $submission->getId()]));
        }


        $this->setupTemplate($request);
        $templateMgr = TemplateManager::getManager($request);

        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $requestedStageId = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_WORKFLOW_STAGE);

        $submissionContext = $request->getContext();
        if ($submission->getData('contextId') !== $submissionContext->getId()) {
            $submissionContext = app()->get('context')->get($submission->getData('contextId'));
        }

        $workflowStages = WorkflowStageDAO::getWorkflowStageKeysAndPaths();
        $accessibleWorkflowStages = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES);

        $workflowRoles = Application::getWorkflowTypeRoles();
        $editorialWorkflowRoles = $workflowRoles[PKPApplication::WORKFLOW_TYPE_EDITORIAL];

        $authorUserGroups = UserGroup::withRoleIds([Role::ROLE_ID_AUTHOR])
            ->withContextIds([$submission->getData('contextId')])
            ->get();
        $workflowUserGroups = Repo::userGroup()->getByRoleIds($editorialWorkflowRoles, $submission->getData('contextId'));

        // Publication tab
        // Users have access to the publication tab if they are assigned to
        // the active stage id or if they are assigned as an editor or if
        // they are not assigned in any role and have a manager role in the
        // context.
        $currentStageId = $submission->getData('stageId');
        $canAccessPublication = false; // View title, metadata, etc.
        $canEditPublication = Repo::submission()->canEditPublication($submission->getId(), $request->getUser()->getId());
        $canAccessProduction = false; // Access to galleys and issue entry
        $canPublish = false; // Ability to publish, unpublish and create versions
        $canAccessEditorialHistory = false; // Access to activity log
        $accessibleRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES) ?? [];
        // unassigned managers
        if (!$accessibleWorkflowStages && array_intersect($accessibleRoles, [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN])) {
            $canAccessProduction = true;
            $canPublish = true;
            $canAccessPublication = true;
            $canAccessEditorialHistory = true;
        } elseif (!empty($accessibleWorkflowStages[$currentStageId]) && array_intersect($editorialWorkflowRoles, $accessibleWorkflowStages[$currentStageId] ?? [])) {
            $canAccessProduction = (bool) array_intersect($editorialWorkflowRoles, $accessibleWorkflowStages[WORKFLOW_STAGE_ID_PRODUCTION] ?? []);
            $canAccessPublication = true;

            // Replaces StageAssignmentDAO::getBySubmissionAndUserIdAndStageId
            $stageAssignments = StageAssignment::query()
                ->withSubmissionIds([$submission->getId()])
                ->withUserId($request->getUser()->getId())
                ->withStageIds([WORKFLOW_STAGE_ID_PRODUCTION])
                ->get();

            // If they have no stage assignments, check the role they have been granted
            // for the production workflow stage. An unassigned admin or manager may
            // have been granted access and should be allowed to publish.
            if ($stageAssignments->isEmpty() && is_array($accessibleWorkflowStages[WORKFLOW_STAGE_ID_PRODUCTION] ?? null)) {
                $canPublish = (bool) array_intersect([Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER], $accessibleWorkflowStages[WORKFLOW_STAGE_ID_PRODUCTION]);
            } else {
                foreach ($stageAssignments as $stageAssignment) {
                    foreach ($workflowUserGroups as $workflowUserGroup) {
                        if ($stageAssignment->userGroupId == $workflowUserGroup->id &&
                            !$stageAssignment->recommendOnly) {
                            $canPublish = true;
                            break;
                        }
                    }
                }
            }
        }
        if (!empty($accessibleWorkflowStages[$currentStageId]) && array_intersect([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_SUB_EDITOR], $accessibleWorkflowStages[$currentStageId] ?? [])) {
            $canAccessEditorialHistory = true;
        }
        /** @var GenreDAO $genreDao */
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $genres = $genreDao->getByContextId($submission->getData('contextId'))->toArray();

        $latestPublication = $submission->getLatestPublication();

        $submissionLocale = $submission->getData('locale');
        $locales = collect($submissionContext->getSupportedSubmissionMetadataLocaleNames() + $submission->getPublicationLanguageNames())
            ->map(fn (string $name, string $locale) => ['key' => $locale, 'label' => $name])
            ->sortBy('key')
            ->values()
            ->toArray();

        $submissionApiUrl = $request->getDispatcher()->url($request, Application::ROUTE_API, $submissionContext->getData('urlPath'), 'submissions/' . $submission->getId());
        $submissionFileApiUrl = $request->getDispatcher()->url($request, Application::ROUTE_API, $submissionContext->getData('urlPath'), 'submissions/' . $submission->getId() . '/files');
        $latestPublicationApiUrl = $request->getDispatcher()->url($request, Application::ROUTE_API, $submissionContext->getData('urlPath'), 'submissions/' . $submission->getId() . '/publications/' . $latestPublication->getId());

        $decisionUrl = $request->url(
            $submissionContext->getData('urlPath'),
            'decision',
            'record',
            [$submission->getId()],
            [
                'decision' => '__decision__',
                'reviewRoundId' => '__reviewRoundId__',
            ]
        );

        $editorialHistoryUrl = $request->getDispatcher()->url(
            $request,
            Application::ROUTE_COMPONENT,
            null,
            'informationCenter.SubmissionInformationCenterHandler',
            'viewInformationCenter',
            null,
            ['submissionId' => $submission->getId()]
        );

        $submissionLibraryUrl = $request->getDispatcher()->url(
            $request,
            Application::ROUTE_COMPONENT,
            null,
            'modals.documentLibrary.DocumentLibraryHandler',
            'documentLibrary',
            null,
            ['submissionId' => $submission->getId()]
        );

        $publishUrl = $request->getDispatcher()->url(
            $request,
            Application::ROUTE_COMPONENT,
            null,
            'modals.publish.PublishHandler',
            'publish',
            null,
            [
                'submissionId' => $submission->getId(),
                'publicationId' => '__publicationId__',
            ]
        );

        $citationsForm = new PKPCitationsForm($latestPublicationApiUrl, $latestPublication);
        $publicationLicenseForm = new PKPPublicationLicenseForm($latestPublicationApiUrl, $locales, $latestPublication, $submissionContext, $authorUserGroups);
        $titleAbstractForm = $this->getTitleAbstractForm($latestPublicationApiUrl, $locales, $latestPublication, $submissionContext);

        $authorItems = [];
        foreach ($latestPublication->getData('authors') as $contributor) {
            $authorItems[] = Repo::author()->getSchemaMap()->map($contributor);
        }

        $contributorsListPanel = $this->getContributorsListPanel(
            $submission,
            $submissionContext,
            $locales,
            $authorItems,
            $canEditPublication
        );

        $changeSubmissionLanguageApiUrl = $request->getDispatcher()->url($request, Application::ROUTE_API, $submissionContext->getData('urlPath'), "submissions/{$submission->getId()}/publications/{$latestPublication->getId()}/changeLocale");
        $changeSubmissionLanguageMetadataForm = new ChangeSubmissionLanguageMetadataForm($changeSubmissionLanguageApiUrl, $submission, $latestPublication, $submissionContext);

        $templateMgr->setConstants([
            'STATUS_QUEUED' => PKPSubmission::STATUS_QUEUED,
            'STATUS_PUBLISHED' => PKPSubmission::STATUS_PUBLISHED,
            'STATUS_DECLINED' => PKPSubmission::STATUS_DECLINED,
            'STATUS_SCHEDULED' => PKPSubmission::STATUS_SCHEDULED,
            'FORM_CHANGE_SUBMISSION_LANGUAGE_METADATA' => $changeSubmissionLanguageMetadataForm::FORM_CHANGE_SUBMISSION_LANGUAGE_METADATA,
            'FORM_CITATIONS' => $citationsForm::FORM_CITATIONS,
            'FORM_PUBLICATION_LICENSE' => $publicationLicenseForm::FORM_PUBLICATION_LICENSE,
            'FORM_PUBLISH' => PublishForm::FORM_PUBLISH,
            'FORM_TITLE_ABSTRACT' => $titleAbstractForm::FORM_TITLE_ABSTRACT,
        ]);
        $templateMgr->registerClass($publicationLicenseForm::class, $publicationLicenseForm::class); // FORM_PUBLICATION_LICENSE
        $templateMgr->registerClass(PublishForm::class, PublishForm::class); // FORM_PUBLISH;
        $templateMgr->registerClass($titleAbstractForm::class, $titleAbstractForm::class); // FORM_TITLE_ABSTRACT
        $templateMgr->registerClass($citationsForm::class, $citationsForm::class); // FORM_CITATIONS

        // Get the submission props without the full publication details. We'll
        // retrieve just the publication information that we need separately to
        // reduce the amount of data passed to the browser
        $submissionProps = Repo::submission()->getSchemaMap()->summarizeWithoutPublication($submission);

        // Get an array of publications
        $publications = $submission->getData('publications'); /** @var Enumerable $publications */
        $publicationList = $publications->map(function ($publication) {
            return [
                'id' => $publication->getId(),
                'datePublished' => $publication->getData('datePublished'),
                'status' => $publication->getData('status'),
                'version' => $publication->getData('version')
            ];
        })->values();

        // Get full details of the working publication and the current publication
        $mapper = Repo::publication()->getSchemaMap($submission, $authorUserGroups, $genres);
        $workingPublicationProps = $mapper->map($submission->getLatestPublication());
        $currentPublicationProps = $submission->getLatestPublication()->getId() === $submission->getCurrentPublication()->getId()
            ? $workingPublicationProps
            : $mapper->map($submission->getCurrentPublication());

        $state = [
            'activityLogLabel' => __('submission.list.infoCenter'),
            'canAccessPublication' => $canAccessPublication,
            'canChangeSubmissionLanguage' => $canPublish || $canEditPublication,
            'canEditPublication' => $canEditPublication,
            'currentSubmissionLanguageLabel' => Locale::getSubmissionLocaleDisplayNames([$submissionLocale])[$submissionLocale],
            'components' => [
                $changeSubmissionLanguageMetadataForm->id => $this->getLocalizedForm($changeSubmissionLanguageMetadataForm, $submissionLocale, $locales),
                $contributorsListPanel->id => $contributorsListPanel->getConfig(),
                $citationsForm->id => $citationsForm->getConfig(),
                $publicationLicenseForm->id => $this->getLocalizedForm($publicationLicenseForm, $submissionLocale, $locales),
                $titleAbstractForm->id => $this->getLocalizedForm($titleAbstractForm, $submissionLocale, $locales),
            ],
            'currentPublication' => $currentPublicationProps,
            'decisionUrl' => $decisionUrl,
            'editorialHistoryUrl' => $editorialHistoryUrl,
            'publicationFormIds' => [
                $citationsForm::FORM_CITATIONS,
                $publicationLicenseForm::FORM_PUBLICATION_LICENSE,
                PublishForm::FORM_PUBLISH,
                $titleAbstractForm::FORM_TITLE_ABSTRACT,
            ],
            'publicationList' => $publicationList,
            'publicationTabsLabel' => __('publication.version.details'),
            'publishLabel' => __('publication.publish'),
            'publishUrl' => $publishUrl,
            'representationsGridUrl' => $this->_getRepresentationsGridUrl($request, $submission),
            'schedulePublicationLabel' => __('editor.submission.schedulePublication'),
            'statusLabel' => __('semicolon', ['label' => __('common.status')]),
            'submission' => $submissionProps,
            'submissionFileApiUrl' => $submissionFileApiUrl,
            'submissionApiUrl' => $submissionApiUrl,
            'submissionLibraryLabel' => __('grid.libraryFiles.submission.title'),
            'submissionLibraryUrl' => $submissionLibraryUrl,
            'submissionSupportedLocales' => $submissionContext->getSupportedSubmissionLocales(),
            'supportsReferences' => !!$submissionContext->getData('citations'),
            'unpublishConfirmLabel' => __('publication.unpublish.confirm'),
            'unpublishLabel' => __('publication.unpublish'),
            'unscheduleConfirmLabel' => __('publication.unschedule.confirm'),
            'unscheduleLabel' => __('publication.unschedule'),
            'versionLabel' => __('semicolon', ['label' => __('admin.version')]),
            'versionConfirmTitle' => __('publication.createVersion'),
            'versionConfirmMessage' => __('publication.version.confirm'),
            'workingPublication' => $workingPublicationProps,
        ];

        // Add the metadata form if one or more metadata fields are enabled
        $vocabSuggestionUrlBase = $request->getDispatcher()->url($request, PKPApplication::ROUTE_API, $submissionContext->getData('urlPath'), 'vocabs', null, null, ['vocab' => '__vocab__', 'submissionId' => $submission->getId()]);
        $metadataForm = new PKPMetadataForm($latestPublicationApiUrl, $locales, $latestPublication, $submissionContext, $vocabSuggestionUrlBase, true);
        $metadataEnabled = count($metadataForm->fields);

        if ($metadataEnabled) {
            $templateMgr->setConstants([
                'FORM_METADATA' => PKPMetadataForm::FORM_METADATA,
            ]);
            $state['components'][PKPMetadataForm::FORM_METADATA] = $this->getLocalizedForm($metadataForm, $submissionLocale, $locales);
            $state['publicationFormIds'][] = PKPMetadataForm::FORM_METADATA;
        }

        // Add the identifiers form if one or more identifier is enabled
        $identifiersEnabled = false;
        $pubIdPlugins = PluginRegistry::getPlugins('pubIds');
        foreach ($pubIdPlugins as $pubIdPlugin) {
            if ($pubIdPlugin->isObjectTypeEnabled('Publication', $request->getContext()->getId())) {
                $identifiersEnabled = true;
                break;
            }
        }
        if ($identifiersEnabled) {
            $identifiersForm = new \PKP\components\forms\publication\PKPPublicationIdentifiersForm($latestPublicationApiUrl, $locales, $latestPublication, $submissionContext);
            $templateMgr->setConstants([
                'FORM_PUBLICATION_IDENTIFIERS' => $identifiersForm::FORM_PUBLICATION_IDENTIFIERS,
            ]);
            $state['components'][$identifiersForm::FORM_PUBLICATION_IDENTIFIERS] = $identifiersForm->getConfig();
            $state['publicationFormIds'][] = $identifiersForm::FORM_PUBLICATION_IDENTIFIERS;
            $templateMgr->registerClass($identifiersForm::class, $identifiersForm::class); // FORM_PUBLICATION_IDENTIFIERS
        }

        // Add the revision decision/recommendation forms if this app supports a review stage
        if (count(array_intersect([WORKFLOW_STAGE_ID_INTERNAL_REVIEW, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW], Application::getApplicationStages() ?? []))) {
            $selectRevisionDecisionForm = new \PKP\components\forms\decision\SelectRevisionDecisionForm();
            $selectRevisionRecommendationForm = new \PKP\components\forms\decision\SelectRevisionRecommendationForm();
            $state['components'][$selectRevisionDecisionForm->id] = $selectRevisionDecisionForm->getConfig();
            $state['components'][$selectRevisionRecommendationForm->id] = $this->getLocalizedForm($selectRevisionRecommendationForm, $submissionLocale, $locales);
            $templateMgr->setConstants([
                'FORM_SELECT_REVISION_DECISION' => $selectRevisionDecisionForm::FORM_SELECT_REVISION_DECISION,
                'FORM_SELECT_REVISION_RECOMMENDATION' => $selectRevisionRecommendationForm::FORM_SELECT_REVISION_RECOMMENDATION,
            ]);
        }

        $templateMgr->setState($state);
        $publication = $submission->getLatestPublication();

        $templateMgr->assign([
            'canAccessEditorialHistory' => $canAccessEditorialHistory,
            'canAccessPublication' => $canAccessPublication,
            'canEditPublication' => $canEditPublication,
            'canAccessProduction' => $canAccessProduction,
            'canPublish' => $canPublish,
            'identifiersEnabled' => $identifiersEnabled,
            'metadataEnabled' => $metadataEnabled,
            'pageComponent' => 'WorkflowPage',
            'pageTitle' => implode(__('common.titleSeparator'), array_filter([
                $publication->getShortAuthorString(),
                $publication->getLocalizedTitle()
            ])),
            'pageWidth' => TemplateManager::PAGE_WIDTH_WIDE,
            'requestedStageId' => $requestedStageId,
            'submission' => $submission,
            'workflowStages' => $workflowStages,
        ]);

        $this->setupIndex($request);

        $templateMgr->display('workflow/workflow.tpl');
    }

    /**
     * Show the submission stage.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function submission($args, $request)
    {
        $this->_redirectToIndex($args, $request);
    }

    /**
     * Show the external review stage.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function externalReview($args, $request)
    {
        $this->_redirectToIndex($args, $request);
    }

    /**
     * Show the editorial stage
     *
     * @param PKPRequest $request
     * @param array $args
     */
    public function editorial($args, $request)
    {
        $this->_redirectToIndex($args, $request);
    }

    /**
     * Show the production stage
     *
     * @param PKPRequest $request
     * @param array $args
     */
    public function production($args, $request)
    {
        $this->_redirectToIndex($args, $request);
    }

    /**
     * Redirect all old stage paths to index
     *
     * @param array $args
     * @param PKPRequest $request
     */
    protected function _redirectToIndex($args, $request)
    {
        // Translate the operation to a workflow stage identifier.
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $router = $request->getRouter();
        $workflowPath = $router->getRequestedOp($request);
        $stageId = WorkflowStageDAO::getIdFromPath($workflowPath);
        $request->redirectUrl($router->url($request, null, 'workflow', 'index', [$submission->getId(), $stageId]));
    }

    /**
     * Fetch JSON-encoded editor decision options.
     *
     * @param array $args
     * @param Request $request
     *
     * @return JSONMessage JSON object
     */
    public function editorDecisionActions($args, $request)
    {
        $this->setupTemplate($request);
        $reviewRoundId = (int) $request->getUserVar('reviewRoundId');

        // Prepare the action arguments.
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $stageId = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_WORKFLOW_STAGE);

        $actionArgs = [
            'submissionId' => $submission->getId(),
            'stageId' => (int) $stageId,
        ];

        // If a review round was specified, include it in the args;
        // must also check that this is the last round or decisions
        // cannot be recorded.
        $reviewRound = null;
        if ($reviewRoundId) {
            $actionArgs['reviewRoundId'] = $reviewRoundId;
            $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
            $lastReviewRound = $reviewRoundDao->getLastReviewRoundBySubmissionId($submission->getId(), $stageId);
            $reviewRound = $reviewRoundDao->getById($reviewRoundId);
        } else {
            $lastReviewRound = null;
        }

        // If there is an editor assigned, retrieve stage decisions.
        // Replaces StageAssignmentDAO::getEditorsAssignedToStage
        $editorsStageAssignments = StageAssignment::withSubmissionIds([$submission->getId()])
            ->withStageIds([$stageId])
            ->withRoleIds([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR])
            ->get();

        $user = $request->getUser();

        $makeRecommendation = $makeDecision = false;
        // if the user is assigned several times in an editorial role, check his/her assignments permissions i.e.
        // if the user is assigned with both possibilities: to only recommend as well as make decision
        foreach ($editorsStageAssignments as $editorsStageAssignment) {
            if ($editorsStageAssignment->userId == $user->getId()) {
                if (!$editorsStageAssignment->recommendOnly) {
                    $makeDecision = true;
                } else {
                    $makeRecommendation = true;
                }
            }
        }

        // If user is not assigned to the submission,
        // see if the user is manager, and
        // if the group is recommendOnly
        if (!$makeRecommendation && !$makeDecision) {
            $userGroups = UserGroup::query()
                ->withContextIds([$request->getContext()->getId()])
                ->withUserIds([$user->getId()])
                ->withRoleIds([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN])
                ->get();

            foreach ($userGroups as $userGroup) {
                if (!$userGroup->recommendOnly) {
                    $makeDecision = true;
                } else {
                    $makeRecommendation = true;
                }
            }
        }

        // if the user can make recommendations, check whether there are any decisions that can be made given
        // the stage that we are operating into.
        $isOnlyRecommending = $makeRecommendation && !$makeDecision;

        if ($isOnlyRecommending) {
            $recommendatorsAvailableDecisions = Repo::decision()
                ->getDecisionTypesMadeByRecommendingUsers($stageId);

            if (!empty($recommendatorsAvailableDecisions)) {
                // If there are any, then the user can be considered a decision user.
                $makeDecision = true;
            }
        }

        $lastRecommendation = null;
        $allRecommendations = null;
        $hasDecidingEditors = false;
        if ($editorsStageAssignments->isNotEmpty() && (!$reviewRoundId || ($lastReviewRound && $reviewRoundId == $lastReviewRound->getId()))) {
            // If this is a review stage and the user has "recommend only role"
            if (($stageId == WORKFLOW_STAGE_ID_EXTERNAL_REVIEW || $stageId == WORKFLOW_STAGE_ID_INTERNAL_REVIEW)) {
                if ($makeRecommendation) {
                    // Get the made editorial decisions from the current user
                    $editorDecisions = Repo::decision()->getCollector()
                        ->filterBySubmissionIds([$submission->getId()])
                        ->filterByStageIds([$stageId])
                        ->filterByReviewRoundIds([$reviewRound->getId()])
                        ->filterByEditorIds([$user->getId()])
                        ->getMany();

                    // Get the last recommendation
                    foreach ($editorDecisions as $editorDecision) {
                        if (Repo::decision()->isRecommendation($editorDecision->getData('decision'))) {
                            if ($lastRecommendation) {
                                if ($editorDecision->getData('dateDecided') >= $lastRecommendation->getData('dateDecided')) {
                                    $lastRecommendation = $editorDecision;
                                }
                            } else {
                                $lastRecommendation = $editorDecision;
                            }
                        }
                    }
                    if ($lastRecommendation) {
                        $lastRecommendation = $this->getRecommendationLabel($lastRecommendation->getData('decision'));
                    }

                    // At least one deciding editor must be assigned before a recommendation can be made
                    // Replaces StageAssignmentDAO::getDecidingEditorIds
                    $hasDecidingEditors = StageAssignment::withSubmissionIds([$submission->getId()])
                        ->withStageIds([$stageId])
                        ->withRoleIds([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR])
                        ->withRecommendOnly(false)
                        ->exists();

                } elseif ($makeDecision) {
                    // Get the made editorial decisions from all users
                    $editorDecisions = Repo::decision()
                        ->getCollector()
                        ->filterBySubmissionIds([$submission->getId()])
                        ->filterByStageIds([$stageId])
                        ->filterByReviewRoundIds([$reviewRound->getId()])
                        ->getMany();

                    // Get all recommendations
                    $recommendations = [];
                    foreach ($editorDecisions as $editorDecision) {
                        if (Repo::decision()->isRecommendation($editorDecision->getData('decision'))) {
                            if (array_key_exists($editorDecision->getData('editorId'), $recommendations)) {
                                if ($editorDecision->getData('dateDecided') >= $recommendations[$editorDecision->getData('editorId')]['dateDecided']) {
                                    $recommendations[$editorDecision->getData('editorId')] = ['dateDecided' => $editorDecision->getData('dateDecided'), 'decision' => $editorDecision->getData('decision')];
                                }
                            } else {
                                $recommendations[$editorDecision->getData('editorId')] = ['dateDecided' => $editorDecision->getData('dateDecided'), 'decision' => $editorDecision->getData('decision')];
                            }
                        }
                    }
                    $allRecommendations = [];
                    foreach ($recommendations as $recommendation) {
                        $allRecommendations[] = $this->getRecommendationLabel($recommendation['decision']);
                    }
                    $allRecommendations = join(__('common.commaListSeparator'), $allRecommendations);
                }
            }
        }

        $hasSubmissionPassedThisStage = $submission->getData('stageId') > $stageId;
        $lastDecision = null;
        switch ($submission->getData('status')) {
            case PKPSubmission::STATUS_QUEUED:
                switch ($stageId) {
                    case WORKFLOW_STAGE_ID_SUBMISSION:
                        if ($hasSubmissionPassedThisStage) {
                            $lastDecision = 'editor.submission.workflowDecision.submission.underReview';
                        }
                        break;
                    case WORKFLOW_STAGE_ID_INTERNAL_REVIEW:
                    case WORKFLOW_STAGE_ID_EXTERNAL_REVIEW:
                        if ($reviewRoundId < $lastReviewRound->getId()) {
                            $lastDecision = 'editor.submission.workflowDecision.submission.reviewRound';
                        } elseif ($hasSubmissionPassedThisStage) {
                            $lastDecision = 'editor.submission.workflowDecision.submission.accepted';
                        }
                        break;
                    case WORKFLOW_STAGE_ID_EDITING:
                        if ($hasSubmissionPassedThisStage) {
                            $lastDecision = 'editor.submission.workflowDecision.submission.production';
                        }
                        break;
                }
                break;
            case PKPSubmission::STATUS_PUBLISHED:
                $lastDecision = 'editor.submission.workflowDecision.submission.published';
                break;
            case PKPSubmission::STATUS_DECLINED:
                $lastDecision = 'editor.submission.workflowDecision.submission.declined';
                break;
        }

        $canRecordDecision =
            // Only allow decisions to be recorded on the submission's current stage
            $submission->getData('stageId') == $stageId

            // Only allow decisions on the latest review round
            && (!$lastReviewRound || $lastReviewRound->getId() == $reviewRoundId)

            // At least one deciding editor must be assigned to make a recommendation
            && ($makeDecision || $hasDecidingEditors);

        $decisions = $this->getStageDecisionTypes($stageId);
        if ($isOnlyRecommending) {
            $decisions = Repo::decision()
                ->getDecisionTypesMadeByRecommendingUsers($stageId);
        }

        // Assign the actions to the template.
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'canRecordDecision' => $canRecordDecision,
            'decisions' => $decisions,
            'recommendations' => $this->getStageRecommendationTypes($stageId),
            'primaryDecisions' => $this->getPrimaryDecisionTypes(),
            'warnableDecisions' => $this->getWarnableDecisionTypes(),
            'editorsAssigned' => $editorsStageAssignments->isNotEmpty(),
            'stageId' => $stageId,
            'reviewRoundId' => $reviewRound
                ? $reviewRound->getId()
                : null,
            'lastDecision' => $lastDecision,
            'lastReviewRound' => $lastReviewRound,
            'submission' => $submission,
            'makeRecommendation' => $makeRecommendation,
            'makeDecision' => $makeDecision,
            'lastRecommendation' => $lastRecommendation,
            'allRecommendations' => $allRecommendations,
        ]);
        $templateMgr->registerClass('Decision', Decision::class);
        return $templateMgr->fetchJson('workflow/editorialLinkActions.tpl');
    }

    /**
     * Fetch the JSON-encoded submission progress bar.
     *
     * @param array $args
     * @param Request $request
     *
     * @return JSONMessage JSON object
     */
    public function submissionProgressBar($args, $request)
    {
        $this->setupTemplate($request);
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $workflowStages = WorkflowStageDAO::getWorkflowStageKeysAndPaths();

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'submission' => $submission,
            'currentStageId' => $this->identifyStageId($request, $args),
            'workflowStages' => $workflowStages,
        ]);

        return $templateMgr->fetchJson('workflow/submissionProgressBar.tpl');
    }

    /**
     * Placeholder method to be overridden by apps in order to add
     * app-specific data to the template
     *
     * @param Request $request
     */
    public function setupIndex($request)
    {
    }

    //
    // Protected helper methods
    //

    /**
     * Translate the requested operation to a stage id.
     *
     * @param Request $request
     * @param array $args
     *
     * @return int One of the WORKFLOW_STAGE_* constants.
     */
    protected function identifyStageId($request, $args)
    {
        if ($stageId = $request->getUserVar('stageId')) {
            return (int) $stageId;
        }

        // Maintain the old check for previous path urls
        $router = $request->getRouter();
        $workflowPath = $router->getRequestedOp($request);
        $stageId = WorkflowStageDAO::getIdFromPath($workflowPath);
        if ($stageId) {
            return $stageId;
        }

        // Finally, retrieve the requested operation, if the stage id is
        // passed in via an argument in the URL, like index/submissionId/stageId
        $stageId = $args[1];

        // Translate the operation to a workflow stage identifier.
        assert(WorkflowStageDAO::getPathFromId($stageId) !== null);
        return $stageId;
    }

    /**
     * Determine if a particular stage has a notification pending.  If so, return true.
     * This is used to set the CSS class of the submission progress bar.
     *
     * @param User $user
     * @param int $stageId
     *
     * @return bool
     */
    protected function notificationOptionsByStage($user, $stageId, int $contextId)
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        $editorAssignmentNotificationType = $this->getEditorAssignmentNotificationTypeByStageId($stageId);

        $notification = Notification::withAssoc(Application::ASSOC_TYPE_SUBMISSION, $submission->getId())
            ->withType($editorAssignmentNotificationType)
            ->withContextId($contextId)
            ->first();

        // if the User has assigned TASKs in this stage check, return true
        if ($notification) {
            return true;
        }

        // check for more specific notifications on those stages that have them.
        if ($stageId == WORKFLOW_STAGE_ID_PRODUCTION) {
            $submissionApprovalNotification = Notification::withAssoc(Application::ASSOC_TYPE_SUBMISSION, $submission->getId())
                ->withType(Notification::NOTIFICATION_TYPE_APPROVE_SUBMISSION)
                ->withContextId($contextId)
                ->first();
            if ($submissionApprovalNotification) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the form configuration data with the correct
     * locale settings based on the publication's locale
     *
     * Uses the publication locale as the primary and
     * visible locale, and puts that locale first in the
     * list of supported locales.
     *
     * Call this instead of $form->getConfig() to display
     * a form with the correct publication locales
     */
    protected function getLocalizedForm(FormComponent $form, string $submissionLocale, array $locales): array
    {
        $config = $form->getConfig();

        $config['primaryLocale'] = $submissionLocale;
        $config['visibleLocales'] = [$submissionLocale];
        $config['supportedFormLocales'] = collect($locales)
            ->sortBy([fn (array $a, array $b) => $b['key'] === $submissionLocale ? 1 : -1])
            ->values()
            ->toArray();

        return $config;
    }

    /**
     * Get a label for a recommendation decision type
     */
    protected function getRecommendationLabel(int $decision): string
    {
        $decisionType = Repo::decision()->getDecisionType($decision);
        if (!$decisionType || !method_exists($decisionType, 'getRecommendationLabel')) {
            throw new Exception('Could not find label for unknown recommendation type.');
        }
        return $decisionType->getRecommendationLabel();
    }

    /**
     * Get the contributor list panel
     */
    protected function getContributorsListPanel(Submission $submission, Context $context, array $locales, array $authorItems, bool $canEditPublication): ContributorsListPanel
    {
        return new ContributorsListPanel(
            'contributors',
            __('publication.contributors'),
            $submission,
            $context,
            $locales,
            $authorItems,
            $canEditPublication
        );
    }

    /**
     * Get the contributor list panel
     */
    protected function getJatsPanel(Submission $submission, Context $context, bool $canEditPublication, Publication $publication): PublicationSectionJats
    {
        return new PublicationSectionJats(
            'jats',
            __('publication.jats'),
            $submission,
            $context,
            $canEditPublication,
            $publication
        );
    }

    //
    // Abstract protected methods.
    //
    /**
    * Return the editor assignment notification type based on stage id.
    *
    * @param int $stageId
    *
    * @return int
    */
    abstract protected function getEditorAssignmentNotificationTypeByStageId($stageId);

    /**
     * Get the URL for the galley/publication formats grid with a placeholder for
     * the publicationId value
     *
     * @param Request $request
     * @param Submission $submission
     *
     * @return string
     */
    abstract protected function _getRepresentationsGridUrl($request, $submission);

    /**
     * A helper method to get a list of editor decisions to
     * show on the right panel of each stage
     *
     * @return string[]
     */
    abstract protected function getStageDecisionTypes(int $stageId): array;

    /**
     * A helper method to get a list of editor recommendations to
     * show on the right panel of the review stage
     *
     */
    abstract protected function getStageRecommendationTypes(int $stageId): array;

    /**
     * Get the editor decision types that should be shown
     * as primary buttons (eg - Accept)
     *
     * @return string[]
     */
    abstract protected function getPrimaryDecisionTypes(): array;

    /**
     * Get the editor decision types that should be shown
     * as warnable buttons (eg - Decline)
     *
     * @return string[]
     */
    abstract protected function getWarnableDecisionTypes(): array;

    /**
     * Get the form for entering the title/abstract details
     */
    abstract protected function getTitleAbstractForm(string $latestPublicationApiUrl, array $locales, Publication $latestPublication, Context $context): TitleAbstractForm;
}
