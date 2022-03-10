<?php

/**
 * @file api/v1/users/PKPUserHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPUserHandler
 * @ingroup api_v1_users
 *
 * @brief Base class to handle API requests for user operations.
 *
 */

use APP\facades\Repo;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Str;
use PKP\facades\Locale;
use PKP\handler\APIHandler;
use PKP\security\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends BaseController
{
    use AuthorizesRequests;
    use DispatchesJobs;
    use ValidatesRequests;

    /**
     * Get a collection of users
     */
    public function getMany(Request $request): JsonResponse
    {
        $queryStrings = $request->query(null);
        $params = $this->processAllowedParams(
            $queryStrings,
            [
                'assignedToCategory',
                'assignedToSection',
                'assignedToSubmission',
                'assignedToSubmissionStage',
                'count',
                'offset',
                'orderBy',
                'orderDirection',
                'roleIds',
                'searchPhrase',
                'status',
            ]
        );

        $params['contextId'] = $request->attributes->get('pkpContext')->getId();

        $collector = Repo::user()->getCollector();

        // Convert from $params array to what the Collector expects
        $orderBy = null;
        switch ($params['orderBy'] ?? 'id') {
            case 'id': $orderBy = $collector::ORDERBY_ID; break;
            case 'givenName': $orderBy = $collector::ORDERBY_GIVENNAME; break;
            case 'familyName': $orderBy = $collector::ORDERBY_FAMILYNAME; break;
            default: throw new Exception('Unknown orderBy specified');
        }

        $orderDirection = null;
        switch ($params['orderDirection'] ?? 'ASC') {
            case 'ASC': $orderDirection = $collector::ORDER_DIR_ASC; break;
            case 'DESC': $orderDirection = $collector::ORDER_DIR_DESC; break;
            default: throw new Exception('Unknown orderDirection specified');
        }

        $collector->assignedTo($params['assignedToSubmission'] ?? null, $params['assignedToSubmissionStage'] ?? null)
            ->assignedToSectionIds(isset($params['assignedToSection']) ? [$params['assignedToSection']] : null)
            ->assignedToCategoryIds(isset($params['assignedToCategory']) ? [$params['assignedToCategory']] : null)
            ->filterByRoleIds($params['roleIds'] ?? null)
            ->searchPhrase($params['searchPhrase'] ?? null)
            ->orderBy($orderBy, $orderDirection, [Locale::getLocale(), Locale::getPrimaryLocale()])
            ->limit($params['count'] ?? null)
            ->offset($params['offset'] ?? null)
            ->filterByStatus($params['status'] ?? $collector::STATUS_ALL);

        $users = Repo::user()->getMany($collector);

        $map = Repo::user()->getSchemaMap();
        $items = [];
        foreach ($users as $user) {
            $items[] = $map->summarize($user);
        }

        return new JsonResponse(
            [
                'itemsMax' => Repo::user()->getCount($collector->limit(null)->offset(null)),
                'items' => $items,
            ],
            Response::HTTP_OK
        );
    }
    /**
     * Get a collection of reviewers
     */
    public function getReviewers(Request $request): JsonResponse
    {
        $queryStrings = $request->query(null);
        $params = $this->processAllowedParams(
            $queryStrings,
            [
                'averageCompletion',
                'count',
                'daysSinceLastAssignment',
                'offset',
                'orderBy',
                'orderDirection',
                'reviewerRating',
                'reviewsActive',
                'reviewsCompleted',
                'reviewStage',
                'searchPhrase',
                'status',
            ]
        );

        $contextId = $request->attributes->get('pkpContext')->getId();

        $collector = Repo::user()->getCollector()
            ->filterByContextIds([$contextId])
            ->includeReviewerData()
            ->filterByRoleIds([Role::ROLE_ID_REVIEWER])
            ->filterByWorkflowStageIds([$params['reviewStage']])
            ->searchPhrase($params['searchPhrase'] ?? null)
            ->filterByReviewerRating($params['reviewerRating'] ?? null)
            ->filterByReviewsCompleted($params['reviewsCompleted'][0] ?? null)
            ->filterByReviewsActive(...($params['reviewsActive'] ?? []))
            ->filterByDaysSinceLastAssignment(...($params['daysSinceLastAssignment'] ?? []))
            ->filterByAverageCompletion($params['averageCompletion'][0] ?? null)
            ->limit($params['count'] ?? null)
            ->offset($params['offset'] ?? null);
        $usersCollection = Repo::user()->getMany($collector);
        $items = [];
        $map = Repo::user()->getSchemaMap();
        foreach ($usersCollection as $user) {
            $items[] = $map->summarizeReviewer($user);
        }

        return new JsonResponse(
            [
                'itemsMax' => Repo::user()->getCount($collector->limit(null)->offset(null)),
                'items' => $items,
            ],
            Response::HTTP_OK
        );
    }

    /**
     * Retrieve the user report
     *
     * @param \Illuminate\Http\Request $request Laravel Request
     *
     * @return StreamedResponse|JsonResponse
     */
    public function getReport(Request $request): StreamedResponse|JsonResponse
    {
        $queryStrings = $request->query(null);

        $params = ['contextId' => [$request->attributes->get('pkpContext')->getId()]];

        foreach ($queryStrings as $param => $value) {
            if ($param === 'userGroupIds') {
                if (is_string($value) && strpos($value, ',') > -1) {
                    $value = explode(',', $value);
                } elseif (!is_array($value)) {
                    $value = [$value];
                }

                $params[$param] = array_map('intval', $value);
                continue;
            }

            if ($param === 'mappings') {
                if (is_string($value) && strpos($value, ',') > -1) {
                    $value = explode(',', $value);
                } elseif (!is_array($value)) {
                    $value = [$value];
                }
                $params[$param] = $value;

                continue;
            }
        }

        $response = new StreamedResponse(
            null,
            Response::HTTP_OK,
        );

        $response->setCallback(function () use ($params) {
            $handle = fopen('php://output', 'w+');
            $report = Repo::user()->getReport($params);
            $report->serialize($handle);
            fclose($handle);
        });

        $name = 'user-report-' . date('Y-m-d') . '.csv';

        $response->headers->set('Content-Type', 'application/force-download');
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(
                'attachment',
                $name,
                str_replace('%', '', Str::ascii($name))
            )
        );

        return $response;
    }

    /**
     * Get a single user
     */
    public function getUser(Request $request): JsonResponse
    {
        $userId = $request->route('userId', null);

        $user = Repo::user()->get($userId);

        if (!$user) {
            throw new InvalidArgumentException(
                'api.404.resourceNotFound',
                Response::HTTP_NOT_FOUND
            );
        }

        return new JsonResponse(
            Repo::user()->getSchemaMap()->map($user),
            Response::HTTP_OK
        );
    }

    /**
     * Convert the query params passed to the end point. Exclude unsupported
     * params and coerce the type of those passed.
     *
     * @param array $params Key/value of request params
     * @param array $allowedKeys The param keys which should be processed and returned
     *
     */
    protected function processAllowedParams(
        array $params,
        array $allowedKeys
    ): array {
        // Merge query params over default params
        $defaultParams = [
            'count' => 20,
            'offset' => 0,
        ];

        $requestParams = array_merge($defaultParams, $params);

        // Process query params to format incoming data as needed
        $returnParams = [];
        foreach ($requestParams as $param => $val) {
            if (!in_array($param, $allowedKeys)) {
                continue;
            }
            switch ($param) {
                case 'orderBy':
                    if (in_array($val, ['id', 'familyName', 'givenName'])) {
                        $returnParams[$param] = $val;
                    }
                    break;

                case 'orderDirection':
                    $returnParams[$param] = $val === 'ASC' ? $val : 'DESC';
                    break;

                case 'status':
                    if (in_array($val, ['all', 'active', 'disabled'])) {
                        $returnParams[$param] = $val;
                    }
                    break;

                // Always convert roleIds to array
                case 'reviewerIds':
                case 'roleIds':
                    if (is_string($val)) {
                        $val = explode(',', $val);
                    } elseif (!is_array($val)) {
                        $val = [$val];
                    }
                    $returnParams[$param] = array_map('intval', $val);
                    break;
                case 'assignedToCategory':
                case 'assignedToSection':
                case 'assignedToSubmissionStage':
                case 'assignedToSubmission':
                case 'reviewerRating':
                case 'reviewStage':
                case 'offset':
                case 'searchPhrase':
                    $returnParams[$param] = trim($val);
                    break;

                case 'reviewsCompleted':
                case 'reviewsActive':
                case 'daysSinceLastAssignment':
                case 'averageCompletion':
                    if (is_array($val)) {
                        $val = array_map('intval', $val);
                    } elseif (strpos($val, '-') !== false) {
                        $val = array_map('intval', explode('-', $val));
                    } else {
                        $val = [(int) $val];
                    }
                    $returnParams[$param] = $val;
                    break;

                // Enforce a maximum count per request
                case 'count':
                    $returnParams[$param] = min(100, (int) $val);
                    break;
            }
        }

        return $returnParams;
    }
}

class PKPUserHandler extends APIHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->_handlerPath = 'users';

        $roles = implode(',', [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR]);

        app('router')->group(
            [
                'prefix' => $this->getEndpointPattern(),
                'middleware' => [
                    'auth',
                    'needs.context',
                    'match.roles:' . $roles,
                ],
            ],
            function () {
                app('router')
                    ->name('getUser')
                    ->get('{userId}', [UserController::class, 'getUser']);

                app('router')
                    ->name('getManyUsers')
                    ->get('', [UserController::class, 'getMany']);

                app('router')
                    ->name('getReviewers')
                    ->get('reviewers', [UserController::class, 'getReviewers']);

                app('router')
                    ->name('getReport')
                    ->get('report', [UserController::class, 'getReport']);
            }
        );

        parent::__construct();
    }
}
