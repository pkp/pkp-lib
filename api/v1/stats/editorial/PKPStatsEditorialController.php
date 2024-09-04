<?php

/**
 * @file api/v1/stats/editorial/PKPStatsEditorialController.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsEditorialController
 *
 * @ingroup api_v1_stats
 *
 * @brief Controller class to handle API requests for publication statistics.
 *
 */

namespace PKP\API\v1\stats\editorial;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\plugins\Hook;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;

abstract class PKPStatsEditorialController extends PKPBaseController
{
    /**
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return 'stats/editorial';
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getRouteGroupMiddleware()
     */
    public function getRouteGroupMiddleware(): array
    {
        return [
            'has.user',
            'has.context',
            self::roleAuthorizer([
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
            ]),
        ];
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getGroupRoutes()
     */
    public function getGroupRoutes(): void
    {
        Route::get('', $this->getMany(...))
            ->name('stats.editorial.getEditorialStat');

        Route::get('averages', $this->getAverages(...))
            ->name('stats.editorial.getAverages');
    }

    /** The name of the section ids query param for this application */
    abstract public function getSectionIdsQueryParam();

    /**
     * @copydoc \PKP\core\PKPBaseController::authorize()
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
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
     * @hook API::stats::editorial::params [[&$params, $illuminateRequest]]
     */
    public function get(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();

        if (!$request->getContext()) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $params = [];
        $sectionIdsQueryParam = $this->getSectionIdsQueryParam();
        foreach ($illuminateRequest->query() as $param => $value) {
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

        Hook::call('API::stats::editorial::params', [&$params, $illuminateRequest]);

        $params['contextIds'] = [$request->getContext()->getId()];

        $result = $this->_validateStatDates($params);
        if ($result !== true) {
            return response()->json(['error' => $result], Response::HTTP_BAD_REQUEST);
        }

        return response()->json(
            array_map(
                function ($item) {
                    $item['name'] = __($item['name']);
                    return $item;
                },
                app()->get('editorialStats')->getOverview($params)
            ),
            Response::HTTP_OK
        );
    }

    /**
     * Get yearly averages of editorial stats
     *
     * Returns information on average submissions received, accepted
     * and declined per year.
     *
     * @hook API::stats::editorial::averages::params [[&$params, $illuminateRequest]]
     */
    public function getAverages(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();

        if (!$request->getContext()) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $params = [];
        $sectionIdsQueryParam = $this->getSectionIdsQueryParam();
        foreach ($illuminateRequest->query() as $param => $value) {
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

        Hook::call('API::stats::editorial::averages::params', [&$params, $illuminateRequest]);

        $params['contextIds'] = [$request->getContext()->getId()];

        $statsEditorialService = app()->get('editorialStats'); /** @var \PKP\services\PKPStatsEditorialService $statsEditorialService */

        return response()->json($statsEditorialService->getAverages($params), Response::HTTP_OK);
    }
}
