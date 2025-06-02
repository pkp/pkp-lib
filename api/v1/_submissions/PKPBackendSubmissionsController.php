<?php

/**
 * @file api/v1/_submissions/PKPBackendSubmissionsController.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPBackendSubmissionsController
 *
 * @ingroup api_v1_backend
 *
 * @brief Handle API requests for backend operations.
 *
 */

namespace PKP\API\v1\_submissions;

use APP\core\Application;
use APP\facades\Repo;
use APP\submission\Collector;
use APP\submission\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\API\v1\submissions\AnonymizeData;
use PKP\context\Context;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\plugins\Hook;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use PKP\submission\DashboardView;
use PKP\submission\PKPSubmission;
use PKP\userGroup\UserGroup;

abstract class PKPBackendSubmissionsController extends PKPBaseController
{
    use AnonymizeData;

    /** @var int Max items that can be requested */
    public const MAX_COUNT = 100;

    /**
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return '_submissions';
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getRouteGroupMiddleware()
     */
    public function getRouteGroupMiddleware(): array
    {
        return [
            'has.user',
            'has.context',
        ];
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getGroupRoutes()
     */
    public function getGroupRoutes(): void
    {
        Route::get('', $this->getMany(...))
            ->name('_submission.getMany')
            ->middleware([
                self::roleAuthorizer([Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER]),
            ]);

        Route::delete('', $this->bulkDeleteIncompleteSubmissions(...))
            ->name('_submission.incomplete.delete')
            ->middleware([
                self::roleAuthorizer([
                    Role::ROLE_ID_SITE_ADMIN,
                    Role::ROLE_ID_MANAGER,
                    Role::ROLE_ID_AUTHOR,
                ]),
            ]);

        Route::delete('{submissionId}', $this->delete(...))
            ->name('_submission.delete')
            ->middleware([
                self::roleAuthorizer([
                    Role::ROLE_ID_SITE_ADMIN,
                    Role::ROLE_ID_MANAGER,
                    Role::ROLE_ID_AUTHOR,
                ]),
            ])
            ->whereNumber('submissionId');

        Route::get('assigned', $this->assigned(...))
            ->name('_submission.assigned')
            ->middleware([
                self::roleAuthorizer([
                    Role::ROLE_ID_MANAGER,
                    Role::ROLE_ID_SUB_EDITOR,
                    Role::ROLE_ID_ASSISTANT,
                    Role::ROLE_ID_AUTHOR,
                ]),
            ]);

        Route::get('reviews', $this->reviews(...))
            ->name('_submission.reviews')
            ->middleware([
                self::roleAuthorizer([
                    Role::ROLE_ID_MANAGER,
                    Role::ROLE_ID_SUB_EDITOR,
                    Role::ROLE_ID_ASSISTANT,
                    Role::ROLE_ID_AUTHOR,
                ])
            ]);

        Route::get('viewsCount', $this->getViewsCount(...))
            ->name('_submission.getViewsCount')
            ->middleware([
                self::roleAuthorizer([
                    Role::ROLE_ID_MANAGER,
                    Role::ROLE_ID_SUB_EDITOR,
                    Role::ROLE_ID_ASSISTANT,
                    Role::ROLE_ID_AUTHOR,
                    Role::ROLE_ID_REVIEWER,
                ])
            ]);

        Route::get('reviewerAssignments', $this->getReviewAssignments(...))
            ->name('_submission.getReviewAssignments')
            ->middleware([
                self::roleAuthorizer([
                    Role::ROLE_ID_REVIEWER,
                ])
            ]);
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::authorize()
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        $this->addPolicy(new UserRolesRequiredPolicy($request), true);

        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));

        $illuminateRequest = $args[0]; /** @var \Illuminate\Http\Request $illuminateRequest */

