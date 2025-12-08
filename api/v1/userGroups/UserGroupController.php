<?php

/**
 * @file api/v1/userGroups/UserGroupController.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserGroupController
 *
 * @ingroup api_v1_userGroups
 *
 * @brief Controller class to handle API requests for user group operations
 */

namespace PKP\API\v1\userGroups;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\API\v1\contexts\resources\UserGroupResource;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use PKP\userGroup\UserGroup;

class UserGroupController extends PKPBaseController
{
    /**
     * @inheritDoc
     */
    public function getHandlerPath(): string
    {
        return 'userGroups';
    }

    /**
     * @inheritDoc
     */
    public function getRouteGroupMiddleware(): array
    {
        return [
            'has.user',
            'has.context'
        ];
    }

    /**
     * @inheritDoc
     */
    public function getGroupRoutes(): void
    {
        Route::middleware([
            self::roleAuthorizer([
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_ASSISTANT,
                Role::ROLE_ID_REVIEWER,
                Role::ROLE_ID_AUTHOR
            ]),
        ])->group(function () {
            Route::get('', $this->getMany(...))
                ->name('userGroups.getMany');
        });
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::authorize()
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        $this->addPolicy(new UserRolesRequiredPolicy($request));
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Get a list of available user groups
     */
    public function getMany(Request $illuminateRequest): JsonResponse
    {
        $context = $this->getRequest()->getContext();

        if (!$context) {
            return response()->json([
                'error' => __('api.contexts.404.contextNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $collector = UserGroup::withContextIds([$context->getId()]);

        foreach ($illuminateRequest->query() as $param => $val) {
            switch ($param) {
                case 'stageIds':
                    $collector->withStageIds(array_map(intval(...), paramToArray($val)));
                    break;
                case 'roleIds':
                    $collector->withRoleIds(array_map(intval(...), paramToArray($val)));
                    break;
            }
        }

        $userGroups = $collector->get();

        return response()->json(
            [
                'itemsMax' => $userGroups->count(),
                'items' => UserGroupResource::collection(resource: $userGroups)
            ],
            Response::HTTP_OK
        );
    }
}
