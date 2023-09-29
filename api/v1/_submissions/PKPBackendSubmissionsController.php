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
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PKP\core\PKPRequest;
use PKP\core\PKPBaseController;
use APP\facades\Repo;
use APP\submission\Collector;
use PKP\config\Config;
use PKP\db\DAORegistry;
use PKP\plugins\Hook;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;

abstract class PKPBackendSubmissionsController extends PKPBaseController
{
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
                self::roleAuthorizer(
                    Config::getVar('features', 'enable_new_submission_listing')
                        ? [
                            Role::ROLE_ID_SITE_ADMIN, 
                            Role::ROLE_ID_MANAGER,
                        ] : [
                            Role::ROLE_ID_SITE_ADMIN,
                            Role::ROLE_ID_MANAGER,
                            Role::ROLE_ID_SUB_EDITOR,
                            Role::ROLE_ID_AUTHOR,
                            Role::ROLE_ID_REVIEWER,
                            Role::ROLE_ID_ASSISTANT,
                        ]
                ),
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
        
        if (Config::getVar('features', 'enable_new_submission_listing')) {
            
            Route::get('needsEditor', $this->needsEditor(...))
                ->name('_submission.needsEditor')
                ->middleware([
                    self::roleAuthorizer([
                        Role::ROLE_ID_MANAGER,
                    ]),
                ]);
            
            Route::get('assigned', $this->assigned(...))
                ->name('_submission.assigned')
                ->middleware([
                    self::roleAuthorizer([
                        Role::ROLE_ID_MANAGER,
                        Role::ROLE_ID_SUB_EDITOR,
                        Role::ROLE_ID_ASSISTANT,
                    ]),
                ]);
        }
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
                    $val = array_map('intval', paramToArray($val));
                    if ($val == [\PKP\submission\Collector::UNASSIGNED]) {
                        $val = array_shift($val);
                    }
                    $collector->assignedTo($val);
                    break;

                case 'isIncomplete':
                    $collector->filterByIncomplete(true);
                    break;
            }
        }

        /**
         * FIXME: Clean up before release pkp/pkp-lib#7495.
         * In new submission lists this endpoint is dedicated to retrieve all submissions only by admins and managers
         */
        if (!Config::getVar('features', 'enable_new_submission_listing')) {

            // Anyone not a manager or site admin can only access their assigned submissions
            $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
            $canAccessUnassignedSubmission = !empty(array_intersect([Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER], $userRoles));
            Hook::run('API::_submissions::params', [$collector, $illuminateRequest]);
            if (!$canAccessUnassignedSubmission) {
                if (!is_array($collector->assignedTo)) {
                    $collector->assignedTo([$currentUser->getId()]);
                } elseif ($collector->assignedTo != [$currentUser->getId()]) {
                    return response()->json([
                        'error' => __('api.submissions.403.requestedOthersUnpublishedSubmissions'),
                    ], Response::HTTP_FORBIDDEN);
                }
            }
        }

        $submissions = $collector->getMany();

        $userGroups = Repo::userGroup()->getCollector()
            ->filterByContextIds([$context->getId()])
            ->getMany();

        /** @var \PKP\submission\GenreDAO $genreDao */
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $genres = $genreDao->getByContextId($context->getId())->toArray();

        return response()->json([
            'itemsMax' => $collector->limit(null)->offset(null)->getCount(),
            'items' => Repo::submission()->getSchemaMap()->mapManyToSubmissionsList($submissions, $userGroups, $genres)->values(),
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

        $submissions = $collector
            ->filterByContextIds([$context->getId()])
            ->assignedTo([$user->getId()])
            ->getMany();

        $userGroups = Repo::userGroup()->getCollector()
            ->filterByContextIds([$context->getId()])
            ->getMany();

        /** @var \PKP\submission\GenreDAO $genreDao */
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $genres = $genreDao->getByContextId($context->getId())->toArray();

        return response()->json([
            'itemsMax' => $collector->limit(null)->offset(null)->getCount(),
            'items' => Repo::submission()->getSchemaMap()->mapManyToSubmissionsList($submissions, $userGroups, $genres)->values(),
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
                    $collector->filterByStatus(array_map('intval', paramToArray($val)));
                    break;

                case 'stageIds':
                    $collector->filterByStageIds(array_map('intval', paramToArray($val)));
                    break;

                case 'categoryIds':
                    $collector->filterByCategoryIds(array_map('intval', paramToArray($val)));
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
                    $collector->filterByOverdue(true);
                    break;
            }
        }

        return $collector;
    }
}
