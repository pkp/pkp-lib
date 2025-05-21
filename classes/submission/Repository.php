<?php

/**
 * @file classes/submission/Repository.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A repository to find and manage submissions.
 */

namespace PKP\submission;

use APP\author\Author;
use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\publication\Publication;
use APP\section\Section;
use APP\submission\Collector;
use APP\submission\DAO;
use APP\submission\Submission;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Illuminate\Support\LazyCollection;
use PKP\context\Context;
use PKP\core\Core;
use PKP\db\DAORegistry;
use PKP\doi\exceptions\DoiException;
use PKP\facades\Locale;
use PKP\observers\events\SubmissionSubmitted;
use PKP\plugins\Hook;
use PKP\security\Role;
use PKP\security\RoleDAO;
use PKP\services\PKPSchemaService;
use PKP\stageAssignment\StageAssignment;
use PKP\submission\Collector as SubmissionCollector;
use PKP\submission\reviewAssignment\Collector as ReviewCollector;
use PKP\submissionFile\SubmissionFile;
use PKP\user\User;
use PKP\userGroup\UserGroup;
use PKP\validation\ValidatorFactory;

abstract class Repository
{
    public const STAGE_STATUS_SUBMISSION_UNASSIGNED = 1;

    /** @var DAO $dao */
    public $dao;

    /** @var string $schemaMap The name of the class to map this entity to its schema */
    public $schemaMap = maps\Schema::class;

    /** @var Request $request */
    protected $request;

    /** @var PKPSchemaService<Submission> $schemaService */
    protected $schemaService;

    public function __construct(DAO $dao, Request $request, PKPSchemaService $schemaService)
    {
        $this->dao = $dao;
        $this->request = $request;
        $this->schemaService = $schemaService;
    }

    /** @copydoc DAO::newDataObject() */
    public function newDataObject(array $params = []): Submission
    {
        $object = $this->dao->newDataObject();
        if (!empty($params)) {
            $object->setAllData($params);
        }
        return $object;
    }

    /** @copydoc DAO::exists() */
    public function exists(int $id, ?int $contextId = null): bool
    {
        return $this->dao->exists($id, $contextId);
    }

    /** @copydoc DAO::get() */
    public function get(int $id, ?int $contextId = null): ?Submission
    {
        return $this->dao->get($id, $contextId);
    }

    /** @copydoc DAO::getCollector() */
    public function getCollector(): Collector
    {
        return app(Collector::class);
    }

    /**
     * Get an instance of the map class for mapping
     * submissions to their schema
     */
    public function getSchemaMap(): maps\Schema
    {
        return app('maps')->withExtensions($this->schemaMap);
    }

    /**
     * Get a submission by "best" submission id -- url path if it exists,
     * falling back on the internal submission ID otherwise.
     */
    public function getByBestId(string $idOrUrlPath, ?int $contextId = null): ?Submission
    {
        return ctype_digit((string) $idOrUrlPath)
            ? $this->get((int) $idOrUrlPath, $contextId)
            : $this->getByUrlPath($idOrUrlPath, $contextId);
    }

    /**
     * Get a submission by its urlPath
     *
     * This returns a submission if any of its publications have a
     * matching urlPath.
     */
    public function getByUrlPath(string $urlPath, int $contextId): ?Submission
    {
        $submissionId = $this->dao->getIdByUrlPath($urlPath, $contextId);

        return $submissionId
            ? $this->get($submissionId)
            : null;
    }

    /**
     * Gets a submission by its current publication's DOI
     *
     *
     */
    public function getByDoi(string $doi, int $contextId): ?Submission
    {
        return $this->dao->getByDoi($doi, $contextId);
    }

    /** @copydoc DAO::getIdsBySetting() */
    public function getIdsBySetting(string $settingName, $settingValue, int $contextId): Enumerable
    {
        return $this->dao->getIdsBySetting($settingName, $settingValue, $contextId);
    }

    /**
     * Get the correct access URL for a submission's workflow based on a user's
     * role.
     *
     * The returned URL will point to the correct workflow page based on whether
     * the user should be treated as an author, reviewer or editor/assistant for
     * this submission.
     */
    public function getWorkflowUrlByUserRoles(Submission $submission, ?int $userId = null): string
    {
        $request = Application::get()->getRequest();

        if (is_null($userId)) {
            $user = $request->getUser();
        } else {
            $user = Repo::user()->get($userId);
        }

        if (is_null($user)) {
            return '';
        }

        $submissionContext = $request->getContext();

        if (!$submissionContext || $submissionContext->getId() != $submission->getData('contextId')) {
            $submissionContext = app()->get('context')->get($submission->getData('contextId'));
        }

        $dispatcher = $request->getDispatcher();

        // Check if the user is an author of this submission
        $authorUserGroupIds = UserGroup::withContextIds([$submission->getData('contextId')])
            ->withRoleIds([Role::ROLE_ID_AUTHOR])
            ->get()
            ->map(fn ($userGroup) => $userGroup->id)
            ->toArray();

        // Replaces StageAssignmentDAO::getBySubmissionAndStageId
        $stageAssignments = StageAssignment::withSubmissionIds([$submission->getId()])
            ->withUserId($user->getId())
            ->get();

        $authorDashboard = false;
        foreach ($stageAssignments as $stageAssignment) {
            if (in_array($stageAssignment->userGroupId, $authorUserGroupIds)) {
                $authorDashboard = true;
                break;
            }
        }

        // Send authors, journal managers and site admins to the submission
        // wizard for incomplete submissions
        if ($submission->getData('submissionProgress') &&
            ($authorDashboard ||
                $user->hasRole([Role::ROLE_ID_MANAGER], $submissionContext->getId()) ||
                $user->hasRole([Role::ROLE_ID_SITE_ADMIN], Application::SITE_CONTEXT_ID))) {
            return $dispatcher->url(
                $request,
                Application::ROUTE_PAGE,
                $submissionContext->getPath(),
                'submission',
                null,
                null,
                ['id' => $submission->getId()]
            );
        }

        // Send authors to author dashboard
        if ($authorDashboard) {
            return $dispatcher->url(
                $request,
                Application::ROUTE_PAGE,
                $submissionContext->getPath(),
                'dashboard',
                'mySubmissions',
                null,
                ['workflowSubmissionId' => $submission->getId()]
            );
        }

        // Send reviewers to review wizard
        $reviewAssignment = Repo::reviewAssignment()->getCollector()
            ->filterBySubmissionIds([$submission->getId()])
            ->filterByReviewerIds([$user->getId()], true)
            ->getMany()
            ->first();

        if ($reviewAssignment && !$reviewAssignment->getCancelled() && !$reviewAssignment->getDeclined()) {
            return $dispatcher->url(
                $request,
                Application::ROUTE_PAGE,
                $submissionContext->getPath(),
                'reviewer',
                'submission',
                [$submission->getId()]
            );
        }

        // Give any other users the editorial workflow URL. If they can't access
        // it, they'll be blocked there.
        return $dispatcher->url(
            $request,
            Application::ROUTE_PAGE,
            $submissionContext->getPath(),
            'dashboard',
            'editorial',
            null,
            ['workflowSubmissionId' => $submission->getId()]
        );
    }