        if (in_array(static::getRouteActionName($illuminateRequest), ['delete'])) {
            $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));
        }

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Get a collection of submissions
     *
     * @hook API::_submissions::params [$collector, $illuminateRequest]
     */
    public function getMany(Request $illuminateRequest): JsonResponse
    {
        $request = Application::get()->getRequest();
        $currentUser = $request->getUser();
        $context = $request->getContext();

        if (!$context) {
            return response()->json([
                'error' => __('api.404.resourceNotFound')
            ], Response::HTTP_NOT_FOUND);
        }

        $queryParams = $illuminateRequest->query();

        $collector = $this->getSubmissionCollector($queryParams);

        foreach ($queryParams as $param => $val) {
            switch ($param) {
                case 'assignedTo':
                    // this is specifically used for assigned editors
                    $collector->assignedTo(array_map(intval(...), paramToArray($val)), [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT]);
                    break;
            }
        }

        $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);

        $submissions = $collector->getMany();

        $contextId = $context->getId();

        $userGroups = UserGroup::withContextIds($contextId)->cursor();


        $genres = Repo::genre()->getByContextId($context->getId())->all();

        return response()->json([
            'itemsMax' => $collector->getCount(),
            'items' => Repo::submission()->getSchemaMap()->mapManyToSubmissionsList($submissions, $userGroups, $genres, $userRoles)->values(),
        ], Response::HTTP_OK);
    }

    /**
     * Get submissions assigned to the current user
     */
    public function assigned(Request $illuminateRequest): JsonResponse
    {
        $request = Application::get()->getRequest();
        $user = $request->getUser();
        $context = $request->getContext();
        if (!$context) {
            return response()->json([
                'error' => __('api.404.resourceNotFound')
            ], Response::HTTP_NOT_FOUND);
        }

        $collector = $this->getSubmissionCollector($illuminateRequest->query());
        $queryParams = $illuminateRequest->query();

        $submissions = $collector
            ->filterByContextIds([$context->getId()])
            ->assignedTo([$user->getId()], $queryParams['assignedWithRoles'] ?? null)
            ->getMany();

        $contextId = $context->getId();
        $userGroups = UserGroup::withContextIds($contextId)->cursor();

        $genres = Repo::genre()->getByContextId($context->getId())->all();

        return response()->json([
            'itemsMax' => $collector->getCount(),
            'items' => Repo::submission()->getSchemaMap()->mapManyToSubmissionsList(
                $submissions,
                $userGroups,
                $genres,
                $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES),
                $this->anonymizeReviews($submissions)
            )->values(),
        ], Response::HTTP_OK);
    }

    /**
     * Get submission undergoing the review
     */
    public function reviews(Request $illuminateRequest): JsonResponse
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        if (!$context) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }
        $currentUser = $request->getUser();
        $queryParams = $illuminateRequest->query();

        $collector = $this->getSubmissionCollector($queryParams);
        $collector
            ->filterByContextIds([$context->getId()])
            ->filterByStatus([PKPSubmission::STATUS_QUEUED])
            ->filterByStageIds([WORKFLOW_STAGE_ID_INTERNAL_REVIEW, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW]);

        // limit results depending on a role

        if (!$this->canAccessAllSubmissions()) {
            $assignedWithRoles = array_map(
                intval(...),
                paramToArray($queryParams['assignedWithRoles'] ?? [])
            );

            $collector->assignedTo([$currentUser->getId()], $assignedWithRoles);
        }

        foreach ($queryParams as $param => $val) {
            switch ($param) {
                case 'needsReviews':
                    $collector->filterByNumReviewsConfirmedLimit(
                        $context->getNumReviewsPerSubmission() == Context::REVIEWS_DEFAULT_COUNT ?
                            Context::REVIEWS_REQUIRED_COUNT :
                            $context->getNumReviewsPerSubmission()
                    );
                    break;
                case 'awaitingReviews':
                    $collector->filterByAwaitingReviews(true);
                    break;
                case 'reviewsSubmitted':
                    $collector->filterByReviewsSubmitted(true);
                    break;
                case 'reviewsOverdue':
                    $collector->filterByReviewsOverdue(true);
                    break;
                case 'revisionsRequested':
                    $collector->filterByRevisionsRequested(true);
                    break;
                case 'revisionsSubmitted':
                    $collector->filterByRevisionsSubmitted(true);
                    break;
            }
        }

        $submissions = $collector->getMany();

        $contextId = $context->getId();
        $userGroups = UserGroup::withContextIds($contextId)->cursor();

        $genres = Repo::genre()->getByContextId($context->getId())->all();


        return response()->json([
            'itemsMax' => $collector->getCount(),
            'items' => Repo::submission()->getSchemaMap()->mapManyToSubmissionsList(
                $submissions,
                $userGroups,
                $genres,
                $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES)
            )->values(),
        ], Response::HTTP_OK);
    }

    /**
     * Get a number of the submissions/review assignments for each view depending on a user role
     */
    public function getViewsCount(Request $illuminateRequest): JsonResponse
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        if (!$context) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }
        $currentUser = $request->getUser();
        $queryParams = $illuminateRequest->query();
        $assignedWithRoles = array_map(
            intval(...),
            paramToArray($queryParams['assignedWithRoles'] ?? [])
        );

        $dashboardViews = Repo::submission()->getDashboardViews($context, $currentUser, $assignedWithRoles, true);

        return response()->json(
            $dashboardViews->map(fn (DashboardView $view) => $view->getCount()),
            Response::HTTP_OK
        );
    }

    /**
     * Get all reviewer's assignments
     */
    public function getReviewAssignments(Request $illuminateRequest): JsonResponse
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        if (!$context) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }
        $currentUser = $request->getUser();
        $collector = Repo::reviewAssignment()->getCollector()
            ->filterByReviewerIds([$currentUser->getId()], true)
            ->filterByContextIds([$context->getId()]);

        foreach ($illuminateRequest->query() as $param => $val) {
            switch ($param) {
                case 'actionRequired':
                    $collector->filterByActionRequiredByReviewer(true);
                    break;
                case 'active':
                    $collector->filterByActive(true);
                    break;
                case 'archived':
                    $collector->filterByIsArchived(true);
                    break;
                case 'declined':
                    $collector->filterByDeclined(true);
                    break;
                case 'completed':
                    $collector->filterByCompleted(true);
                    break;
                case 'published':
                    $collector->filterByPublished(true);
                    break;
            }
        }

        $reviewAssignments = $collector->getMany();

        return response()->json([
            'itemsMax' => $collector->getCount(),
            'items' => Repo::reviewAssignment()->getSchemaMap()->mapMany($reviewAssignments)->values(),
        ], Response::HTTP_OK);
    }

    /**
     * Delete a submission
     */
    public function delete(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $context = $request->getContext();
        $submissionId = (int) $illuminateRequest->route('submissionId');
        $submission = Repo::submission()->get($submissionId);

        if (!$submission) {
            return response()->json([
                'error' => __('api.404.resourceNotFound')
            ], Response::HTTP_NOT_FOUND);
        }

        if ($context->getId() != $submission->getData('contextId')) {
            return response()->json([
                'error' => __('api.submissions.403.deleteSubmissionOutOfContext'),
            ], Response::HTTP_FORBIDDEN);
        }

        if (!Repo::submission()->canCurrentUserDelete($submission)) {
            return response()->json([
                'error' => __('api.submissions.403.unauthorizedDeleteSubmission'),
            ], Response::HTTP_FORBIDDEN);
        }

        Repo::submission()->delete($submission);

        return response()->json([], Response::HTTP_OK);
    }

    /**
     * Delete a list of incomplete submissions
     */
    public function bulkDeleteIncompleteSubmissions(Request $illuminateRequest): JsonResponse
    {
        $submissionIdsRaw = paramToArray($illuminateRequest->query('ids') ?? []);

        if (empty($submissionIdsRaw)) {
            return response()->json([
                'error' => __('api.submission.400.missingQueryParam'),
            ], Response::HTTP_BAD_REQUEST);
        }

        $submissionIds = [];

        foreach ($submissionIdsRaw as $id) {
            $integerId = intval($id);

            if (!$integerId) {
                return response()->json([
                    'error' => __('api.submission.400.invalidId', ['id' => $id])
                ], Response::HTTP_BAD_REQUEST);
            }

            $submissionIds[] = $id;
        }

        $collector = $this->getSubmissionCollector($illuminateRequest->query())
            ->filterBySubmissionIds($submissionIds)
            ->filterByIncomplete(true);

        $request = Application::get()->getRequest();
        $context = $this->getRequest()->getContext();
        $user = $request->getUser();

        if ($user->hasRole([Role::ROLE_ID_AUTHOR], $context->getId())) {
            $userId = $request->getUser()->getId();
            $collector->assignedTo([$userId]);
        }

        $submissions = $collector->getMany()->all();

        $submissionIdsFound = array_map(fn (Submission $submission) => $submission->getData('id'), $submissions);

        if (array_diff($submissionIds, $submissionIdsFound)) {
            return response()->json([
                'error' => __('api.404.resourceNotFound')
            ], Response::HTTP_NOT_FOUND);
        }


        foreach ($submissions as $submission) {
            if ($context->getId() != $submission->getData('contextId')) {
                return response()->json([
                    'error' => __('api.submissions.403.deleteSubmissionOutOfContext'),
                ], Response::HTTP_FORBIDDEN);
            }

            if (!Repo::submission()->canCurrentUserDelete($submission)) {
                return response()->json([
                    'error' => __('api.submissions.403.unauthorizedDeleteSubmission'),
                ], Response::HTTP_FORBIDDEN);
            }
        }

        foreach ($submissions as $submission) {
            Repo::submission()->delete($submission);
        }

        return response()->json([], Response::HTTP_OK);
    }

    /**
     * Configure a submission Collector based on the query params
     */
    protected function getSubmissionCollector(array $queryParams): Collector
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();

        $collector = Repo::submission()->getCollector()
            ->filterByContextIds([$context->getId()])
            ->limit(30)
            ->offset(0);

        foreach ($queryParams as $param => $val) {
            switch ($param) {
                case 'orderBy':
                    if (in_array($val, [
                        $collector::ORDERBY_DATE_PUBLISHED,
                        $collector::ORDERBY_DATE_SUBMITTED,
                        $collector::ORDERBY_ID,
                        $collector::ORDERBY_LAST_ACTIVITY,
                        $collector::ORDERBY_LAST_MODIFIED,
                        $collector::ORDERBY_SEQUENCE,
                        $collector::ORDERBY_TITLE,
                    ])) {
                        $direction = isset($queryParams['orderDirection']) && $queryParams['orderDirection'] === $collector::ORDER_DIR_ASC
                            ? $collector::ORDER_DIR_ASC
                            : $collector::ORDER_DIR_DESC;
                        $collector->orderBy($val, $direction);
                    }
                    break;

                case 'status':
                    $collector->filterByStatus(array_map(intval(...), paramToArray($val)));
                    break;

                case 'stageIds':
                    $collector->filterByStageIds(array_map(intval(...), paramToArray($val)));
                    break;

                case 'categoryIds':
                    $collector->filterByCategoryIds(array_map(intval(...), paramToArray($val)));
                    break;

                case 'daysInactive':
                    $collector->filterByDaysInactive((int) $val);
                    break;

                case 'offset':
                    $collector->offset((int) $val);
                    break;

                case 'searchPhrase':
                    $collector->searchPhrase($val);
                    break;

                case 'count':
                    $collector->limit(min(self::MAX_COUNT, (int) $val));
                    break;

                case 'isOverdue':
                    $collector->filterByReviewsOverdue(true);
                    break;

                case 'isIncomplete':
                    $collector->filterByIncomplete(true);
                    break;
                case 'isUnassigned':
                    $collector->filterByisUnassigned(true);
                    break;
            }
        }

        return $collector;
    }

    protected function canAccessAllSubmissions(): bool
    {
        $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
        return !empty(array_intersect([Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER], $userRoles));
    }
}
