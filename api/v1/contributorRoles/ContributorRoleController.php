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

use APP\facades\Repo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\author\contributorRole\ContributorRole;
use PKP\author\contributorRole\ContributorRoleIdentifier;
use PKP\author\creditContributorRole\CreditContributorRole;
use PKP\security\Role;
use PKP\security\authorization\CanAccessSettingsPolicy;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\services\PKPSchemaService;

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
            self::roleAuthorizer([
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_MANAGER,
            ]),
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
        Route::get('{roleId}', $this->get(...))
            ->name('contributorRole.getContributorRole')
            ->whereNumber('roleId');

        Route::middleware([
            self::roleAuthorizer([
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_MANAGER,
            ]),
        ])->group(function () {
            Route::get('', $this->getMany(...))
                ->name('contributorRole.getContributorRoles');
            Route::get('identifiers', $this->getIdentifiers(...))
                ->name('contributorRole.getIdentifiers');
            Route::post('', $this->add(...))
                ->name('contributorRole.addContributorRole');
            Route::put('{roleId}', $this->edit(...))
                ->name('contributorRole.editContributorRole')
                ->whereNumber('roleId');
            Route::delete('{roleId}', $this->delete(...))
                ->name('contributorRole.deleteContributorRole')
                ->whereNumber('roleId');
        });
    }

    /**
     * Create a new role.
     */
    public function add(Request $illuminateRequest): JsonResponse
    {
        return $this->saveRole($illuminateRequest, null);
    }

    /**
     * Edit an existing role by ID.
     */
    public function edit(Request $illuminateRequest): JsonResponse
    {
        $role = ContributorRole::find((int) $illuminateRequest->route('roleId'));

        if (!$role) {
            return response()->json([
                'error' => __('api.contributorRole.404.roleNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $contextId = $this->getRequest()->getContext()->getId();
        if ($contextId !== $role->contextId) {
            return response()->json([
                'error' => __('api.contributorRole.400.contextsNotMatched'),
            ], Response::HTTP_FORBIDDEN);
        }

        return $this->saveRole($illuminateRequest, $role);
    }

    /**
     * Delete a role by ID.
     */
    public function delete(Request $illuminateRequest): JsonResponse
    {
        $role = ContributorRole::find((int) $illuminateRequest->route('roleId'));

        if (!$role) {
            return response()->json([
                'error' => __('api.contributorRole.404.roleNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $contextId = $this->getRequest()->getContext()->getId();

        if ($contextId !== $role->contextId) {
            return response()->json([
                'error' => __('api.contributorRole.400.contextsNotMatched'),
            ], Response::HTTP_FORBIDDEN);
        }

        // Block the removal of a role when in use
        if (CreditContributorRole::query()->withContributorRoleId($role->id)->count()) {
            return response()->json([
                'error' => __('manager.contributorRoles.error.delete.inUse'),
            ], Response::HTTP_NOT_ACCEPTABLE);
        }

        $props = Repo::contributorRole()->getSchemaMap()->map($role);

        // E.g. last AUTHOR role cannot be deleted
        try {
            $role->delete();
        } catch (\Exception $e) {
            return response()->json([
                'error' => [__('api.contributorRole.400.errorDeletingAuthorRole')],
            ], Response::HTTP_NOT_ACCEPTABLE);
        }

        return response()->json($props, Response::HTTP_OK);
    }

    /**
     * Get a single active role
     */
    public function get(Request $illuminateRequest): JsonResponse
    {
        $role = ContributorRole::find((int) $illuminateRequest->route('roleId'));

        if (!$role) {
            return response()->json([
                'error' => __('api.contributorRole.404.roleNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $contextId = $this->getRequest()->getContext()->getId();

        if ($contextId !== $role->contextId) {
            return response()->json([
                'error' => __('api.contributorRole.400.contextsNotMatched'),
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json(Repo::contributorRole()->getSchemaMap()->map($role), Response::HTTP_OK);
    }

    /**
     * Get the list of active roles.
     */
    public function getMany(Request $illuminateRequest): JsonResponse
    {
        $roles = ContributorRole::query()->withContextId($this->getRequest()->getContext()->getId());
        return response()->json(Repo::contributorRole()->getSchemaMap()->summarizeMany($roles->get())->values(), Response::HTTP_OK);
    }

    /**
     * Get the list of all identifiers
     */
    public function getIdentifiers(Request $illuminateRequest): JsonResponse
    {
        return response()->json(ContributorRoleIdentifier::getRoles(), Response::HTTP_OK);
    }

    /**
     * Create or update a role.
     *
     * Used internally to handle both new role creation and editing existing ones.
     */
    private function saveRole(Request $illuminateRequest, ?ContributorRole $role): JsonResponse
    {
        $context = $this->getRequest()->getContext();
        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_CONTRIBUTOR_ROLE, $illuminateRequest->input());

        $readOnlyErrors = $this->getWriteDisabledErrors(PKPSchemaService::SCHEMA_CONTRIBUTOR_ROLE, $params);
        if ($readOnlyErrors) {
            return response()->json($readOnlyErrors, Response::HTTP_BAD_REQUEST);
        }

        $params['id'] = $role?->id;
        $params['contextId'] = $context->getId();

        $errors = Repo::contributorRole()->validate($role, $params, $context);
        if ($errors) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        // Disallow edit of identifer when editing
        if ($params['id']) {
            unset($params['contributorRoleIdentifier']);
        }

        try {
            $newRole = ContributorRole::add($params);
        } catch (\Exception $e) {
            return response()->json([
                'error' => [__('api.contributorRole.400.errorSavingRole')],
            ], Response::HTTP_BAD_REQUEST);
        }

        return response()->json(Repo::contributorRole()->getSchemaMap()->map($newRole), Response::HTTP_OK);
    }

    /**
     * This method returns errors for any params that match
     * properties in the schema with writeDisabledInApi set to true.
     *
     * This is used for properties that can not be edited through
     * the API, but which otherwise can be edited by the entity's
     * repository.
     */
    protected function getWriteDisabledErrors(string $schemaName, array $params): array
    {
        $schema = app()->get('schema')->get($schemaName);

        $writeDisabledProps = [];
        foreach ($schema->properties as $propName => $propSchema) {
            if (!empty($propSchema->writeDisabledInApi)) {
                $writeDisabledProps[] = $propName;
            }
        }

        $errors = [];

        $notAllowedProps = array_intersect(
            $writeDisabledProps,
            array_keys($params)
        );

        if (!empty($notAllowedProps)) {
            foreach ($notAllowedProps as $propName) {
                $errors[$propName] = [__('api.400.propReadOnly', ['prop' => $propName])];
            }
        }

        return $errors;
    }
}
