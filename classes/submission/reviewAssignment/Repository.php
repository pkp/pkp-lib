<?php
/**
 * @file classes/submission/reviewAssignment/Repository.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A repository to find and manage review assignment.
 */

namespace PKP\submission\reviewAssignment;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use Illuminate\Support\Collection;
use PKP\context\Context;
use PKP\db\DAORegistry;
use PKP\notification\Notification;
use PKP\plugins\Hook;
use PKP\reviewForm\ReviewFormResponseDAO;
use PKP\security\Role;
use PKP\security\RoleDAO;
use PKP\services\PKPSchemaService;
use PKP\submission\ReviewFilesDAO;
use PKP\submission\reviewRound\ReviewRoundDAO;
use PKP\validation\ValidatorFactory;

class Repository
{
    public DAO $dao;

    /** @var string $schemaMap The name of the class to map this entity to its schema */
    public string $schemaMap = maps\Schema::class;

    protected Request $request;

    /** @var PKPSchemaService<ReviewAssignment> $schemaService */
    protected PKPSchemaService $schemaService;

    public function __construct(DAO $dao, Request $request, PKPSchemaService $schemaService)
    {
        $this->dao = $dao;
        $this->request = $request;
        $this->schemaService = $schemaService;
    }

    /** @copydoc DAO::newDataObject() */
    public function newDataObject(array $params = []): ReviewAssignment
    {
        $object = $this->dao->newDataObject();
        if (!empty($params)) {
            $object->setAllData($params);
        }
        return $object;
    }

    /** @copydoc DAO::get() */
    public function get(int $id, ?int $submissionId = null): ?ReviewAssignment
    {
        return $this->dao->get($id, $submissionId);
    }

    /** @copydoc DAO::exists() */
    public function exists(int $id, ?int $submissionId = null): bool
    {
        return $this->dao->exists($id, $submissionId);
    }

    /** @copydoc DAO::getCollector() */
    public function getCollector(): Collector
    {
        return app(Collector::class);
    }

    /**
     * Get an instance of the map class for mapping
     * announcements to their schema
     */
    public function getSchemaMap(): maps\Schema
    {
        return app('maps')->withExtensions($this->schemaMap);
    }

    /**
     * Validate properties for a category
     *
     * Perform validation checks on data used to add or edit a review assignment.
     *
     * @param array $props A key/value array with the new data to validate
     *
     * @return array A key/value array with validation errors. Empty if no errors
     *
     * @hook ReviewAssignment::validate [&$errors, $object, $props, $allowedLocales, $primaryLocale]
     */
    public function validate(?ReviewAssignment $object, array $props, Context $context): array
    {
        $primaryLocale = $context->getData('primaryLocale');
        $allowedLocales = $context->getData('supportedFormLocales');

        $validator = ValidatorFactory::make(
            $props,
            $this->schemaService->getValidationRules($this->dao->schema, $allowedLocales),
            []
        );

        // Check required fields
        ValidatorFactory::required(
            $validator,
            $object,
            $this->schemaService->getRequiredProps($this->dao->schema),
            $this->schemaService->getMultilingualProps($this->dao->schema),
            $allowedLocales,
            $primaryLocale
        );

        // Check if submission exists
        if (isset($props['submissionId'])) {
            $validator->after(function ($validator) use ($props) {
                if (!$validator->errors()->get('submissionId')) {
                    $submission = Repo::submission()->get($props['submissionId']);
                    if (!$submission) {
                        $validator->errors()->add('submissionId', __('api.reviews.assignments.invalidSubmission'));
                    }
                }
            });
        }

        // Check if reviewer exists
        if (isset($props['reviewerId'])) {
            $validator->after(function ($validator) use ($props, $context) {
                if (!$validator->errors()->get('reviewerId')) {
                    $reviewer = Repo::user()->get($props['reviewerId']);
                    if (!$reviewer) {
                        $validator->errors()->add('reviewerId', __('api.reviews.assignments.invalidReviewer'));
                    }
                    $roleDao = DAORegistry::getDAO('RoleDAO'); /** @var RoleDAO $roleDao */
                    $isReviewer = $roleDao->userHasRole($context->getId(), $reviewer->getId(), Role::ROLE_ID_REVIEWER);
                    if (!$isReviewer) {
                        $validator->errors()->add('reviewerId', __('api.reviews.assignments.invalidReviewer'));
                    }
                }
            });
        }

        // Check for input from disallowed locales
        ValidatorFactory::allowedLocales($validator, $this->schemaService->getMultilingualProps($this->dao->schema), $allowedLocales);

        $errors = [];

        if ($validator->fails()) {
            $errors = $this->schemaService->formatValidationErrors($validator->errors());
        }

        Hook::run('ReviewAssignment::validate', [&$errors, $object, $props, $allowedLocales, $primaryLocale]);

        return $errors;
    }

