<?php
/**
 * @file classes/submission/Repository.inc.php
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
use Illuminate\Support\Facades\App;
use Illuminate\Support\LazyCollection;
use PKP\core\Core;
use PKP\db\DAORegistry;
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
        return App::make(Collector::class);
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

    /** @copydoc DAO::getByPubId() */
    public function getByPubId(string $pubIdType, string $pubId, $contextId = null): ?Submission
    {
        return $this->dao->getByPubId($pubIdType, $pubId, $contextId);
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
            $userDao = DAORegistry::getDAO('UserDAO'); /** @var UserDAO $userDao */
            $user = $userDao->getById($userId);
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
        if ($reviewAssignment) {
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
            if (isset($props['contextid']) && !$validator->errors()->get('contextid')) {
                $submissionContext = Services::get('context')->get($props['contextid']);
                if (!$submissionContext) {
                    $validator->errors()->add('contextId', __('submission.submit.noContext'));
                }
            }
        });

        if ($validator->fails()) {
            $errors = $this->schemaService->formatValidationErrors($validator->errors(), $this->schemaService->get(PKPSchemaService::SCHEMA_SUBMISSION), $allowedLocales);
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

        HookRegistry::call('Submission::add', [&$submission]);

        return $submission->getId();
    }

    /** @copydoc DAO::update */
    public function edit(Submission $submission, array $params)
    {
        $newSubmission = Repo::submission()->newDataObject(array_merge($submission->_data, $params));
        $newSubmission->stampLastActivity();
        $newSubmission->stampModified();

        HookRegistry::call('Submission::edit', [&$newSubmission, $submission, $params]);

        $this->dao->update($newSubmission);
    }

    /** @copydoc DAO::delete */
    public function delete(Submission $submission)
    {
        HookRegistry::call('Submission::delete::before', [&$submission]);

        $this->dao->delete($submission);

        HookRegistry::call('Submission::delete', [&$submission]);
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
     * Update a submission's status and current publication id
     *
     * Sets the appropriate status on the submission and updates the
     * current publication id, based on all of the submission's
     * publications.
     *
     * Used to update the submission status when publications are
     * published or deleted, or any other actions which may effect
     * the status of the submission.
     */
    public function updateStatus(Submission $submission)
    {
        $status = $newStatus = $submission->getData('status');
        $currentPublicationId = $newCurrentPublicationId = $submission->getData('currentPublicationId');
        $publications = $submission->getData('publications'); /** @var LazyCollection $publications */

        // If there are no publications, we are probably in the process of deleting a submission.
        // To be safe, reset the status and currentPublicationId anyway.
        if (!$publications->count()) {
            $newStatus = $status == Submission::STATUS_DECLINED
                ? Submission::STATUS_DECLINED
                : Submission::STATUS_QUEUED;
            $newCurrentPublicationId = null;
        } else {

            // Get the new current publication after status changes or deletions
            // Use the latest published publication or, failing that, the latest publication
            $newCurrentPublicationId = $publications->reduce(function ($a, $b) {
                return $b->getData('status') === PKPSubmission::STATUS_PUBLISHED && $b->getId() > $a ? $b->getId() : $a;
            }, 0);
            if (!$newCurrentPublicationId) {
                $newCurrentPublicationId = $publications->reduce(function ($a, $b) {
                    return $a > $b->getId() ? $a : $b->getId();
                }, 0);
            }

            // Declined submissions should remain declined even if their
            // publications change
            if ($status !== PKPSubmission::STATUS_DECLINED) {
                $newStatus = PKPSubmission::STATUS_QUEUED;
                foreach ($publications as $publication) {
                    if ($publication->getData('status') === PKPSubmission::STATUS_PUBLISHED) {
                        $newStatus = PKPSubmission::STATUS_PUBLISHED;
                        break;
                    }
                    if ($publication->getData('status') === PKPSubmission::STATUS_SCHEDULED) {
                        $newStatus = PKPSubmission::STATUS_SCHEDULED;
                        continue;
                    }
                }
            }
        }

        HookRegistry::call('Submission::updateStatus', [&$newStatus, $status, $submission]);

        $updateParams = [];
        if ($status !== $newStatus) {
            $updateParams['status'] = $newStatus;
        }
        if ($currentPublicationId !== $newCurrentPublicationId) {
            $updateParams['currentPublicationId'] = $newCurrentPublicationId;
        }
        if (!empty($updateParams)) {
            $this->edit($submission, $updateParams);
        }
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
}
