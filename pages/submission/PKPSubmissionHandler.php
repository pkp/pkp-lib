<?php

/**
 * @file pages/submission/PKPSubmissionHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionHandler
 * @ingroup pages_submission
 *
 * @brief Handles page requests to the submission wizard
 */

namespace PKP\pages\submission;

use APP\components\forms\submission\ReconfigureSubmission;
use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\handler\Handler;
use APP\publication\Publication;
use APP\section\Section;
use APP\submission\Submission;
use APP\template\TemplateManager;
use Illuminate\Support\LazyCollection;
use PKP\components\forms\FormComponent;
use PKP\components\forms\publication\PKPCitationsForm;
use PKP\components\forms\publication\TitleAbstractForm;
use PKP\components\forms\submission\CommentsForTheEditors;
use PKP\components\forms\submission\ConfirmSubmission;
use PKP\components\forms\submission\ForTheEditors;
use PKP\components\forms\submission\PKPSubmissionFileForm;
use PKP\components\listPanels\ContributorsListPanel;
use PKP\context\Context;
use PKP\db\DAORegistry;
use PKP\file\FileManager;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\authorization\UserRequiredPolicy;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignmentDAO;
use PKP\submissionFile\SubmissionFile;
use PKP\user\User;

abstract class PKPSubmissionHandler extends Handler
{
    public const SECTION_TYPE_CONFIRM = 'confirm';
    public const SECTION_TYPE_CONTRIBUTORS = 'contributors';
    public const SECTION_TYPE_FILES = 'files';
    public const SECTION_TYPE_FORM = 'form';
    public const SECTION_TYPE_TEMPLATE = 'template';
    public const SECTION_TYPE_REVIEW = 'review';

