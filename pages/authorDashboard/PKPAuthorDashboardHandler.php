<?php

/**
 * @file pages/authorDashboard/PKPAuthorDashboardHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPAuthorDashboardHandler
 *
 * @ingroup pages_authorDashboard
 *
 * @brief Handle requests for the author dashboard.
 */

namespace PKP\pages\authorDashboard;

use APP\core\Application;
use APP\core\Request;
use APP\decision\Decision;
use APP\facades\Repo;
use APP\handler\Handler;
use APP\publication\Publication;
use APP\submission\Submission;
use APP\template\TemplateManager;
use Illuminate\Support\Enumerable;
use PKP\components\forms\publication\PKPCitationsForm;
use PKP\components\forms\publication\PKPMetadataForm;
use PKP\components\forms\publication\TitleAbstractForm;
use PKP\components\listPanels\ContributorsListPanel;
use PKP\config\Config;
use PKP\context\Context;
use PKP\core\JSONMessage;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\log\SubmissionEmailLogEventType;
use PKP\security\authorization\AuthorDashboardAccessPolicy;
use PKP\security\Role;
use PKP\submission\GenreDAO;
use PKP\submission\PKPSubmission;
use PKP\submission\reviewRound\ReviewRound;
use PKP\submission\reviewRound\ReviewRoundDAO;
use PKP\submissionFile\SubmissionFile;
use PKP\workflow\WorkflowStageDAO;

