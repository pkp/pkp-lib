<?php

/**
 * @file api/v1/stats/editorial/PKPStatsEditorialHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsEditorialHandler
 * @ingroup api_v1_stats
 *
 * @brief Handle API requests for publication statistics.
 *
 */

namespace PKP\API\v1\stats\editorial;

use APP\core\Services;
use PKP\handler\APIHandler;
use PKP\plugins\Hook;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;

abstract class PKPStatsEditorialHandler extends APIHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->_handlerPath = 'stats/editorial';
        $this->_endpoints = [
            'GET' => [
                [
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => [$this, 'get'],
                    'roles' => [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/averages',
                    'handler' => [$this, 'getAverages'],
                    'roles' => [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR],
                ],
            ],
        ];
        parent::__construct();
    }

    /** The name of the section ids query param for this application */
    abstract public function getSectionIdsQueryParam();

    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new UserRolesRequiredPolicy($request), true);

        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));

        $rolePolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);
        foreach ($roleAssignments as $role => $operations) {
            $rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
        }
        $this->addPolicy($rolePolicy);

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Get editorial stats
     *
     * Returns information on submissions received, accepted, declined,
     * average response times and more.
     *
     * @param Request $slimRequest Slim request object
     * @param object $response Response
     * @param array $args
     *
     * @return object Response
     */
    public function get($slimRequest, $response, $args)
    {
        $request = $this->getRequest();

        if (!$request->getContext()) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $params = [];
        $sectionIdsQueryParam = $this->getSectionIdsQueryParam();
        foreach ($slimRequest->getQueryParams() as $param => $value) {
            switch ($param) {
                case 'dateStart':
                case 'dateEnd':
                    $params[$param] = $value;
                    break;

                case $sectionIdsQueryParam:
                    if (is_string($value) && str_contains($value, ',')) {
                        $value = explode(',', $value);
                    } elseif (!is_array($value)) {
                        $value = [$value];
                    }
                    $params[$param] = array_map('intval', $value);
                    break;
            }
        }

        Hook::call('API::stats::editorial::params', [&$params, $slimRequest]);

        $params['contextIds'] = [$request->getContext()->getId()];

        $result = $this->_validateStatDates($params);
        if ($result !== true) {
            return $response->withStatus(400)->withJsonError($result);
        }

        return $response->withJson(array_map(
            function ($item) {
                $item['name'] = __($item['name']);
                return $item;
            },
            Services::get('editorialStats')->getOverview($params)
        ));
    }

    /**
     * Get yearly averages of editorial stats
     *
     * Returns information on average submissions received, accepted
     * and declined per year.
     *
     * @param Request $slimRequest Slim request object
     * @param object $response Response
     * @param array $args
     *
     * @return object Response
     */
    public function getAverages($slimRequest, $response, $args)
    {
        $request = $this->getRequest();

        if (!$request->getContext()) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $params = [];
        $sectionIdsQueryParam = $this->getSectionIdsQueryParam();
        foreach ($slimRequest->getQueryParams() as $param => $value) {
            switch ($param) {
                case $sectionIdsQueryParam:
                    if (is_string($value) && str_contains($value, ',')) {
                        $value = explode(',', $value);
                    } elseif (!is_array($value)) {
                        $value = [$value];
                    }
                    $params[$param] = array_map('intval', $value);
                    break;
            }
        }

        Hook::call('API::stats::editorial::averages::params', [&$params, $slimRequest]);

        $params['contextIds'] = [$request->getContext()->getId()];

        return $response->withJson(Services::get('editorialStats')->getAverages($params));
    }
}
