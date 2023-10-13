<?php

/**
 * @file api/v1/stats/users/PKPStatsUserController.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsUserController
 *
 * @ingroup api_v1_stats
 *
 * @brief Controller class to handle API requests for publication statistics.
 *
 */

namespace PKP\API\v1\stats\users;

use APP\facades\Repo;
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

class PKPStatsUserController extends PKPBaseController
{
    /**
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return 'stats/users';
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
        Route::get('', $this->get(...))->name('stats.user.getUserStat');
    }

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
     * Get user stats
     *
     * Returns the count of users broken down by roles
     *
     * @hook API::stats::users::params [[$collector, $illuminateRequest]]
     */
    public function get(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();

        if (!$request->getContext()) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $collector = Repo::user()->getCollector();
        $dateParams = [];
        foreach ($illuminateRequest->query() as $param => $value) {
            switch ($param) {
                case 'registeredAfter':
                    $collector->filterRegisteredAfter($value);
                    $dateParams['dateStart'] = $value;
                    break;
                case 'registeredBefore':
                    $collector->filterRegisteredBefore($value);
                    $dateParams['dateEnd'] = $value;
                    break;
                case 'status': switch ($value) {
                    case 'disabled':
                        $collector->filterByStatus($collector::STATUS_DISABLED);
                        break;
                    case 'all':
                        $collector->filterByStatus($collector::STATUS_ALL);
                        break;
                    default:
                    case 'active':
                        $collector->filterByStatus($collector::STATUS_ACTIVE);
                        break;
                }
            }
        }

        Hook::call('API::stats::users::params', [$collector, $illuminateRequest]);

        $collector->filterByContextIds([$request->getContext()->getId()]);

        $result = $this->_validateStatDates($dateParams);
        if ($result !== true) {
            return response()->json([
                'error' => $result,
            ], Response::HTTP_BAD_REQUEST);
        }

        return response()->json(
            array_map(
                function ($item) {
                    $item['name'] = __($item['name']);
                    return $item;
                },
                Repo::user()->getRolesOverview($collector)
            ),
            Response::HTTP_OK
        );
    }
}