abstract class PKPAuthorDashboardHandler extends Handler
{
    /** @copydoc PKPHandler::_isBackendPage */
    public $_isBackendPage = true;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [Role::ROLE_ID_AUTHOR],
            [
                'submission',
                'readSubmissionEmail',
            ]
        );
    }


    //
    // Implement template methods from PKPHandler
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
     * Displays the author dashboard.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function submission($args, $request)
    {
        if (Config::getVar('features', 'enable_new_submission_listing')) {
            $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
            $router = $request->getRouter();
            return $request->redirectUrl($router->url($request, null, 'dashboard', 'mySubmissions', null, ['workflowSubmissionId' => $submission->getId()]));
        }


        // Pass the authorized submission on to the template.
        $this->setupTemplate($request);

        $templateMgr = TemplateManager::getManager($request);
        return $templateMgr->display('authorDashboard/authorDashboard.tpl');
    }


    /**
     * Fetches information about a specific email and returns it.
     *
     * @param array $args
     * @param Request $request
     *
     * @return JSONMessage JSON object
     */
    public function readSubmissionEmail($args, $request)
    {
        $user = $request->getUser();
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $submissionEmailId = $request->getUserVar('submissionEmailId');

        $submissionEmailFactory = Repo::emailLogEntry()->getByEventType(
            $submission->getId(),
            SubmissionEmailLogEventType::EDITOR_NOTIFY_AUTHOR,
            Application::ASSOC_TYPE_SUBMISSION,
            $user->getId()
        );
        foreach ($submissionEmailFactory as $email) {
            // validate the email id for this user.
            if ($email->id == $submissionEmailId) {
                $templateMgr = TemplateManager::getManager($request);
                $templateMgr->assign('submissionEmail', $email);
                return $templateMgr->fetchJson('authorDashboard/submissionEmail.tpl');
            }
        }
    }

    /**
     * Get the SubmissionFile::SUBMISSION_FILE_... file stage based on the current
     * WORKFLOW_STAGE_... workflow stage.
     *
     * @param int $currentStage WORKFLOW_STAGE_...
     *
     * @return ?int SubmissionFile::SUBMISSION_FILE_...
     */
    protected function _fileStageFromWorkflowStage($currentStage)
    {
        switch ($currentStage) {
            case WORKFLOW_STAGE_ID_SUBMISSION:
                return SubmissionFile::SUBMISSION_FILE_SUBMISSION;
            case WORKFLOW_STAGE_ID_EXTERNAL_REVIEW:
                return SubmissionFile::SUBMISSION_FILE_REVIEW_REVISION;
            case WORKFLOW_STAGE_ID_EDITING:
                return SubmissionFile::SUBMISSION_FILE_FINAL;
            default:
                return null;
        }
    }


    //
    // Protected helper methods
    //
    /**
     * Setup common template variables.
     */
    public function setupTemplate($request)
    {
        parent::setupTemplate($request);

        $templateMgr = TemplateManager::getManager($request);
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION); /** @var Submission $submission */
        $user = $request->getUser();
        $submissionContext = $request->getContext();
        if ($submission->getData('contextId') !== $submissionContext->getId()) {
            $submissionContext = app()->get('context')->get($submission->getData('contextId'));
        }

        $contextUserGroups = Repo::userGroup()->getByRoleIds([Role::ROLE_ID_AUTHOR], $submission->getData('contextId'));
        $genreDao = DAORegistry::getDAO('GenreDAO'); /** @var GenreDAO $genreDao */
        $contextGenres = $genreDao->getEnabledByContextId($submission->getData('contextId'))->toArray();
        $workflowStages = WorkflowStageDAO::getWorkflowStageKeysAndPaths();

        $stageNotifications = [];
        foreach (array_keys($workflowStages) as $stageId) {
            $stageNotifications[$stageId] = false;
        }

        // Add an upload revisions button when in the review stage
        // and the last decision is to request revisions
        $uploadFileUrl = '';
        if (in_array($submission->getData('stageId'), [WORKFLOW_STAGE_ID_INTERNAL_REVIEW, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW])) {
            $fileStage = $this->_fileStageFromWorkflowStage($submission->getData('stageId'));
            $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
            $lastReviewRound = $reviewRoundDao->getLastReviewRoundBySubmissionId($submission->getId(), $submission->getData('stageId'));
            if ($fileStage && $lastReviewRound instanceof ReviewRound) {
                $editorDecisions = Repo::decision()->getCollector()
                    ->filterBySubmissionIds([$submission->getId()])
                    ->filterByStageIds([$submission->getData('stageId')])
                    ->filterByReviewRoundIds([$lastReviewRound->getId()])
                    ->getMany();

                if (!$editorDecisions->isEmpty()) {
                    $lastDecision = $editorDecisions->last();
                    $revisionDecisions = [
                        Decision::PENDING_REVISIONS,
                        Decision::RESUBMIT
                    ];
                    if (in_array($lastDecision->getData('decision'), $revisionDecisions)) {
                        $actionArgs['submissionId'] = $submission->getId();
                        $actionArgs['stageId'] = $submission->getData('stageId');
                        $actionArgs['uploaderRoles'] = Role::ROLE_ID_AUTHOR;
                        $actionArgs['fileStage'] = $fileStage;
                        $actionArgs['reviewRoundId'] = $lastReviewRound->getId();
                        $uploadFileUrl = $request->getDispatcher()->url(
                            $request,
                            PKPApplication::ROUTE_COMPONENT,
                            null,
                            'wizard.fileUpload.FileUploadWizardHandler',
                            'startWizard',
                            null,
                            $actionArgs
                        );
                    }
                }
            }
        }

        $latestPublication = $submission->getLatestPublication();

        $submissionLocale = $submission->getData('locale');
        $locales = collect($submissionContext->getSupportedSubmissionMetadataLocaleNames() + $submission->getPublicationLanguageNames())
            ->map(fn (string $name, string $locale) => ['key' => $locale, 'label' => $name])
            ->sortBy('key')
            ->values()
            ->toArray();

        $submissionApiUrl = $request->getDispatcher()->url($request, PKPApplication::ROUTE_API, $submissionContext->getData('urlPath'), 'submissions/' . $submission->getId());
        $latestPublicationApiUrl = $request->getDispatcher()->url($request, PKPApplication::ROUTE_API, $submissionContext->getData('urlPath'), 'submissions/' . $submission->getId() . '/publications/' . $latestPublication->getId());

        $submissionLibraryUrl = $request->getDispatcher()->url(
            $request,
            PKPApplication::ROUTE_COMPONENT,
            null,
            'modals.documentLibrary.DocumentLibraryHandler',
            'documentLibrary',
            null,
            ['submissionId' => $submission->getId()]
        );

        $titleAbstractForm = $this->getTitleAbstractForm($latestPublicationApiUrl, $locales, $latestPublication, $submissionContext);
        $citationsForm = new PKPCitationsForm($latestPublicationApiUrl, $latestPublication);

        $templateMgr->setConstants([
            'STATUS_QUEUED' => PKPSubmission::STATUS_QUEUED,
            'STATUS_PUBLISHED' => PKPSubmission::STATUS_PUBLISHED,
            'STATUS_DECLINED' => PKPSubmission::STATUS_DECLINED,
            'STATUS_SCHEDULED' => PKPSubmission::STATUS_SCHEDULED,
            'FORM_TITLE_ABSTRACT' => $titleAbstractForm::FORM_TITLE_ABSTRACT,
            'FORM_CITATIONS' => $citationsForm::FORM_CITATIONS,
        ]);

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
        $mapper = Repo::publication()->getSchemaMap($submission, $contextUserGroups, $contextGenres);
        $workingPublicationProps = $mapper->map($submission->getLatestPublication());
        $currentPublicationProps = $submission->getLatestPublication()->getId() === $submission->getCurrentPublication()->getId()
            ? $workingPublicationProps
            : $mapper->map($submission->getCurrentPublication());

        // Check if current author can edit metadata
        $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
        $canEditPublication = true;
        if (!in_array(Role::ROLE_ID_SITE_ADMIN, $userRoles) && !Repo::submission()->canEditPublication($submission->getId(), $user->getId())) {
            $canEditPublication = false;
        }

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

        // Check if current author can access ArticleGalleyGrid within production stage
        $canAccessProductionStage = true;
        $userAllowedStages = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES);
        if (!array_key_exists(WORKFLOW_STAGE_ID_PRODUCTION, $userAllowedStages)) {
            $canAccessProductionStage = false;
        }

        $state = [
            'canEditPublication' => $canEditPublication,
            'currentSubmissionLanguageLabel' => Locale::getSubmissionLocaleDisplayNames([$submissionLocale])[$submissionLocale],
            'components' => [
                $titleAbstractForm::FORM_TITLE_ABSTRACT => $this->getLocalizedForm($titleAbstractForm, $submissionLocale, $locales),
                $citationsForm::FORM_CITATIONS => $this->getLocalizedForm($citationsForm, $submissionLocale, $locales),
                $contributorsListPanel->id => $contributorsListPanel->getConfig(),
            ],
            'currentPublication' => $currentPublicationProps,
            'publicationFormIds' => [
                $titleAbstractForm::FORM_TITLE_ABSTRACT,
                $citationsForm::FORM_CITATIONS,
            ],
            'representationsGridUrl' => $canAccessProductionStage ? $this->_getRepresentationsGridUrl($request, $submission) : '',
            'submission' => $submissionProps,
            'publicationList' => $publicationList,
            'workingPublication' => $workingPublicationProps,
            'submissionApiUrl' => $submissionApiUrl,
            'submissionLibraryLabel' => __('grid.libraryFiles.submission.title'),
            'submissionLibraryUrl' => $submissionLibraryUrl,
            'supportsReferences' => !!$submissionContext->getData('citations'),
            'statusLabel' => __('semicolon', ['label' => __('common.status')]),
            'uploadFileModalLabel' => __('editor.submissionReview.uploadFile'),
            'uploadFileUrl' => $uploadFileUrl,
            'versionLabel' => __('semicolon', ['label' => __('admin.version')]),
        ];

        // Add the metadata form if one or more metadata fields are enabled
        $vocabSuggestionUrlBase = $request->getDispatcher()->url($request, PKPApplication::ROUTE_API, $submissionContext->getData('urlPath'), 'vocabs', null, null, ['vocab' => '__vocab__', 'submissionId' => $submission->getId()]);
        $metadataForm = new PKPMetadataForm($latestPublicationApiUrl, $locales, $latestPublication, $submissionContext, $vocabSuggestionUrlBase, true);
        $metadataEnabled = count($metadataForm->fields);

        if ($metadataEnabled) {
            $templateMgr->setConstants([
                'FORM_METADATA' => $metadataForm::FORM_METADATA,
            ]);
            $state['components'][$metadataForm::FORM_METADATA] = $this->getLocalizedForm($metadataForm, $submissionLocale, $locales);
            $state['publicationFormIds'][] = $metadataForm::FORM_METADATA;
        }

        $templateMgr->setState($state);

        $templateMgr->assign([
            'metadataEnabled' => $metadataEnabled,
            'pageComponent' => 'WorkflowPage',
            'pageTitle' => implode(__('common.titleSeparator'), array_filter([
                $latestPublication->getShortAuthorString(),
                $submission->getCurrentPublication()->getLocalizedTitle(null, 'html')
            ])),
            'submission' => $submission,
            'workflowStages' => $workflowStages,
            'canAccessProductionStage' => $canAccessProductionStage,
        ]);
    }

    /**
     * Get the contributor's list panel
     */
    protected function getContributorsListPanel(Submission $submission, Context $context, array $locales, array $authorItems, ?bool $canEditPublication): ContributorsListPanel
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
     * Get the form configuration data with the correct
     * locale settings based on the submission's locale
     *
     * Uses the submission locale as the primary and
     * visible locale, and puts that locale first in the
     * list of supported locales.
     *
     * Call this instead of $form->getConfig() to display
     * a form with the correct submission's publication locales
     */
    protected function getLocalizedForm(\PKP\components\forms\FormComponent $form, string $submissionLocale, array $locales): array
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
     * Get the form for entering the title/abstract details
     */
    abstract protected function getTitleAbstractForm(string $latestPublicationApiUrl, array $locales, Publication $latestPublication, Context $context): TitleAbstractForm;
}