    public $_isBackendPage = true;

    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [
                Role::ROLE_ID_AUTHOR,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SITE_ADMIN,
            ],
            [
                'index',
                'saved',
                'wizard', // @deprecated 3.4
            ]
        );
    }

    /**
     * @param Request $request
     */
    public function authorize($request, &$args, $roleAssignments): bool
    {
        $submissionId = (int) $request->getUserVar('id');

        // Creating a new submission
        if ($submissionId === 0) {
            $this->addPolicy(new UserRequiredPolicy($request));
            $this->markRoleAssignmentsChecked();
        } else {
            $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments, 'id'));
        }

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Route the request to the correct page based
     * on whether they are starting a new submission,
     * working on a submission in progress, or viewing
     * a submission that has been submitted.
     *
     * @param array $args
     * @param Request $request
     */
    public function index($args, $request): void
    {
        $this->setupTemplate($request);

        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        if (!$submission) {
            $this->start($args, $request);
            return;
        }

        if ($submission->getData('submissionProgress')) {
            $this->showWizard($args, $request, $submission);
            return;
        }

        $this->complete($args, $request, $submission);
    }

    /**
     * Display the screen to start a new submission
     */
    protected function start(array $args, Request $request): void
    {
        $templateMgr = TemplateManager::getManager($request);

        $templateMgr->assign([
            'pageComponent' => 'StartSubmissionPage',
            'pageTitle' => __('submission.wizard.title'),
            'pageWidth' => TemplateManager::PAGE_WIDTH_NARROW,
        ]);

        $templateMgr->display('submission/start.tpl');
    }

    /**
     * Backwards compatibility for old links to the submission wizard
     *
     * @deprecated 3.4
     */
    public function wizard(array $args, Request $request): void
    {
        $submissionId = $request->getUserVar('submissionId')
            ? (int) $request->getUserVar('submissionId')
            : null;

        $request->redirectUrl(
            Repo::submission()->getUrlSubmissionWizard($request->getContext(), $submissionId)
        );
    }

    /**
     * Display the submission wizard
     */
    protected function showWizard(array $args, Request $request, Submission $submission): void
    {
        $context = $request->getContext();

        /** @var Publication $publication */
        $publication = $submission->getCurrentPublication();

        /** @var int $sectionId */
        $sectionId = $publication->getData(Application::getSectionIdPropName());

        if ($sectionId) {
            $section = Repo::section()->get($sectionId, $context->getId());
        }

        if (isset($section) &&
            (
                $section->getIsInactive() ||
                ($section->getEditorRestricted() && !$this->isEditor())
            )
        ) {
            $this->showErrorPage(
                'submission.wizard.sectionClosed',
                __('submission.wizard.sectionClosed.message', [
                    'contextName' => $context->getLocalizedData('name'),
                    'section' => $section->getLocalizedTitle(),
                    'email' => $context->getData('contactEmail'),
                    'name' => $context->getData('contactName'),
                ])
            );
            return;
        }


        $supportedSubmissionLocales = $context->getSupportedSubmissionLocaleNames();
        $formLocales = array_map(fn (string $locale, string $name) => ['key' => $locale, 'label' => $name], array_keys($supportedSubmissionLocales), $supportedSubmissionLocales);

        // Order locales with submission locale first
        $orderedLocales = $supportedSubmissionLocales;
        uksort($orderedLocales, fn ($a, $b) => $a === $submission->getData('locale') ? $a : $b);

        $userGroups = Repo::userGroup()
            ->getCollector()
            ->filterByContextIds([$context->getId()])
            ->getMany();

        /** @var GenreDAO $genreDao */
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $genres = $genreDao->getByContextId($context->getId())->toArray();

        $sections = $this->getSubmitSections($context);
        $categories = Repo::category()->getCollector()
            ->filterByContextIds([$context->getId()])
            ->getMany();

        $submissionFilesListPanel = $this->getSubmissionFilesListPanel($request, $submission, $genres);
        $contributorsListPanel = $this->getContributorsListPanel($request, $submission, $publication, $formLocales);
        $reconfigureSubmissionForm = $this->getReconfigureForm($context, $submission, $publication, $sections, $categories);

        $steps = $this->getSteps($request, $submission, $publication, $formLocales, $sections, $categories);

        $templateMgr = TemplateManager::getManager($request);

        $templateMgr->setState([
            'categories' => Repo::category()->getBreadcrumbs($categories),
            'components' => [
                $submissionFilesListPanel['id'] => $submissionFilesListPanel,
                $contributorsListPanel->id => $contributorsListPanel->getConfig(),
                $reconfigureSubmissionForm->id => $reconfigureSubmissionForm->getConfig(),
            ],
            'i18nConfirmSubmit' => $this->getConfirmSubmitMessage($submission, $context),
            'i18nDiscardChanges' => __('common.discardChanges'),
            'i18nDisconnected' => __('common.disconnected'),
            'i18nLastAutosaved' => __('common.lastSaved'),
            'i18nPageTitle' => __('submission.wizard.titleWithStep'),
            'i18nSubmit' => __('form.submit'),
            'i18nTitleSeparator' => __('common.titleSeparator'),
            'i18nUnableToSave' => __('submission.wizard.unableToSave'),
            'i18nUnsavedChanges' => __('common.unsavedChanges'),
            'i18nUnsavedChangesMessage' => __('common.unsavedChangesMessage'),
            'publication' => Repo::publication()->getSchemaMap($submission, $userGroups, $genres)->map($publication),
            'publicationApiUrl' => $this->getPublicationApiUrl($request, $submission->getId(), $publication->getId()),
            'reconfigurePublicationProps' => $this->getReconfigurePublicationProps(),
            'reconfigureSubmissionProps' => $this->getReconfigureSubmissionProps(),
            'submission' => Repo::submission()->getSchemaMap()->map($submission, $userGroups, $genres),
            'submissionApiUrl' => Repo::submission()->getUrlApi($request->getContext(), $submission->getId()),
            'submissionSavedUrl' => $this->getSubmissionSavedUrl($request, $submission->getId()),
            'submissionWizardUrl' => Repo::submission()->getUrlSubmissionWizard($context, $submission->getId()),
            'submitApiUrl' => $this->getSubmitApiUrl($request, $submission->getId()),
            'steps' => $steps,
        ]);

        $templateMgr->assign([
            'isCategoriesEnabled' => $context->getData('submitWithCategories') && $categories->count(),
            'locales' => $orderedLocales,
            'pageComponent' => 'SubmissionWizardPage',
            'pageTitle' => __('submission.wizard.title'),
            'submission' => $submission,
            'submittingTo' => $this->getSubmittingTo($context, $submission, $sections, $categories),
            'reviewSteps' => $this->getReviewStepsForSmarty($steps),
        ]);

        $templateMgr->display('submission/wizard.tpl');
    }

    /**
     * Display the submission completed screen
     */
    protected function complete(array $args, Request $request, Submission $submission): void
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'pageTitle' => __('submission.submit.submissionComplete'),
            'pageWidth' => TemplateManager::PAGE_WIDTH_NARROW,
            'submission' => $submission,
            'workflowUrl' => $this->getWorkflowUrl($submission, $request->getUser()),
        ]);
        $templateMgr->display('submission/complete.tpl');
    }

    /**
     * Display the saved for later screen
     */
    public function saved(array $args, Request $request): void
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        if (!$submission) {
            $request->getDispatcher()->handle404();
        }

        $this->setupTemplate($request);

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'email' => $request->getUser()->getEmail(),
            'pageTitle' => __('submission.wizard.saved'),
            'pageWidth' => TemplateManager::PAGE_WIDTH_NARROW,
            'submission' => $submission,
            'submissionWizardUrl' => Repo::submission()->getUrlSubmissionWizard($request->getContext(), $submission->getId()),
        ]);
        $templateMgr->display('submission/saved.tpl');
    }

    /**
     * Get all steps of the submission wizard
     */
    protected function getSteps(Request $request, Submission $submission, Publication $publication, array $locales, array $sections, LazyCollection $categories): array
    {
        $publicationApiUrl = $this->getPublicationApiUrl($request, $submission->getId(), $publication->getId());
        $controlledVocabUrl = $this->getControlledVocabBaseUrl($request);

        $steps = [];
        $steps[] = $this->getDetailsStep($request, $submission, $publication, $locales, $publicationApiUrl, $sections, $controlledVocabUrl);
        $steps[] = $this->getFilesStep($request, $submission, $publication, $locales, $publicationApiUrl);
        $steps[] = $this->getContributorsStep($request, $submission, $publication, $locales, $publicationApiUrl);
        $steps[] = $this->getEditorsStep($request, $submission, $publication, $locales, $publicationApiUrl, $categories);
        $steps[] = $this->getConfirmStep($request, $submission, $publication, $locales, $publicationApiUrl);

        return $steps;
    }

    /**
     * Get the url to the API endpoint to submit this submission
     */
    protected function getSubmitApiUrl(Request $request, int $submissionId): string
    {
        return $request
            ->getDispatcher()
            ->url(
                $request,
                Application::ROUTE_API,
                $request->getContext()->getPath(),
                'submissions/' . $submissionId . '/submit'
            );
    }

    /**
     * Get the url to the publication's API endpoint
     */
    protected function getPublicationApiUrl(Request $request, int $submissionId, int $publicationId): string
    {
        return $request
            ->getDispatcher()
            ->url(
                $request,
                Application::ROUTE_API,
                $request->getContext()->getPath(),
                'submissions/' . $submissionId . '/publications/' . $publicationId
            );
    }

    /**
     * Get the URL to the page that shows the submission
     * has been saved
     */
    protected function getSubmissionSavedUrl(Request $request, int $submissionId): string
    {
        return $request
            ->getDispatcher()
            ->url(
                $request,
                Application::ROUTE_PAGE,
                $request->getContext()->getPath(),
                'submission',
                'saved',
                null,
                [
                    'id' => $submissionId,
                ]
            );
    }

    /**
     * Get the url to the submission's files API endpoint
     */
    protected function getSubmissionFilesApiUrl(Request $request, int $submissionId): string
    {
        return $request
            ->getDispatcher()
            ->url(
                $request,
                Application::ROUTE_API,
                $request->getContext()->getPath(),
                'submissions/' . $submissionId . '/files'
            );
    }

    /**
     * Get the base url to the controlled vocab suggestions API endpoint
     *
     * The entry `__vocab__` will be replaced with the user's search phrase.
     */
    protected function getControlledVocabBaseUrl(Request $request): string
    {
        return $request->getDispatcher()->url(
            $request,
            Application::ROUTE_API,
            $request->getContext()->getData('urlPath'),
            'vocabs',
            null,
            null,
            ['vocab' => '__vocab__']
        );
    }

    /**
     * Get the state needed for the SubmissionFilesListPanel component
     */
    protected function getSubmissionFilesListPanel(Request $request, Submission $submission, array $genres): array
    {
        $submissionFiles = Repo::submissionFile()
            ->getCollector()
            ->filterBySubmissionIds([$submission->getId()])
            ->filterByFileStages([SubmissionFile::SUBMISSION_FILE_SUBMISSION])
            ->getMany();

        // Don't allow dependent files to be uploaded with the submission
        $genres = array_values(
            array_filter($genres, fn ($genre) => !$genre->getDependent())
        );

        $form = new PKPSubmissionFileForm(
            $this->getSubmissionFilesApiUrl($request, $submission->getId()),
            $genres
        );

        return [
            'addFileLabel' => __('common.addFile'),
            'apiUrl' => $this->getSubmissionFilesApiUrl($request, $submission->getId()),
            'cancelUploadLabel' => __('form.dropzone.dictCancelUpload'),
            'genrePromptLabel' => __('submission.submit.genre.label'),
            'emptyLabel' => __('submission.upload.instructions'),
            'emptyAddLabel' => __('common.upload.addFile'),
            'fileStage' => SubmissionFile::SUBMISSION_FILE_SUBMISSION,
            'form' => $form->getConfig(),
            'genres' => array_map(
                fn ($genre) => [
                    'id' => (int) $genre->getId(),
                    'name' => $genre->getLocalizedName(),
                    'isPrimary' => !$genre->getSupplementary() && !$genre->getDependent(),
                ],
                $genres
            ),
            'id' => 'submissionFiles',
            'items' => Repo::submissionFile()
                ->getSchemaMap()
                ->summarizeMany($submissionFiles, $genres)
                ->values(),
            'options' => [
                'maxFilesize' => Application::getIntMaxFileMBs(),
                'timeout' => ini_get('max_execution_time') ? ini_get('max_execution_time') * 1000 : 0,
                'dropzoneDictDefaultMessage' => __('form.dropzone.dictDefaultMessage'),
                'dropzoneDictFallbackMessage' => __('form.dropzone.dictFallbackMessage'),
                'dropzoneDictFallbackText' => __('form.dropzone.dictFallbackText'),
                'dropzoneDictFileTooBig' => __('form.dropzone.dictFileTooBig'),
                'dropzoneDictInvalidFileType' => __('form.dropzone.dictInvalidFileType'),
                'dropzoneDictResponseError' => __('form.dropzone.dictResponseError'),
                'dropzoneDictCancelUpload' => __('form.dropzone.dictCancelUpload'),
                'dropzoneDictUploadCanceled' => __('form.dropzone.dictUploadCanceled'),
                'dropzoneDictCancelUploadConfirmation' => __('form.dropzone.dictCancelUploadConfirmation'),
                'dropzoneDictRemoveFile' => __('form.dropzone.dictRemoveFile'),
                'dropzoneDictMaxFilesExceeded' => __('form.dropzone.dictMaxFilesExceeded'),
            ],
            'otherLabel' => __('about.other'),
            'primaryLocale' => $request->getContext()->getPrimaryLocale(),
            'removeConfirmLabel' => __('submission.submit.removeConfirm'),
            'stageId' => WORKFLOW_STAGE_ID_SUBMISSION,
            'title' => __('submission.files'),
            'uploadProgressLabel' => __('submission.upload.percentComplete'),
        ];
    }

    /**
     * Get an instance of the ContributorsListPanel component
     */
    protected function getContributorsListPanel(Request $request, Submission $submission, Publication $publication, array $locales): ContributorsListPanel
    {
        return new ContributorsListPanel(
            'contributors',
            __('publication.contributors'),
            $submission,
            $request->getContext(),
            $locales,
            [], // Populated by publication state
            true
        );
    }

    /**
     * Get the user groups that a user can submit in
     */
    protected function getSubmitUserGroups(Context $context, User $user): LazyCollection
    {
        $userGroups = Repo::userGroup()
            ->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByUserIds([$user->getId()])
            ->filterByRoleIds([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_AUTHOR])
            ->getMany();

        // Users without a submitting role can submit as an
        // author role that allows self registration
        if (!$userGroups->count()) {
            $defaultUserGroup = Repo::userGroup()->getFirstSubmitAsAuthorUserGroup($context->getId());
            return LazyCollection::make(function () use ($defaultUserGroup) {
                if ($defaultUserGroup) {
                    yield $defaultUserGroup->getId() => $defaultUserGroup;
                }
            });
        }

        return $userGroups;
    }

    /**
     * Get the state for the files step
     */
    protected function getFilesStep(Request $request, Submission $submission, Publication $publication, array $locales, string $publicationApiUrl): array
    {
        return [
            'id' => 'files',
            'name' => __('submission.upload.uploadFiles'),
            'reviewName' => __('submission.files'),
            'sections' => [
                [
                    'id' => 'files',
                    'name' => __('submission.upload.uploadFiles'),
                    'type' => self::SECTION_TYPE_FILES,
                    'description' => $request->getContext()->getLocalizedData('uploadFilesHelp'),
                ],
            ],
            'reviewTemplate' => '/submission/review-files.tpl',
        ];
    }

    /**
     * Get the state for the contributors step
     */
    protected function getContributorsStep(Request $request, Submission $submission, Publication $publication, array $locales, string $publicationApiUrl): array
    {
        return [
            'id' => 'contributors',
            'name' => __('publication.contributors'),
            'reviewName' => __('publication.contributors'),
            'sections' => [
                [
                    'id' => 'contributors',
                    'name' => __('publication.contributors'),
                    'type' => self::SECTION_TYPE_CONTRIBUTORS,
                    'description' => $request->getContext()->getLocalizedData('contributorsHelp'),
                ],
            ],
            'reviewTemplate' => '/submission/review-contributors.tpl',
        ];
    }

    /**
     * Get the state for the details step
     */
    protected function getDetailsStep(Request $request, Submission $submission, Publication $publication, array $locales, string $publicationApiUrl, array $sections, string $controlledVocabUrl): array
    {
        $titleAbstractForm = $this->getDetailsForm(
            $publicationApiUrl,
            $locales,
            $publication,
            $request->getContext(),
            $sections,
            $controlledVocabUrl
        );
        $this->removeButtonFromForm($titleAbstractForm);

        $sections = [
            [
                'id' => $titleAbstractForm->id,
                'name' => __('submission.details'),
                'type' => self::SECTION_TYPE_FORM,
                'description' => $request->getContext()->getLocalizedData('detailsHelp'),
                'form' => $this->getLocalizedForm($titleAbstractForm, $submission, $request->getContext()),
            ],
        ];

        if (in_array($request->getContext()->getData('citations'), [Context::METADATA_REQUEST, Context::METADATA_REQUIRE])) {
            $citationsForm = new PKPCitationsForm(
                $publicationApiUrl,
                $publication,
                $request->getContext()->getData('citations') === Context::METADATA_REQUIRE
            );
            $this->removeButtonFromForm($citationsForm);
            $sections[] = [
                'id' => $citationsForm->id,
                'name' => '',
                'type' => self::SECTION_TYPE_FORM,
                'description' => '',
                'form' => $citationsForm->getConfig(),
            ];
        }

        return [
            'id' => 'details',
            'name' => __('common.details'),
            'reviewName' => __('common.details'),
            'sections' => $sections,
            'reviewTemplate' => '/submission/review-details.tpl',
        ];
    }

    /**
     * Get the state for the For the Editors step
     *
     * If no metadata is enabled during submission, the metadata
     * form is not shown.
     */
    protected function getEditorsStep(Request $request, Submission $submission, Publication $publication, array $locales, string $publicationApiUrl, LazyCollection $categories): array
    {
        $metadataForm = $this->getForTheEditorsForm(
            $publicationApiUrl,
            $locales,
            $publication,
            $submission,
            $request->getContext(),
            $request->getDispatcher()->url(
                $request,
                Application::ROUTE_API,
                $request->getContext()->getData('urlPath'),
                'vocabs',
                null,
                null,
                ['vocab' => '__vocab__']
            ),
            $categories
        );
        $this->removeButtonFromForm($metadataForm);

        $commentsForm = new CommentsForTheEditors(
            Repo::submission()->getUrlApi($request->getContext(), $submission->getId()),
            $submission
        );
        $this->removeButtonFromForm($commentsForm);

        $hasMetadataForm = count($metadataForm->fields);

        $metadataFormData = $this->getLocalizedForm($metadataForm, $submission, $request->getContext());
        $commentsFormData = $this->getLocalizedForm($commentsForm, $submission, $request->getContext());

        $sections = [
            [
                'id' => $hasMetadataForm ? $metadataForm->id : $commentsForm->id,
                'name' => __('submission.forTheEditors'),
                'type' => self::SECTION_TYPE_FORM,
                'description' => $request->getContext()->getLocalizedData('forTheEditorsHelp'),
                'form' => $hasMetadataForm ? $metadataFormData : $commentsFormData,
            ],
        ];

        if ($hasMetadataForm) {
            $sections[] = [
                'id' => $commentsForm->id,
                'name' => '',
                'type' => self::SECTION_TYPE_FORM,
                'description' => '',
                'form' => $commentsFormData,
            ];
        }

        return [
            'id' => 'editors',
            'name' => __('submission.forTheEditors'),
            'reviewName' => __('submission.forTheEditors'),
            'sections' => $sections,
            'reviewTemplate' => '/submission/review-editors.tpl',
        ];
    }

    /**
     * Get the state for the Confirm step
     */
    protected function getConfirmStep(Request $request, Submission $submission, Publication $publication, array $locales, string $publicationApiUrl): array
    {
        $sections = [
            [
                'id' => 'review',
                'name' => __('submission.reviewAndSubmit'),
                'type' => self::SECTION_TYPE_REVIEW,
                'description' => $request->getContext()->getLocalizedData('reviewHelp'),
            ]
        ];

        $confirmForm = new ConfirmSubmission(
            FormComponent::ACTION_EMIT,
            $request->getContext()
        );

        if (!empty($confirmForm->fields)) {
            $this->removeButtonFromForm($confirmForm);
            $sections[] = [
                'id' => $confirmForm->id,
                'name' => __('author.submit.confirmation'),
                'type' => self::SECTION_TYPE_CONFIRM,
                'description' => '<p>' . __('submission.wizard.confirm') . '</p>',
                'form' => $confirmForm->getConfig(),
            ];
        }

        return [
            'id' => 'review',
            'name' => __('submission.review'),
            'sections' => $sections,
        ];
    }

    /**
     * A helper function to remove the save button forms in the wizard
     *
     * This creates a default group/page for each form and assigns each #
     * field and group to that page.
     */
    protected function removeButtonFromForm(FormComponent $form): void
    {
        $form->addPage([
            'id' => 'default',
        ])
            ->addGroup([
                'id' => 'default',
                'pageId' => 'default'
            ]);

        foreach ($form->fields as $field) {
            $field->groupId = 'default';
        }
    }

    /**
     * Get details about the steps that are required by the smarty template
     */
    protected function getReviewStepsForSmarty(array $steps): array
    {
        $reviewSteps = [];
        foreach ($steps as $step) {
            if ($step['id'] === 'review') {
                continue;
            }
            $reviewSteps[] = [
                'id' => $step['id'],
                'reviewTemplate' => $step['reviewTemplate'],
                'reviewName' => $step['reviewName'],
            ];
        }
        return $reviewSteps;
    }

    /**
     * Show an error page
     */
    protected function showErrorPage(string $titleLocaleKey, string $message): void
    {
        $this->_isBackendPage = false;
        $templateMgr = TemplateManager::getManager(Application::get()->getRequest());
        $templateMgr->assign([
            'pageTitle' => $titleLocaleKey,
            'messageTranslated' => $message,
        ]);
        $templateMgr->display('frontend/pages/message.tpl');
    }

    /**
     * Get the appropriate workflow URL for the current user
     *
     * Returns the author dashboard if the user has an author assignment
     * and the editorial workflow if not.
     */
    protected function getWorkflowUrl(Submission $submission, User $user): string
    {
        /** @var StageAssignmentDAO $stageAssignmentDao */
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
        $results = $stageAssignmentDao->getBySubmissionAndRoleIds($submission->getId(), [Role::ROLE_ID_AUTHOR], WORKFLOW_STAGE_ID_SUBMISSION, $user->getId());

        $request = Application::get()->getRequest();

        if (count($results->toArray())) {
            return Repo::submission()->getUrlAuthorWorkflow($request->getContext(), $submission->getId());
        }

        return Repo::submission()->getUrlEditorialWorkflow($request->getContext(), $submission->getId());
    }

    /**
     * Get the sections that this user can submit to
     */
    protected function getSubmitSections(Context $context): array
    {
        $allSections = Repo::section()
            ->getCollector()
            ->filterByContextIds([$context->getId()])
            ->excludeInactive()
            ->getMany();

        $submitSections = [];
        /** @var Section $section */
        foreach ($allSections as $section) {
            if ($section->getEditorRestricted() && !$this->isEditor()) {
                continue;
            }
            $submitSections[] = $section;
        }

        return $submitSections;
    }

    /**
     * Get the "are you sure?" message shown to the user
     * before they complete their submission
     */
    protected function getConfirmSubmitMessage(Submission $submission, Context $context): string
    {
        return __('submission.wizard.confirmSubmit', ['context' => $context->getLocalizedName()]);
    }

    /**
     * Is the current user an editor
     */
    protected function isEditor(): bool
    {
        return !empty(
            array_intersect(
                Section::getEditorRestrictedRoles(),
                $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES)
            )
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
     * a form with the correct submission locales
     */
    protected function getLocalizedForm(FormComponent $form, Submission $submission, Context $context): array
    {
        $config = $form->getConfig();

        $config['primaryLocale'] = $submission->getLocale();
        $config['visibleLocales'] = [$submission->getLocale()];

        $supportedFormLocales = [];
        foreach ($context->getSupportedSubmissionLocaleNames() as $localeKey => $name) {
            $supportedFormLocales[] = [
                'key' => $localeKey,
                'label' => $name,
            ];
        }

        usort($supportedFormLocales, fn ($a, $b) => $a['key'] === $submission->getLocale() ? -1 : 1);

        $config['supportedFormLocales'] = $supportedFormLocales;

        return $config;
    }

    /**
     * Get a string describing the sections, languages, etc
     * that the submission is in
     */
    abstract protected function getSubmittingTo(Context $context, Submission $submission, array $sections, LazyCollection $categories): string;

    /**
     * Get the form to reconfigure a submission that has already been started
     */
    abstract protected function getReconfigureForm(Context $context, Submission $submission, Publication $publication, array $sections, LazyCollection $categories): ReconfigureSubmission;

    /**
     * Get the form for entering the title/abstract details
     */
    abstract protected function getDetailsForm(string $publicationApiUrl, array $locales, Publication $publication, Context $context, array $sections, string $suggestionUrlBase): TitleAbstractForm;

    /**
     * Get the form for entering information for the editors
     */
    abstract protected function getForTheEditorsForm(string $publicationApiUrl, array $locales, Publication $publication, Submission $submission, Context $context, string $suggestionUrlBase, LazyCollection $categories): ForTheEditors;

    /**
     * Get the properties that should be saved to the Submission
     * from the ReconfigureSubmission form
     */
    abstract protected function getReconfigurePublicationProps(): array;

    /**
     * Get the properties that should be saved to the Submission
     * from the ReconfigureSubmission form
     */
    abstract protected function getReconfigureSubmissionProps(): array;
}