    /**
     * Add a new review assignment
     *
     * @hook ReviewAssignment::add [[$reviewAssignment]]
     */
    public function add(ReviewAssignment $reviewAssignment): int
    {
        $id = $this->dao->insert($reviewAssignment);
        $reviewAssignment->stampModified();
        Hook::call('ReviewAssignment::add', [$reviewAssignment]);
        $this->updateReviewRoundStatus($reviewAssignment);

        return $id;
    }

    /**
     * Edit a review assignment
     *
     * @hook ReviewAssignment::edit [[$newReviewAssignment, $reviewAssignment, $params]]
     */
    public function edit(ReviewAssignment $reviewAssignment, array $params)
    {
        $newReviewAssignment = clone $reviewAssignment;
        $newReviewAssignment->setAllData(array_merge($newReviewAssignment->_data, $params));
        $newReviewAssignment->stampModified();

        Hook::call('ReviewAssignment::edit', [$newReviewAssignment, $reviewAssignment, $params]);

        $this->dao->update($newReviewAssignment);
        $this->updateReviewRoundStatus($newReviewAssignment);
    }

    /**
     * Delete a review assignment
     *
     * @hook ReviewAssignment::delete::before [[$reviewAssignment]]
     */
    public function delete(ReviewAssignment $reviewAssignment)
    {
        Hook::call('ReviewAssignment::delete::before', [$reviewAssignment]);

        $reviewFormResponseDao = DAORegistry::getDAO('ReviewFormResponseDAO'); /** @var ReviewFormResponseDAO $reviewFormResponseDao */
        $reviewFormResponseDao->deleteByReviewId($reviewAssignment->getId());

        $reviewFilesDao = DAORegistry::getDAO('ReviewFilesDAO'); /** @var ReviewFilesDAO $reviewFilesDao */
        $reviewFilesDao->revokeByReviewId($reviewAssignment->getId());

        Notification::withAssoc(Application::ASSOC_TYPE_REVIEW_ASSIGNMENT, $reviewAssignment->getId())->delete();

        $this->dao->delete($reviewAssignment);

        $this->updateReviewRoundStatus($reviewAssignment);

        Hook::call('ReviewAssignment::delete', [$reviewAssignment]);
    }

    /**
     * Delete a collection of announcements
     */
    public function deleteMany(Collector $collector)
    {
        foreach ($collector->getMany() as $reviewAssignment) {
            $this->delete($reviewAssignment);
        }
    }

    /**
     * Delete all review assignments for a given context ID.
     */
    public function deleteByContextId(int $contextId): void
    {
        // using reviewAssignmentCollector to fetch ids of all review assignments for the context
        $reviewAssignmentCollector = $this->getCollector();
        $reviewAssignmentCollector->filterByContextIds([$contextId]);
        $reviewAssignmentIds = $reviewAssignmentCollector->getIds();

        foreach ($reviewAssignmentIds as $reviewAssignmentId) {
            $this->dao->deleteById($reviewAssignmentId);
        }

        // delete review rounds associated with this context
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
        $reviewRoundDao->deleteByContextId($contextId);
    }

    /**
     * Return the review methods translation keys.
     */
    public function getReviewMethodsTranslationKeys(): array
    {
        return [
            ReviewAssignment::SUBMISSION_REVIEW_METHOD_DOUBLEANONYMOUS => 'editor.submissionReview.doubleAnonymous',
            ReviewAssignment::SUBMISSION_REVIEW_METHOD_ANONYMOUS => 'editor.submissionReview.anonymous',
            ReviewAssignment::SUBMISSION_REVIEW_METHOD_OPEN => 'editor.submissionReview.open',
        ];
    }

    /**
     * Update the status of the review round an assignment is attached to. This
     * should be fired whenever a reviewer assignment is modified.
     */
    protected function updateReviewRoundStatus(ReviewAssignment $reviewAssignment): bool
    {
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
        $reviewRound = $reviewRoundDao->getReviewRound(
            $reviewAssignment->getSubmissionId(),
            $reviewAssignment->getStageId(),
            $reviewAssignment->getRound()
        );

        // Review round may not exist if submission is being deleted
        if ($reviewRound) {
            $reviewRoundDao->updateStatus($reviewRound);
            return true;
        }

        return false;
    }

    /**
     * @copydoc DAO::getExternalReviewerIdsByCompletedYear()
     *
     * @return Collection<int,int>
     */
    public function getExternalReviewerIdsByCompletedYear(int $contextId, string $year): Collection
    {
        return $this->dao->getExternalReviewerIdsByCompletedYear($contextId, $year);
    }
}
