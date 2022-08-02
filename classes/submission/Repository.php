<?php
/**
 * @file classes/submission/Repository.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class submission
 *
 * @brief A repository to find and manage submissions.
 */

namespace PKP\submission;

use APP\core\Application;
use APP\core\Request;
use APP\core\Services;
use APP\facades\Repo;
use APP\publication\Publication;
use APP\submission\Collector;
use APP\submission\DAO;
use APP\submission\Submission;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Illuminate\Support\LazyCollection;
use PKP\core\Core;
use PKP\db\DAORegistry;
use PKP\doi\exceptions\DoiCreationException;
use PKP\plugins\HookRegistry;
use PKP\services\PKPSchemaService;
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

    /** @var PKPSchemaService $schemaService */
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
    public function exists(int $id): bool
    {
        return $this->dao->exists($id);
    }

    /** @copydoc DAO::get() */
    public function get(int $id): ?Submission
    {
        return $this->dao->get($id);
    }

    /** @copydoc DAO::getCount() */
    public function getCount(Collector $query): int
    {
        return $this->dao->getCount($query);
    }

    /** @copydoc DAO::getIds() */
    public function getIds(Collector $query): Collection
    {
        return $this->dao->getIds($query);
    }

    /** @copydoc DAO::getMany() */
    public function getMany(Collector $query): LazyCollection
    {
        return $this->dao->getMany($query);
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
            $submissionContext = Services::get('context')->get($submission->getData('contextId'));
        }

        $dispatcher = $request->getDispatcher();

        // Check if the user is an author of this submission
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */
        $authorUserGroupIds = $userGroupDao->getUserGroupIdsByRoleId(ROLE_ID_AUTHOR);
        $stageAssignmentsFactory = $stageAssignmentDao->getBySubmissionAndStageId($submission->getId(), null, null, $user->getId());

        $authorDashboard = false;
        while ($stageAssignment = $stageAssignmentsFactory->next()) {
            if (in_array($stageAssignment->getUserGroupId(), $authorUserGroupIds)) {
                $authorDashboard = true;
            }
        }

        // Send authors, journal managers and site admins to the submission
        // wizard for incomplete submissions
        if ($submission->getSubmissionProgress() > 0 &&
            ($authorDashboard ||
                $user->hasRole([ROLE_ID_MANAGER], $submissionContext->getId()) ||
                $user->hasRole([ROLE_ID_SITE_ADMIN], Application::CONTEXT_SITE))) {
            return $dispatcher->url(
                $request,
                Application::ROUTE_PAGE,
                $submissionContext->getPath(),
                'submission',
                'wizard',
                $submission->getSubmissionProgress(),
                ['submissionId' => $submission->getId()]
            );
        }

        // Send authors to author dashboard
        if ($authorDashboard) {
            return $dispatcher->url(
                $request,
                Application::ROUTE_PAGE,
                $submissionContext->getPath(),
                'authorDashboard',
                'submission',
                $submission->getId()
            );
        }

        // Send reviewers to review wizard
        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
        $reviewAssignment = $reviewAssignmentDao->getLastReviewRoundReviewAssignmentByReviewer($submission->getId(), $user->getId());
        if ($reviewAssignment && !$reviewAssignment->getCancelled() && !$reviewAssignment->getDeclined()) {
            return $dispatcher->url(
                $request,
                Application::ROUTE_PAGE,
                $submissionContext->getPath(),
                'reviewer',
                'submission',
                $submission->getId()
            );
        }

        // Give any other users the editorial workflow URL. If they can't access
        // it, they'll be blocked there.
        return $dispatcher->url(
            $request,
            Application::ROUTE_PAGE,
            $submissionContext->getPath(),
            'workflow',
            'access',
            $submission->getId()
        );
    }

    /**
     * Validate properties for a submission
     *
     * Perform validation checks on data used to add or edit a submission.
     *
     * @param Submission|null $submission The submission being edited. Pass `null` if creating a new submission
     * @param array $props A key/value array with the new data to validate
     * @param array $allowedLocales The context's supported submission locales
     * @param string $primaryLocale The submission's primary locale
     *
     * @return array A key/value array with validation errors. Empty if no errors
     */
    public function validate(?Submission $submission, array $props, array $allowedLocales, string $primaryLocale): array
    {
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

        // The contextId must match an existing context
        $validator->after(function ($validator) use ($props) {
            if (isset($props['contextId']) && !$validator->errors()->get('contextId')) {
                $submissionContext = Services::get('context')->get($props['contextId']);
                if (!$submissionContext) {
                    $validator->errors()->add('contextId', __('submission.submit.noContext'));
                }
            }
        });

        if ($validator->fails()) {
            $errors = $this->schemaService->formatValidationErrors($validator->errors());
        }

        HookRegistry::call('Submission::validate', [&$errors, $submission, $props, $allowedLocales, $primaryLocale]);

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

        $canDelete = false;

        // Only allow admins and journal managers to delete submissions, except
        // for authors who can delete their own incomplete submissions
        if ($currentUser->hasRole([ROLE_ID_MANAGER], $contextId) || $currentUser->hasRole([ROLE_ID_SITE_ADMIN], Application::CONTEXT_SITE)) {
            $canDelete = true;
        } else {
            if ($submission->getData('submissionProgress') != 0) {
                $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */
                $assignments = $stageAssignmentDao->getBySubmissionAndRoleId($submission->getId(), ROLE_ID_AUTHOR, WORKFLOW_STAGE_ID_SUBMISSION, $currentUser->getId());
                $assignment = $assignments->next();
                if ($assignment) {
                    $canDelete = true;
                }
            }
        }

        return $canDelete;
    }

    /**
     * Check if a user can edit the publication metadata of a submission
     */
    public function canEditPublication(int $submissionId, int $userId): bool
    {
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */
        $stageAssignments = $stageAssignmentDao->getBySubmissionAndUserIdAndStageId($submissionId, $userId, null)->toArray();
        // Check for permission from stage assignments
        foreach ($stageAssignments as $stageAssignment) {
            if ($stageAssignment->getCanChangeMetadata()) {
                return true;
            }
        }
        // If user has no stage assigments, check if user can edit anyway ie. is manager
        $context = Application::get()->getRequest()->getContext();
        if (count($stageAssignments) == 0 && $this->_canUserAccessUnassignedSubmissions($context->getId(), $userId)) {
            return true;
        }
        // Else deny access
        return false;
    }

    /** @copydoc DAO::insert */
    public function add(Submission $submission, Publication $publication): int
    {
        $submission->stampLastActivity();
        $submission->stampModified();
        if (!$submission->getData('dateSubmitted') && !$submission->getData('submissionProgress')) {
            $submission->setData('dateSubmitted', Core::getCurrentDate());
        }
        if (!$submission->getData('status')) {
            $submission->setData('status', Submission::STATUS_QUEUED);
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

        HookRegistry::call('Submission::add', [$submission]);

        return $submission->getId();
    }

    /** @copydoc DAO::update */
    public function edit(Submission $submission, array $params)
    {
        $newSubmission = Repo::submission()->newDataObject(array_merge($submission->_data, $params));
        $newSubmission->stampLastActivity();
        $newSubmission->stampModified();

        HookRegistry::call('Submission::edit', [$newSubmission, $submission, $params]);

        $this->dao->update($newSubmission);
    }

    /** @copydoc DAO::delete */
    public function delete(Submission $submission)
    {
        HookRegistry::call('Submission::delete::before', [&$submission]);

        $this->dao->delete($submission);

        HookRegistry::call('Submission::delete', [$submission]);
    }

    /**
     * Delete all submissions in a context
     */
    public function deleteByContextId(int $contextId)
    {
        $collector = Repo::submission()->getCollector()->filterByContextIds([$contextId]);
        $submissionIds = Repo::submission()->getIds($collector);
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
     */
    public function updateStatus(Submission $submission, ?int $newStatus = null)
    {
        $status = $submission->getData('status');

        if ($newStatus === null) {
            $newStatus = $this->getStatusByPublications($submission);
        }

        HookRegistry::call('Submission::updateStatus', [&$newStatus, $status, $submission]);

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
        $collector = Repo::submission()->getCollector()->filterByContextIds([$contextId]);
        $submissions = Repo::submission()->getMany($collector);
        while ($submission = $submissions->next()) {
            $publications = (array) $submission->getData('publications');
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
     * Creates and assigns DOIs to all sub-objects if:
     * 1) the suffix pattern can currently be created, and
     * 2) it does not already exist.
     *
     * @return DoiCreationException[]
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
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
        $allowedRoles = $userGroupDao->getNotChangeMetadataEditPermissionRoles();
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
}
