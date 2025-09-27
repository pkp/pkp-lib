<?php

/**
 * @file api/v1/contributorRoles/ContributorRoleController.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ContributorRoleController
 *
 * @brief Handle API requests for contributor role operations.
 *
 */

namespace PKP\API\v1\contributorRoles;

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use PKP\components\forms\context\ContributorRoleForm;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\author\contributorRole\ContributorRole;
use PKP\author\creditContributorRole\CreditContributorRole;
use PKP\security\Role;
use PKP\security\authorization\CanAccessSettingsPolicy;
use PKP\security\authorization\ContextAccessPolicy;

class ContributorRoleController extends PKPBaseController
{
    /**
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return 'contributorRoles';
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

    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
        $this->addPolicy(new CanAccessSettingsPolicy());

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getGroupRoutes()
     */
    public function getGroupRoutes(): void
    {
        Route::middleware([
            self::roleAuthorizer([
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_MANAGER,
            ]),
        ])->group(function () {
            Route::get('', $this->getMany(...));
            Route::get('contributorRoleFormComponent', $this->getContributorRoleFormComponent(...));
            Route::post('', $this->add(...));
            Route::put('{roleId}', $this->edit(...))
                ->whereNumber('roleId');
            Route::delete('{roleId}', $this->delete(...))
                ->whereNumber('roleId');
        });
    }

    /**
     * Create a new role.
     */
    public function add(Request $illuminateRequest): JsonResponse
    {
        return $this->saveRole($illuminateRequest);
    }

    /**
     * Edit an existing role by ID.
     */
    public function edit(Request $illuminateRequest): JsonResponse
    {
        return $this->saveRole($illuminateRequest);
    }

    /**
     * Delete a role by ID.
     */
    public function delete(Request $illuminateRequest): JsonResponse
    {
        $roleId = (int) $illuminateRequest->route('roleId');

        if (!$roleId) {
            return response()->json(__('api.404.resourceNotFound'), Response::HTTP_NOT_FOUND);
        }

        $contextId = $this->getRequest()->getContext()->getId();

        // At least one must exist
        if (ContributorRole::query()->withContextId($contextId)->count() < 2) {
            return response()->json(__('manager.contributorRoles.error.delete.atLeastOne'), Response::HTTP_NOT_ACCEPTABLE);
        }

        // Block the removal of role when in use
        if (CreditContributorRole::query()->withContributorRoleId($roleId)->count()) {
            return response()->json(__('manager.contributorRoles.error.delete.inUse'), Response::HTTP_NOT_ACCEPTABLE);
        }

        Repo::contributorRole()->delete(roleId: $roleId);

        return response()->json([], Response::HTTP_OK);
    }

    /**
     * Get the list of active roles.
     */
    public function getMany(Request $illuminateRequest): JsonResponse
    {
        $data = Repo::contributorRole()->getByContextIdWithRoleId($this->getRequest()->getContext()->getId());
        return response()->json($data, Response::HTTP_OK);
    }

    /**
     * Get the add/edit form
     */
    public function getContributorRoleFormComponent()
    {
        $context = $this->getRequest()->getContext();
        $contributorRolesApiUrl = Application::get()->getRequest()->getDispatcher()->url(
            Application::get()->getRequest(),
            Application::ROUTE_API,
            $context->getPath(),
            'contributorRoles'
        );
        $contributorRoleForm = new ContributorRoleForm($contributorRolesApiUrl, $context);
        return response()->json($contributorRoleForm->getConfig(), Response::HTTP_OK);
    }

    /**
     * Create or update a role.
     *
     * Used internally to handle both new role creation and editing existing ones.
     */
    private function saveRole(Request $illuminateRequest): JsonResponse
    {
        $context = $this->getRequest()->getContext();
        $roleId = ((int) $illuminateRequest->route('roleId')) ?: null;
        $errors = Repo::contributorRole()->validate($illuminateRequest->all(), $roleId, $context);

        if ($errors) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $id = Repo::contributorRole()->add(
            $illuminateRequest->input('name'),
            $roleId ? null : $illuminateRequest->input('identifier') /* Disallow edit of identifier */,
            $context->getId(),
            $roleId
        );

        return response()->json(Repo::contributorRole()->getByRoleId($id), Response::HTTP_OK);
    }
}