    /**
     * Validate properties for a submission
     *
     * Perform validation checks on data used to add or edit a submission.
     *
     * @param Submission|null $submission The submission being edited. Pass `null` if creating a new submission
     * @param array $props A key/value array with the new data to validate
     *
     * @return array A key/value array with validation errors. Empty if no errors
     *
     * @hook Submission::validate [[&$errors, $submission, $props, $allowedLocales, $primaryLocale]]
     */
    public function validate(?Submission $submission, array $props, Context $context): array
    {
        $primaryLocale = $props['locale'] ?? $submission?->getData('locale') ?? $context->getSupportedDefaultSubmissionLocale();
        $allowedLocales = $context->getSupportedSubmissionLocales();

        if (!in_array($primaryLocale, $allowedLocales)) {
            $allowedLocales[] = $primaryLocale;
        }

        $errors = [];

        $validator = ValidatorFactory::make(
            $props,
            $this->schemaService->getValidationRules(PKPSchemaService::SCHEMA_SUBMISSION, $allowedLocales)
        );

        // Check required fields
        ValidatorFactory::required(
            $validator,
            $submission,
            $this->schemaService->getRequiredProps(PKPSchemaService::SCHEMA_SUBMISSION),
            $this->schemaService->getMultilingualProps(PKPSchemaService::SCHEMA_SUBMISSION),
            $primaryLocale,
            $allowedLocales
        );

        // Check for input from disallowed locales
        ValidatorFactory::allowedLocales($validator, $this->schemaService->getMultilingualProps(PKPSchemaService::SCHEMA_SUBMISSION), $allowedLocales);

        // The submission's locale must be one of the context's supported submission locales
        $validator->after(function ($validator) use ($props, $allowedLocales) {
            if (isset($props['locale']) && !$validator->errors()->get('locale')) {
                if (!in_array($props['locale'], $allowedLocales)) {
                    $validator->errors()->add('locale', __('validator.locale'));
                }
            }
        });

        // The contextId must match an existing context
        $validator->after(function ($validator) use ($props) {
            if (isset($props['contextId']) && !$validator->errors()->get('contextId')) {
                $submissionContext = app()->get('context')->exists($props['contextId']);
                if (!$submissionContext) {
                    $validator->errors()->add('contextId', __('submission.submit.noContext'));
                }
            }
        });

        // The sectionId must match an existing section in this context
        $validator->after(function ($validator) use ($props, $submission) {
            $propName = Application::getSectionIdPropName();
            if ($validator->errors()->get($propName)) {
                return;
            }
            $sectionId = $props[$propName] ?? ($submission ? $submission->getCurrentPublication()->getData($propName) : null);
            if (!$sectionId) {
                return;
            }
            $contextId = $props['contextId'] ?? ($submission ? $submission->getData('contextId') : null);
            if (!Repo::section()->exists($sectionId, $contextId)) {
                $validator->errors()->add($propName, __('submission.sectionNotFound'));
                return;
            }
        });

        // Comments for the editors are invalid after a submission has been submitted
        if ($submission && !$submission->getData('submissionProgress')) {
            $validator->after(function ($validator) use ($props, $submission) {
                if (isset($props['commentsForTheEditors']) && !$validator->errors()->get('commentsForTheEditors')) {
                    $validator->errors()->add('commentsForTheEditors', __('form.disallowedProp'));
                }
            });
        }

        if ($validator->fails()) {
            $errors = $this->schemaService->formatValidationErrors($validator->errors());
        }

        Hook::call('Submission::validate', [&$errors, $submission, $props, $allowedLocales, $primaryLocale]);

        return $errors;
    }

