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
use PKP\facades\Locale;
use PKP\handler\APIHandler;
use PKP\plugins\HookRegistry;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\Role;

class PKPUserHandler extends APIHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->_handlerPath = 'users';
        $roles = [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR];
        $this->_endpoints = [
            'GET' => [
                [
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => [$this, 'getMany'],
                    'roles' => $roles
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/reviewers',
                    'handler' => [$this, 'getReviewers'],
                    'roles' => $roles
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{userId:\d+}',
                    'handler' => [$this, 'get'],
                    'roles' => $roles
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/report',
                    'handler' => [$this, 'getReport'],
                    'roles' => $roles
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
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Get a collection of users
     *
     * @param Request $slimRequest Slim request object
     * @param Response $response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function getMany($slimRequest, $response, $args)
    {
        $request = $this->getRequest();
        $context = $request->getContext();

        if (!$context) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $params = $this->_processAllowedParams($slimRequest->getQueryParams(), [
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
        ]);

        $params['contextId'] = $context->getId();

        HookRegistry::call('API::users::params', [&$params, $slimRequest]);
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
            ->orderBy($orderBy, $orderDirection, [Locale::getLocale(), $request->getSite()->getPrimaryLocale()])
            ->limit($params['count'] ?? null)
            ->offset($params['offset'] ?? null)
            ->filterByStatus($params['status'] ?? $collector::STATUS_ALL);

        $users = Repo::user()->getMany($collector);

        $map = Repo::user()->getSchemaMap();
        $items = [];
        foreach ($users as $user) {
            $items[] = $map->summarize($user);
        }

        return $response->withJson([
            'itemsMax' => Repo::user()->getCount($collector->limit(null)->offset(null)),
            'items' => $items,
        ], 200);
    }

    /**
     * Get a single user
     *
     * @param Request $slimRequest Slim request object
     * @param Response $response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function get($slimRequest, $response, $args)
    {
        $request = $this->getRequest();

        if (!empty($args['userId'])) {
            $user = Repo::user()->get($args['userId']);
        }

        if (!$user) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $data = Repo::user()->getSchemaMap()->map($user, $slimRequest);

        return $response->withJson($data, 200);
    }

    /**
     * Get a collection of reviewers
     *
     * @param Request $slimRequest Slim request object
     * @param Response $response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function getReviewers($slimRequest, $response, $args)
    {
        $request = $this->getRequest();
        $context = $request->getContext();

        if (!$context) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $params = $this->_processAllowedParams($slimRequest->getQueryParams(), [
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
        ]);

        HookRegistry::call('API::users::reviewers::params', [&$params, $slimRequest]);

        $collector = Repo::user()->getCollector()
            ->filterByContextIds([$context->getId()])
            ->includeReviewerData()
            ->filterByRoleIds([\PKP\security\Role::ROLE_ID_REVIEWER])
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
            $items[] = $map->summarizeReviewer($user, $slimRequest);
        }

        return $response->withJson([
            'itemsMax' => Repo::user()->getCount($collector->limit(null)->offset(null)),
            'items' => $items,
        ], 200);
    }

    /**
     * Convert the query params passed to the end point. Exclude unsupported
     * params and coerce the type of those passed.
     *
     * @param array $params Key/value of request params
     * @param array $allowedKeys The param keys which should be processed and returned
     *
     * @return array
     */
    private function _processAllowedparams($params, $allowedKeys)
    {

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
                    $returnParams[$param] = (int) $val;
                    break;

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

    /**
     * Retrieve the user report
     *
     * @param Slim\Http\Request $slimRequest Slim request object
     * @param \APIResponse $response Response
     *
     * @return ?\APIResponse Response
     */
    public function getReport(\Slim\Http\Request $slimRequest, \APIResponse $response, array $args): ?\APIResponse
    {
        $request = $this->getRequest();

        $context = $request->getContext();
        if (!$context) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $params = ['contextId' => $context->getId()];
        foreach ($slimRequest->getQueryParams() as $param => $value) {
            switch ($param) {
                case 'userGroupIds':
                    if (is_string($value) && strpos($value, ',') > -1) {
                        $value = explode(',', $value);
                    } elseif (!is_array($value)) {
                        $value = [$value];
                    }
                    $params[$param] = array_map('intval', $value);
                    break;
                case 'mappings':
                    if (is_string($value) && strpos($value, ',') > -1) {
                        $value = explode(',', $value);
                    } elseif (!is_array($value)) {
                        $value = [$value];
                    }
                    $params[$param] = $value;
                    break;
            }
        }

        HookRegistry::call('API::users::user::report::params', [&$params, $slimRequest]);

        $this->getApp()->getContainer()->get('settings')->replace(['outputBuffering' => false]);

        $report = Repo::user()->getReport($params);
        header('content-type: text/comma-separated-values');
        header('content-disposition: attachment; filename="user-report-' . date('Y-m-d') . '.csv"');
        $report->serialize(fopen('php://output', 'w+'));
        exit;
    }
}
