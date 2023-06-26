<?php

/**
 * @file api/v1/_submissions/PKPBackendSubmissionsHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPBackendSubmissionsHandler
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
use PKP\core\APIResponse;
use PKP\db\DAORegistry;
use PKP\handler\APIHandler;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use Slim\Http\Request as SlimRequest;
use Slim\Http\Response;

abstract class PKPBackendSubmissionsHandler extends APIHandler
{
    /** @var int Max items that can be requested */
    public const MAX_COUNT = 100;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->_handlerPath = '_submissions';
        $this->_endpoints =  [
            'GET' => [
                [
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => [$this, 'getMany'],
                    'roles' => [
                        Role::ROLE_ID_SITE_ADMIN,
                        Role::ROLE_ID_MANAGER,
                    ],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/needsEditor',
                    'handler' => [$this, 'needsEditor'],
                    'roles' => [Role::ROLE_ID_MANAGER],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/assigned',
                    'handler' => [$this, 'assigned'],
                    'roles' => [
                        Role::ROLE_ID_MANAGER,
                        Role::ROLE_ID_SUB_EDITOR,
                        Role::ROLE_ID_ASSISTANT
                    ],
                ]
            ],
            'DELETE' => [
                [
                    'pattern' => $this->getEndpointPattern() . '/{submissionId:\d+}',
                    'handler' => [$this, 'delete'],
                    'roles' => [
                        Role::ROLE_ID_SITE_ADMIN,
                        Role::ROLE_ID_MANAGER,
                        Role::ROLE_ID_AUTHOR,
                    ],
                ],
            ],
        ];
        parent::__construct();
    }

    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new UserRolesRequiredPolicy($request), true);

        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));

        $routeName = $this->getSlimRequest()->getAttribute('route')->getName();
        if (in_array($routeName, ['delete'])) {
            $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));
        }

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Get a collection of submissions
     *
     * @param SLimRequest $slimRequest Slim request object
     * @param APIResponse $response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function getMany(SlimRequest $slimRequest, APIResponse $response, array $args)
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();

        if (!$context) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $queryParams = $slimRequest->getQueryParams();
        $collector = $this->getSubmissionCollector($queryParams);

        // Additional params available for this endpoint
        foreach ($queryParams as $param => $val) {
            switch ($param) {
                case 'assignedTo':
                    $val = array_map('intval', $this->paramToArray($val));
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

        $submissions = $collector->getMany();

        $userGroups = Repo::userGroup()->getCollector()
            ->filterByContextIds([$context->getId()])
            ->getMany();

        /** @var \PKP\submission\GenreDAO $genreDao */
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $genres = $genreDao->getByContextId($context->getId())->toArray();

        return $response->withJson([
            'itemsMax' => $collector->limit(null)->offset(null)->getCount(),
            'items' => Repo::submission()->getSchemaMap()->mapManyToSubmissionsList($submissions, $userGroups, $genres)->values(),
        ], 200);
    }

    /**
     * Get submissions assigned to the current user
     */
    public function assigned(SlimRequest $slimRequest, APIResponse $response, array $args): APIResponse
    {
        $request = Application::get()->getRequest();
        $user = $request->getUser();
        $context = $request->getContext();
        if (!$context) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $collector = $this->getSubmissionCollector($slimRequest->getQueryParams());

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

        return $response->withJson([
            'itemsMax' => $collector->limit(null)->offset(null)->getCount(),
            'items' => Repo::submission()->getSchemaMap()->mapManyToSubmissionsList($submissions, $userGroups, $genres)->values(),
        ], 200);
    }

    /**
     * Delete a submission
     *
     * @param SlimRequest $slimRequest Slim request object
     * @param APIResponse $response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function delete(SlimRequest $slimRequest, APIResponse $response, array $args)
    {
        $request = $this->getRequest();
        $context = $request->getContext();
        $submissionId = (int) $args['submissionId'];
        $submission = Repo::submission()->get($submissionId);

        if (!$submission) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        if ($context->getId() != $submission->getData('contextId')) {
            return $response->withStatus(403)->withJsonError('api.submissions.403.deleteSubmissionOutOfContext');
        }

        if (!Repo::submission()->canCurrentUserDelete($submission)) {
            return $response->withStatus(403)->withJsonError('api.submissions.403.unauthorizedDeleteSubmission');
        }

        Repo::submission()->delete($submission);

        return $response->withJson(true);
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
                    $collector->filterByStatus(array_map('intval', $this->paramToArray($val)));
                    break;

                case 'stageIds':
                    $collector->filterByStageIds(array_map('intval', $this->paramToArray($val)));
                    break;

                case 'categoryIds':
                    $collector->filterByCategoryIds(array_map('intval', $this->paramToArray($val)));
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