    /**
     * Check if a submission meets all requirements to be submitted
     *
     * @return array A key/value array with validation errors. Empty if no errors
     *
     * @hook Submission::validateSubmit [[&$errors, $submission, $context]]
     */
    public function validateSubmit(Submission $submission, Context $context): array
    {
        $locale = $submission->getData('locale');
        $publication = $submission->getCurrentPublication();

        $errors = [];

        // Can't submit a submission twice or a submission with the wrong status
        if (!$submission->getData('submissionProgress') || ($submission->getData('status') !== Submission::STATUS_QUEUED)) {
            $errors['submissionProgress'] = __(
                'submission.wizard.alreadySubmitted',
                [
                    'url' => Application::get()
                        ->getDispatcher()
                        ->url(
                            Application::get()->getRequest(),
                            Application::ROUTE_PAGE,
                            $context->getData('path'),
                            'dashboard'
                        )
                ]
            );
        }

        // Title required in submission locale
        if (!$publication->getData('title', $locale)) {
            $errors['title'] = [$locale => [__('validator.required')]];
        }

        // Author names required in submission locale
        foreach ($publication->getData('authors') as $author) {
            /** @var Author $author */
            if (!$author->getGivenName($submission->getData('locale'))) {
                if (!isset($errors['contributors'])) {
                    $errors['contributors'] = [];
                }
                $errors['contributors'][] = __('submission.wizard.missingContributorLanguage', ['language' => Locale::getSubmissionLocaleDisplayNames([$locale])[$locale]]);
                break;
            }
            foreach ($author->getAffiliations() as $affiliation) {
                if (!$affiliation->getRor()) {
                    if (!$affiliation->getName($submission->getData('locale'))) {
                        if (!isset($errors['contributors'])) {
                            $errors['contributors'] = [];
                        }
                        $errors['contributors'][] = __('submission.wizard.missingContributorAffiliationLanguage', ['language' => Locale::getSubmissionLocaleDisplayNames([$locale])[$locale]]);
                        break;
                    }
                }
            }
        }

        // Required metadata
        $publicationSchema = $this->schemaService->get(PKPSchemaService::SCHEMA_PUBLICATION);
        foreach ($context->getRequiredMetadata() as $metadata) {
            // The `citations` metadata is received and validated at `citationsRaw`
            if ($metadata === 'citations') {
                $metadata = 'citationsRaw';
            }
            // The `supportingAgencies` metadata is called `agencies` on the context
            if ($metadata === 'agencies') {
                $metadata = 'supportingAgencies';
            }
            $schema = $publicationSchema->properties?->{$metadata};
            if (!$schema) {
                continue;
            }
            if (empty($schema->multilingual) && empty($publication->getData($metadata))) {
                $errors[$metadata] = [__('validator.required')];
            } elseif (!empty($schema->multilingual) && empty($publication->getData($metadata, $locale))) {
                $errors[$metadata] = [$locale => [__('validator.required')]];
            }
        }

        // Required submission files
        $genreDao = DAORegistry::getDAO('GenreDAO'); /** @var GenreDAO $genreDao */
        $requiredGenres = $genreDao->getRequiredToSubmit($context->getId());
        if (!$requiredGenres->isEmpty()) {
            $submissionFiles = Repo::submissionFile()
                ->getCollector()
                ->filterBySubmissionIds([$submission->getId()])
                ->filterByGenreIds(
                    $requiredGenres->map(
                        function (Genre $genre) {
                            return $genre->getId();
                        }
                    )->toArray()
                )
                ->getMany();
            $missingGenres = $submissionFiles->isEmpty()
                ? clone $requiredGenres
                : $requiredGenres->filter(
                    function (Genre $genre) use ($submissionFiles) {
                        $exists = $submissionFiles->first(
                            function (SubmissionFile $submissionFile) use ($genre) {
                                return $submissionFile->getData('genreId') === $genre->getId();
                            }
                        );
                        return !$exists;
                    }
                );
            if ($missingGenres->count()) {
                $missingGenreNames = $missingGenres->map(
                    function (Genre $genre) {
                        return $genre->getLocalizedName();
                    }
                );
                $errors['files'] = [
                    $missingGenres->count() > 1
                        ? __('submission.files.required.genres', [
                            'genres' => $missingGenreNames->join(__('common.commaListSeparator'))
                        ])
                        : __('submission.files.required.genre', ['genre' => $missingGenreNames->first()])
                ];
            }
        }

        Hook::call('Submission::validateSubmit', [&$errors, $submission, $context]);

        return $errors;
    }

    /**
     * Check if a user can delete a submission
     */
    public function canCurrentUserDelete(Submission $submission): bool
    {
        $this->request = Application::get()->getRequest();
        $contextId = $submission->getData('contextId');

        $currentUser = $this->request->getUser();
        if (!$currentUser) {
            return false;
        }

        // Only allow admins and journal managers to delete submissions, except
        // for authors who can delete their own incomplete submissions
        return ($currentUser->hasRole([Role::ROLE_ID_MANAGER], $contextId) || $currentUser->hasRole([Role::ROLE_ID_SITE_ADMIN], Application::SITE_CONTEXT_ID))
            || (
                $submission->getData('submissionProgress') &&
                StageAssignment::withSubmissionIds([$submission->getId()])
                    ->withRoleIds([Role::ROLE_ID_AUTHOR])
                    ->withStageIds([WORKFLOW_STAGE_ID_SUBMISSION])
                    ->withUserId($currentUser->getId())
                    ->get()
                    ->isNotEmpty()
            );
    }

    /**
     * Check if a user can edit the publication metadata of a submission
     */
    public function canEditPublication(int $submissionId, int $userId): bool
    {
        // block authors can never edit a published publication even if an editor granted them canChangeMetadata
        $assignments = StageAssignment::withSubmissionIds([$submissionId])
            ->withUserId($userId)
            ->get();

        $submission = $this->get($submissionId);
        // any published or scheduled then probe
        $hasLockedPublication = $submission?->getData('publications')
            ->contains(
                fn (Publication $p) =>
                    in_array(
                        $p->getData('status'),
                        [Submission::STATUS_PUBLISHED, Submission::STATUS_SCHEDULED]
                    )
            );

        if ($hasLockedPublication && !$assignments->contains(fn (StageAssignment $sa) => $sa->userGroup && $sa->userGroup->roleId != Role::ROLE_ID_AUTHOR)) {
            return false;
        }

        if ($assignments->contains(fn($sa) => $sa->canChangeMetadata)) {
            return true;
        }
        // If user has no stage assigments, check if user can edit anyway ie. is manager
        $context = Application::get()->getRequest()->getContext();
        if ($assignments->isEmpty() && $this->_canUserAccessUnassignedSubmissions($context->getId(), $userId)) {
            return true;
        }
        // Else deny access
        return false;
    }

    /**
     * Checks if this user is granted reader access to pre-publication submissions
     * based on their roles in the context (i.e. Manager, Editor, etc).
     */
    public function canPreview(?User $user, Submission $submission): bool
    {
        // Only grant access when in copyediting or production stage
        if (!in_array($submission->getData('stageId'), [WORKFLOW_STAGE_ID_EDITING, WORKFLOW_STAGE_ID_PRODUCTION])) {
            return false;
        }

        if ($this->_roleCanPreview($user, $submission)) {
            return true;
        }

        if ($user) {
            // Replaces StageAssignmentDAO::getBySubmissionAndRoleId
            $stageAssignments = StageAssignment::withSubmissionIds([$submission->getId()])
                ->withRoleIds([Role::ROLE_ID_AUTHOR])
                ->withUserId($user->getId())
                ->get();

            if ($stageAssignments->isNotEmpty()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Add a new submission
     *
     * @hook Submission::add [[$submission]]
     */
    public function add(Submission $submission, Publication $publication, Context $context): int
    {
        $submission->stampLastActivity();
        $submission->stampModified();
        if (!$submission->getData('dateSubmitted') && !$submission->getData('submissionProgress')) {
            $submission->setData('dateSubmitted', Core::getCurrentDate());
        }
        if (!$submission->getData('status')) {
            $submission->setData('status', Submission::STATUS_QUEUED);
        }
        if (!$submission->getData('locale')) {
            $submission->setData('locale', $context->getSupportedDefaultSubmissionLocale());
        }
        $submissionId = $this->dao->insert($submission);
        $submission = Repo::submission()->get($submissionId);

        $publication->setData('submissionId', $submission->getId());
        $publication->setData('version', 1);
        if (!$publication->getData('status')) {
            $publication->setData('status', $submission->getData('status'));
        }

        $publicationId = Repo::publication()->add($publication);

        $this->edit($submission, ['currentPublicationId' => $publicationId]);

        Hook::call('Submission::add', [$submission]);

        return $submission->getId();
    }

    /** @copydoc DAO::update */
    public function edit(Submission $submission, array $params)
    {
        $newSubmission = Repo::submission()->newDataObject(array_merge($submission->_data, $params));
        $newSubmission->stampLastActivity();
        $newSubmission->stampModified();

        Hook::call('Submission::edit', [$newSubmission, $submission, $params]);

        $this->dao->update($newSubmission);
    }

    /**
     * Submit a submission
     *
     * Changes the submissionProgress property, creates the comments
     * for the editors discussion, and fires the SubmissionSubmitted
     * event.
     */
    public function submit(Submission $submission, Context $context): void
    {
        $this->edit($submission, [
            'submissionProgress' => '',
            'dateSubmitted' => Core::getCurrentDate(),
        ]);

        $submission = $this->get($submission->getId());

        event(
            new SubmissionSubmitted(
                $submission,
                $context
            )
        );

        if ($submission->getData('commentsForTheEditors')) {
            Repo::query()->addCommentsForEditorsQuery($submission);
        }
    }

    /** @copydoc DAO::delete */
    public function delete(Submission $submission)
    {
        Hook::call('Submission::delete::before', [&$submission]);

        $this->dao->delete($submission);

        Hook::call('Submission::delete', [$submission]);
    }

    /**
     * Delete all submissions in a context
     */
    public function deleteByContextId(int $contextId)
    {
        $submissionIds = Repo::submission()->getCollector()->filterByContextIds([$contextId])->getIds();
        foreach ($submissionIds as $submissionId) {
            $this->dao->deleteById($submissionId);
        }
    }

    /**
     * Update a submission's status
     *
     * Changes a submission's status. Or, if no new status is provided,
     * sets the appropriate status based on all of the submission's
     * publications.
     *
     * This method performs any actions necessary when a submission's
     * status changes, such as changing the current publication ID
     * and creating or deleting tombstones.
     *
     * @param ?Section $section If this submission is being deleted, its previous section ID should be specified
     *    in order to ensure a correctly created tombstone.
     *
     * @hook Submission::updateStatus [[&$newStatus, $status, $submission]]
     */
    public function updateStatus(Submission $submission, ?int $newStatus = null, ?Section $section = null)
    {
        $status = $submission->getData('status');

        if ($newStatus === null) {
            $newStatus = $this->getStatusByPublications($submission);
        }

        Hook::call('Submission::updateStatus', [&$newStatus, $status, $submission]);

        if ($status !== $newStatus) {
            $submission->setData('status', $newStatus);
        }

        $currentPublicationId = $newCurrentPublicationId = $submission->getData('currentPublicationId');
        $newCurrentPublicationId = $this->getCurrentPublicationIdByPublications($submission);
        if ($currentPublicationId !== $newCurrentPublicationId) {
            $submission->setData('currentPublicationId', $newCurrentPublicationId);
        }

        // Use the DAO instead of the Repository to prevent
        // calling this method over and over again.
        $this->dao->update($submission);
    }

    /**
     * Set license information for all submissions in a context
     * to the context's default license.
     */
    public function resetPermissions(int $contextId)
    {
        $submissions = Repo::submission()->getCollector()->filterByContextIds([$contextId])->getMany();
        foreach ($submissions as $submission) {
            $publications = $submission->getData('publications');
            if (empty($publications)) {
                continue;
            }
            $params = [
                'copyrightYear' => $submission->_getContextLicenseFieldValue(null, Submission::PERMISSIONS_FIELD_COPYRIGHT_YEAR),
                'copyrightHolder' => $submission->_getContextLicenseFieldValue(null, Submission::PERMISSIONS_FIELD_COPYRIGHT_HOLDER),
                'licenseUrl' => $submission->_getContextLicenseFieldValue(null, Submission::PERMISSIONS_FIELD_LICENSE_URL),
            ];
            foreach ($publications as $publication) {
                Repo::publication()->edit($publication, $params);
            }
        }
    }

    /**
     * Get an array of sort options used in forms when configuring
     * how published submissions are displayed
     */
    public function getSortSelectOptions(): array
    {
        return [
            $this->getSortOption(Collector::ORDERBY_TITLE, Collector::ORDER_DIR_ASC) => __('catalog.sortBy.titleAsc'),
            $this->getSortOption(Collector::ORDERBY_TITLE, Collector::ORDER_DIR_DESC) => __('catalog.sortBy.titleDesc'),
            $this->getSortOption(Collector::ORDERBY_DATE_PUBLISHED, Collector::ORDER_DIR_ASC) => __('catalog.sortBy.datePublishedAsc'),
            $this->getSortOption(Collector::ORDERBY_DATE_PUBLISHED, Collector::ORDER_DIR_DESC) => __('catalog.sortBy.datePublishedDesc'),
        ];
    }

    /**
     * Get the default sort option used in forms when configuring
     * how published submissions are displayed
     *
     * @see self::getSortSelectOptions()
     */
    public function getDefaultSortOption(): string
    {
        return $this->getSortOption(Collector::ORDERBY_DATE_PUBLISHED, Collector::ORDER_DIR_DESC);
    }

    /**
     * Get the URL to the API endpoint for a submission
     */
    public function getUrlApi(Context $context, ?int $submissionId = null): string
    {
        return Application::get()->getDispatcher()->url(
            Application::get()->getRequest(),
            Application::ROUTE_API,
            $context->getData('urlPath'),
            'submissions' . ($submissionId ? '/' . $submissionId : ''),
        );
    }

    /**
     * Get the URL to the author workflow for a submission
     */
    public function getUrlAuthorWorkflow(Context $context, int $submissionId): string
    {
        return Application::get()->getDispatcher()->url(
            Application::get()->getRequest(),
            Application::ROUTE_PAGE,
            $context->getData('urlPath'),
            'dashboard',
            'mySubmissions',
            null,
            ['workflowSubmissionId' => $submissionId]
        );
    }

    /**
     * Get the URL to the editorial workflow for a submission
     */
    public function getUrlEditorialWorkflow(Context $context, int $submissionId): string
    {
        return Application::get()->getDispatcher()->url(
            Application::get()->getRequest(),
            Application::ROUTE_PAGE,
            $context->getData('urlPath'),
            'dashboard',
            'editorial',
            null,
            ['workflowSubmissionId' => $submissionId]
        );
    }

    /**
     * Get the URL to the submission wizard for a submission
     */
    public function getUrlSubmissionWizard(Context $context, ?int $submissionId = null): string
    {
        return Application::get()->getDispatcher()->url(
            Application::get()->getRequest(),
            Application::ROUTE_PAGE,
            $context->getData('urlPath'),
            'submission',
            null,
            null,
            $submissionId
                ? ['id' => $submissionId]
                : null
        );
    }

    /**
     * Get all views, views count to be retrieved separately due to performance reasons
     */
    public function getDashboardViews(Context $context, User $user, array $selectedRoleIds = [], bool $includeCount = false): Collection
    {
        $types = DashboardView::getTypes()->flip();
        $roleDao = DAORegistry::getDAO('RoleDAO'); /** @var RoleDAO $roleDao */
        $roles = $roleDao->getByUserId($user->getId(), $context->getId());
        $roleIds = [];
        foreach ($roles as $role) {
            $roleIds[] = $role->getRoleId();
        }
        if ($selectedRoleIds) {
            $roleIds = array_values(array_intersect($roleIds, $selectedRoleIds));
        }

        $canAccessUnassignedSubmission = !empty(array_intersect([Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER], $roleIds));

        $views = $this->mapDashboardViews($types, $context, $user, $canAccessUnassignedSubmission, $selectedRoleIds);
        $filteredViews = $this->filterViewsByUserRoles($views, $roleIds);

        if ($includeCount) {
            return $this->setViewsCount($filteredViews);
        }

        return $filteredViews;
    }

    /**
     * Returns a Collection of mapped dashboard views
     */
    protected function mapDashboardViews(Collection $types, Context $context, User $user, bool $canAccessUnassignedSubmission, array $selectedRoleIds = []): Collection
    {
        return $types->map(function (int $item, string $key) use ($context, $user, $canAccessUnassignedSubmission, $selectedRoleIds) {
            switch ($key) {
                case DashboardView::TYPE_ASSIGNED:
                    return new DashboardView(
                        $key,
                        __('submission.dashboard.view.assigned'),
                        [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT],
                        Repo::submission()->getCollector()
                            ->filterByContextIds([$context->getId()])
                            ->filterByStatus([PKPSubmission::STATUS_QUEUED])
                            ->assignedTo([$user->getId()], $selectedRoleIds),
                        'assigned',
                        ['status' => [PKPSubmission::STATUS_QUEUED], 'assignedWithRoles' => $selectedRoleIds]
                    );
                case DashboardView::TYPE_ACTIVE:
                    $assignedWithRoles = $canAccessUnassignedSubmission ? null : $selectedRoleIds;

                    $collector = Repo::submission()->getCollector()
                        ->filterByContextIds([$context->getId()])
                        ->filterByStatus([PKPSubmission::STATUS_QUEUED]);
                    return new DashboardView(
                        $key,
                        __('submission.dashboard.view.active'),
                        [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_AUTHOR],
                        $canAccessUnassignedSubmission
                            ? $collector
                            : $collector->assignedTo([$user->getId()], $assignedWithRoles),
                        $canAccessUnassignedSubmission ? null : 'assigned',
                        ['status' => [PKPSubmission::STATUS_QUEUED], 'assignedWithRoles' => $assignedWithRoles],
                    );
                case DashboardView::TYPE_NEEDS_EDITOR:
                    return new DashboardView(
                        $key,
                        __('submission.dashboard.view.needsEditor'),
                        [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER],
                        Repo::submission()->getCollector()
                            ->filterByContextIds([$context->getId()])
                            ->filterByisUnassigned(true)
                            ->filterByStatus([PKPSubmission::STATUS_QUEUED]),
                        null,
                        ['isUnassigned' => true, 'status' => [PKPSubmission::STATUS_QUEUED]]
                    );
                case DashboardView::TYPE_SUBMISSION:
                    $assignedWithRoles = $canAccessUnassignedSubmission ? null : $selectedRoleIds;

                    $collector = Repo::submission()->getCollector()
                        ->filterByContextIds([$context->getId()])
                        ->filterByStageIds([WORKFLOW_STAGE_ID_SUBMISSION])
                        ->filterByStatus([PKPSubmission::STATUS_QUEUED]);
                    return new DashboardView(
                        $key,
                        __('submission.dashboard.view.submissionStageAll'),
                        [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT],
                        $canAccessUnassignedSubmission
                            ? $collector
                            : $collector->assignedTo([$user->getId()], $assignedWithRoles),
                        $canAccessUnassignedSubmission ? null : 'assigned',
                        ['stageIds' => [WORKFLOW_STAGE_ID_SUBMISSION], 'status' => [PKPSubmission::STATUS_QUEUED], 'assignedWithRoles' => $assignedWithRoles]
                    );
                case DashboardView::TYPE_REVIEW_EXTERNAL:
                    $assignedWithRoles = $canAccessUnassignedSubmission ? null : $selectedRoleIds;

                    $collector = Repo::submission()->getCollector()
                        ->filterByContextIds([$context->getId()])
                        ->filterByStageIds([WORKFLOW_STAGE_ID_EXTERNAL_REVIEW])
                        ->filterByStatus([PKPSubmission::STATUS_QUEUED]);
                    return new DashboardView(
                        $key,
                        __('submission.dashboard.view.reviewAll'),
                        [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT],
                        $canAccessUnassignedSubmission
                            ? $collector
                            : $collector->assignedTo([$user->getId()], $assignedWithRoles),
                        $canAccessUnassignedSubmission ? null : 'assigned',
                        ['stageIds' => [WORKFLOW_STAGE_ID_EXTERNAL_REVIEW], 'status' => [PKPSubmission::STATUS_QUEUED], 'assignedWithRoles' => $assignedWithRoles]
                    );
                case DashboardView::TYPE_NEEDS_REVIEWS:
                    $assignedWithRoles = $canAccessUnassignedSubmission ? null : $selectedRoleIds;

                    $collector = Repo::submission()->getCollector()
                        ->filterByContextIds([$context->getId()])
                        ->filterByNumReviewsConfirmedLimit(
                            $context->getNumReviewsPerSubmission() == Context::REVIEWS_DEFAULT_COUNT ?
                                Context::REVIEWS_REQUIRED_COUNT :
                                $context->getNumReviewsPerSubmission()
                        )
                        ->filterByStatus([PKPSubmission::STATUS_QUEUED]);
                    return new DashboardView(
                        $key,
                        __('submission.dashboard.view.needsReviews'),
                        [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT],
                        $canAccessUnassignedSubmission
                            ? $collector
                            : $collector->assignedTo([$user->getId()], $assignedWithRoles),
                        'reviews',
                        ['needsReviews' => true, 'assignedWithRoles' => $assignedWithRoles]
                    );
                case DashboardView::TYPE_AWAITING_REVIEWS:
                    $assignedWithRoles = $canAccessUnassignedSubmission ? null : $selectedRoleIds;

                    $collector = Repo::submission()->getCollector()
                        ->filterByContextIds([$context->getId()])
                        ->filterByAwaitingReviews(true)
                        ->filterByStatus([PKPSubmission::STATUS_QUEUED]);
                    return new DashboardView(
                        $key,
                        __('submission.dashboard.view.awaitingReviews'),
                        [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT],
                        $canAccessUnassignedSubmission
                            ? $collector
                            : $collector->assignedTo([$user->getId()], $assignedWithRoles),
                        'reviews',
                        ['awaitingReviews' => true, 'assignedWithRoles' => $assignedWithRoles]
                    );
                case DashboardView::TYPE_REVIEWS_SUBMITTED:
                    $assignedWithRoles = $canAccessUnassignedSubmission ? null : $selectedRoleIds;

                    $collector = Repo::submission()->getCollector()
                        ->filterByContextIds([$context->getId()])
                        ->filterByReviewsSubmitted(true)
                        ->filterByStatus([PKPSubmission::STATUS_QUEUED]);
                    return new DashboardView(
                        $key,
                        __('submission.dashboard.view.reviewsSubmitted'),
                        [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT],
                        $canAccessUnassignedSubmission
                            ? $collector
                            : $collector->assignedTo([$user->getId()], $assignedWithRoles),
                        'reviews',
                        ['reviewsSubmitted' => true, 'assignedWithRoles' => $assignedWithRoles]
                    );
                case DashboardView::TYPE_REVIEWS_OVERDUE:
                    $assignedWithRoles = $canAccessUnassignedSubmission ? null : $selectedRoleIds;

                    $collector = Repo::submission()->getCollector()
                        ->filterByContextIds([$context->getId()])
                        ->filterByReviewsOverdue(true)
                        ->filterByStatus([PKPSubmission::STATUS_QUEUED]);
                    return new DashboardView(
                        $key,
                        __('submission.dashboard.view.reviewsOverdue'),
                        [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT],
                        $canAccessUnassignedSubmission
                            ? $collector
                            : $collector->assignedTo([$user->getId()], $assignedWithRoles),
                        'reviews',
                        ['reviewsOverdue' => true, 'assignedWithRoles' => $assignedWithRoles]
                    );
                case DashboardView::TYPE_COPYEDITING:
                    $assignedWithRoles = $canAccessUnassignedSubmission ? null : $selectedRoleIds;

                    $collector = Repo::submission()->getCollector()
                        ->filterByContextIds([$context->getId()])
                        ->filterByStageIds([WORKFLOW_STAGE_ID_EDITING])
                        ->filterByStatus([PKPSubmission::STATUS_QUEUED]);
                    return new DashboardView(
                        $key,
                        __('submission.dashboard.view.copyediting'),
                        [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT],
                        $canAccessUnassignedSubmission
                            ? $collector
                            : $collector->assignedTo([$user->getId()], $assignedWithRoles),
                        $canAccessUnassignedSubmission ? null : 'assigned',
                        ['stageIds' => [WORKFLOW_STAGE_ID_EDITING], 'status' => [PKPSubmission::STATUS_QUEUED], 'assignedWithRoles' => $assignedWithRoles]
                    );
                case DashboardView::TYPE_PRODUCTION:
                    $assignedWithRoles = $canAccessUnassignedSubmission ? null : $selectedRoleIds;

                    $collector = Repo::submission()->getCollector()
                        ->filterByContextIds([$context->getId()])
                        ->filterByStageIds([WORKFLOW_STAGE_ID_PRODUCTION])
                        ->filterByStatus([PKPSubmission::STATUS_QUEUED]);
                    return new DashboardView(
                        $key,
                        __('submission.dashboard.view.production'),
                        [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT],
                        $canAccessUnassignedSubmission
                            ? $collector
                            : $collector->assignedTo([$user->getId()], $assignedWithRoles),
                        $canAccessUnassignedSubmission ? null : 'assigned',
                        ['stageIds' => [WORKFLOW_STAGE_ID_PRODUCTION], 'status' => [PKPSubmission::STATUS_QUEUED], 'assignedWithRoles' => $assignedWithRoles]
                    );
                case DashboardView::TYPE_SCHEDULED:
                    $assignedWithRoles = $canAccessUnassignedSubmission ? null : $selectedRoleIds;

                    $collector = Repo::submission()->getCollector()
                        ->filterByContextIds([$context->getId()])
                        ->filterByStatus([PKPSubmission::STATUS_SCHEDULED]);
                    return new DashboardView(
                        $key,
                        __('submission.dashboard.view.scheduled'),
                        [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_AUTHOR],
                        $canAccessUnassignedSubmission
                            ? $collector
                            : $collector->assignedTo([$user->getId()], $assignedWithRoles),
                        $canAccessUnassignedSubmission ? null : 'assigned',
                        ['status' => [PKPSubmission::STATUS_SCHEDULED], 'assignedWithRoles' => $assignedWithRoles]
                    );
                case DashboardView::TYPE_PUBLISHED:
                    $assignedWithRoles = $canAccessUnassignedSubmission ? null : $selectedRoleIds;

                    $collector = Repo::submission()->getCollector()
                        ->filterByContextIds([$context->getId()])
                        ->filterByStatus([PKPSubmission::STATUS_PUBLISHED]);
                    return new DashboardView(
                        $key,
                        __('submission.dashboard.view.published'),
                        [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_AUTHOR],
                        $canAccessUnassignedSubmission
                            ? $collector
                            : $collector->assignedTo([$user->getId()], $assignedWithRoles),
                        $canAccessUnassignedSubmission ? null : 'assigned',
                        ['status' => [PKPSubmission::STATUS_PUBLISHED], 'assignedWithRoles' => $assignedWithRoles]
                    );
                case DashboardView::TYPE_DECLINED:
                    $assignedWithRoles = $canAccessUnassignedSubmission ? null : $selectedRoleIds;

                    $collector = Repo::submission()->getCollector()
                        ->filterByContextIds([$context->getId()])
                        ->filterByStatus([PKPSubmission::STATUS_DECLINED]);
                    return new DashboardView(
                        $key,
                        __('submission.dashboard.view.declined'),
                        [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_AUTHOR],
                        $canAccessUnassignedSubmission
                            ? $collector
                            : $collector->assignedTo([$user->getId()], $assignedWithRoles),
                        $canAccessUnassignedSubmission ? null : 'assigned',
                        ['status' => [PKPSubmission::STATUS_DECLINED], 'assignedWithRoles' => $assignedWithRoles]
                    );
                case DashboardView::TYPE_REVISIONS_REQUESTED:
                    return new DashboardView(
                        $key,
                        __('submission.dashboard.view.revisionsRequested'),
                        [Role::ROLE_ID_AUTHOR],
                        Repo::submission()->getCollector()
                            ->assignedTo([$user->getId()], $selectedRoleIds)
                            ->filterByContextIds([$context->getId()])
                            ->filterByRevisionsRequested(true)
                            ->filterByStatus([PKPSubmission::STATUS_QUEUED]),
                        'reviews',
                        ['revisionsRequested' => true, 'assignedWithRoles' => $selectedRoleIds]
                    );
                case DashboardView::TYPE_REVISIONS_SUBMITTED:
                    $assignedWithRoles = $canAccessUnassignedSubmission ? null : $selectedRoleIds;

                    $collector = Repo::submission()->getCollector()
                        ->filterByContextIds([$context->getId()])
                        ->filterByRevisionsSubmitted(true)
                        ->filterByStatus([PKPSubmission::STATUS_QUEUED]);
                    return new DashboardView(
                        $key,
                        in_array(Role::ROLE_ID_AUTHOR, $selectedRoleIds) ? __('submission.list.revisionsSubmitted') : __('submission.dashboard.view.revisionsSubmitted'),
                        [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_AUTHOR],
                        $canAccessUnassignedSubmission
                            ? $collector
                            : $collector->assignedTo([$user->getId()], $assignedWithRoles),
                        'reviews',
                        ['revisionsSubmitted' => true, 'assignedWithRoles' => $assignedWithRoles]
                    );
                case DashboardView::TYPE_INCOMPLETE_SUBMISSIONS:
                    return new DashboardView(
                        $key,
                        __('submission.dashboard.view.incompleteSubmissions'),
                        [Role::ROLE_ID_AUTHOR],
                        Repo::submission()->getCollector()
                            ->assignedTo([$user->getId()], $selectedRoleIds)
                            ->filterByContextIds([$context->getId()])
                            ->filterByIncomplete(true),
                        'assigned',
                        ['isIncomplete' => true, 'assignedWithRoles' => $selectedRoleIds]
                    );
                case DashboardView::TYPE_REVIEWER_ACTION_REQUIRED:
                    return new DashboardView(
                        $key,
                        __('submission.dashboard.view.reviewAssignments.actionRequired'),
                        [Role::ROLE_ID_REVIEWER],
                        Repo::reviewAssignment()->getCollector()
                            ->filterByReviewerIds([$user->getId()], true)
                            ->filterByContextIds([$context->getId()])
                            ->filterByActionRequiredByReviewer(true),
                        'reviewerAssignments',
                        ['actionRequired' => true]
                    );
                case DashboardView::TYPE_REVIEWER_ASSIGNMENTS_ALL:
                    return new DashboardView(
                        $key,
                        __('submission.dashboard.view.reviewAssignments.all'),
                        [Role::ROLE_ID_REVIEWER],
                        Repo::reviewAssignment()->getCollector()
                            ->filterByReviewerIds([$user->getId()], true)
                            ->filterByContextIds([$context->getId()])
                            ->filterByActive(true),
                        'reviewerAssignments',
                        ['active' => true]
                    );
                case DashboardView::TYPE_REVIEWER_ASSIGNMENTS_COMPLETED:
                    return new DashboardView(
                        $key,
                        __('submission.dashboard.view.reviewAssignments.completed'),
                        [Role::ROLE_ID_REVIEWER],
                        Repo::reviewAssignment()->getCollector()
                            ->filterByReviewerIds([$user->getId()], true)
                            ->filterByContextIds([$context->getId()])
                            ->filterByCompleted(true),
                        'reviewerAssignments',
                        ['completed' => true]
                    );
                case DashboardView::TYPE_REVIEWER_ASSIGNMENTS_PUBLISHED:
                    return new DashboardView(
                        $key,
                        __('submission.dashboard.view.reviewAssignments.published'),
                        [Role::ROLE_ID_REVIEWER],
                        Repo::reviewAssignment()->getCollector()
                            ->filterByReviewerIds([$user->getId()], true)
                            ->filterByContextIds([$context->getId()])
                            ->filterByPublished(true),
                        'reviewerAssignments',
                        ['published' => true]
                    );
                case DashboardView::TYPE_REVIEWER_ASSIGNMENTS_ARCHIVED:
                    return new DashboardView(
                        $key,
                        __('submission.dashboard.view.reviewAssignments.archived'),
                        [Role::ROLE_ID_REVIEWER],
                        Repo::reviewAssignment()->getCollector()
                            ->filterByReviewerIds([$user->getId()], true)
                            ->filterByContextIds([$context->getId()])
                            ->filterByIsArchived(true),
                        'reviewerAssignments',
                        ['archived' => true]
                    );
                case DashboardView::TYPE_REVIEWER_ASSIGNMENTS_DECLINED:
                    return new DashboardView(
                        $key,
                        __('submission.dashboard.view.reviewAssignments.declined'),
                        [Role::ROLE_ID_REVIEWER],
                        Repo::reviewAssignment()->getCollector()
                            ->filterByReviewerIds([$user->getId()], true)
                            ->filterByContextIds([$context->getId()])
                            ->filterByDeclined(true),
                        'reviewerAssignments',
                        ['declined' => true]
                    );

            }
        });
    }

    protected function filterViewsByUserRoles(Collection $views, array $roleIds): Collection
    {
        return $views->filter(function (?DashboardView $view) use ($roleIds) {
            if (!is_null($view)) { // null check to filter out views not used by the application
                return array_intersect($roleIds, $view->getRoles());
            }
        });
    }

    /**
     * @param Collection<DashboardView> $dashboardViews
     *
     * Set the submissions/reviews count to the list of dashboard views
     */
    protected function setViewsCount(Collection $dashboardViews): Collection
    {
        $submissionCollectors = collect();
        $reviewCollectors = collect();
        foreach ($dashboardViews as $id => $dashboardView) {
            $collector = $dashboardView->getCollector();
            is_a($collector, SubmissionCollector::class) ?
                $submissionCollectors->put($id, $collector) :
                $reviewCollectors->put($id, $collector);
        }

        $submissionsCount = $submissionCollectors->isNotEmpty() ?
            get_object_vars(SubmissionCollector::getViewsCountBuilder($submissionCollectors)?->first() ?? []) :
            [];

        $reviewsCount = $reviewCollectors->isNotEmpty() ?
            get_object_vars(ReviewCollector::getViewsCountBuilder($reviewCollectors)?->first() ?? []) :
            [];


        foreach (array_merge($submissionsCount, $reviewsCount) as $viewId => $count) {
            $view = $dashboardViews->get($viewId); /** @var DashboardView $view */
            $view->setCount($count);
        }

        return $dashboardViews;
    }

    /**
     * Creates and assigns DOIs to all sub-objects if:
     * 1) the suffix pattern can currently be created, and
     * 2) it does not already exist.
     *
     * @return DoiException[]
     */
    abstract public function createDois(Submission $submission): array;

    /**
     * Compile the sort orderBy and orderDirection into an option
     * used in forms
     */
    protected function getSortOption(string $sortBy, string $sortDir): string
    {
        return $sortBy . '-' . $sortDir;
    }


    /**
     * Check if a user is allowed to edit publication metadata for submissions
     * they are not assigned to
     */
    protected function _canUserAccessUnassignedSubmissions(int $contextId, int $userId): bool
    {
        $roleDao = DAORegistry::getDAO('RoleDAO'); /** @var RoleDAO $roleDao */
        $roles = $roleDao->getByUserId($userId, $contextId);

        $allowedRoles = Repo::userGroup()::NOT_CHANGE_METADATA_EDIT_PERMISSION_ROLES;
        foreach ($roles as $role) {
            if (in_array($role->getRoleId(), $allowedRoles)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get the appropriate status of a submission based on the
     * statuses of its publications
     */
    protected function getStatusByPublications(Submission $submission): int
    {
        $publications = $submission->getData('publications'); /** @var LazyCollection $publications */

        // Declined submissions should remain declined regardless of their publications' statuses
        if ($submission->getData('status') === Submission::STATUS_DECLINED) {
            return Submission::STATUS_DECLINED;
        }

        // If there are no publications, we are probably in the process of deleting a submission.
        // To be safe, reset the status anyway.
        if (!$publications->count()) {
            return Submission::STATUS_DECLINED
                ? Submission::STATUS_DECLINED
                : Submission::STATUS_QUEUED;
        }

        $newStatus = Submission::STATUS_QUEUED;
        foreach ($publications as $publication) {
            if ($publication->getData('status') === Submission::STATUS_PUBLISHED) {
                $newStatus = Submission::STATUS_PUBLISHED;
                break;
            }
            if ($publication->getData('status') === Submission::STATUS_SCHEDULED) {
                $newStatus = Submission::STATUS_SCHEDULED;
                continue;
            }
        }

        return $newStatus;
    }

    /**
     * Get the appropriate currentPublicationId for a submission based on the
     * statues of its publications
     */
    protected function getCurrentPublicationIdByPublications(Submission $submission): ?int
    {
        $publications = $submission->getData('publications'); /** @var LazyCollection $publications */

        if (!$publications->count()) {
            return null;
        }

        // Use the latest published publication
        $newCurrentPublicationId = $publications->reduce(function ($a, $b) {
            return $b->getData('status') === Submission::STATUS_PUBLISHED && $b->getId() > $a ? $b->getId() : $a;
        }, 0);

        // If there is no published publication, use the latest publication
        if (!$newCurrentPublicationId) {
            $newCurrentPublicationId = $publications->reduce(function ($a, $b) {
                return $a > $b->getId() ? $a : $b->getId();
            }, 0);
        }

        return $newCurrentPublicationId ?? $submission->getData('currentPublicationId');
    }

    /**
     * Checks if this user is granted access to preview
     * based on their roles in the context (i.e. Manager, Editor, etc).
     *
     *
     */
    protected function _roleCanPreview(?User $user, Submission $submission): bool
    {
        if (!$user) {
            return false;
        }

        $subscriptionAssumedRoles = [
            Role::ROLE_ID_MANAGER,
            Role::ROLE_ID_SUB_EDITOR,
            Role::ROLE_ID_ASSISTANT,
            Role::ROLE_ID_SUBSCRIPTION_MANAGER
        ];

        /** @var RoleDAO $roleDao */
        $roleDao = DAORegistry::getDAO('RoleDAO');
        $roles = $roleDao->getByUserId($user->getId(), $submission->getData('contextId'));
        foreach ($roles as $role) {
            if (in_array($role->getRoleId(), $subscriptionAssumedRoles)) {
                return true;
            }
        }
        return false;
    }
}
