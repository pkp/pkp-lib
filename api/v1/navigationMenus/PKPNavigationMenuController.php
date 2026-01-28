<?php

/**
 * @file api/v1/navigationMenus/PKPNavigationMenuController.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPNavigationMenuController
 *
 * @ingroup api_v1_navigationMenus
 *
 * @brief Controller class to handle API requests for navigation menu operations.
 */

namespace PKP\API\v1\navigationMenus;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\core\PKPApplication;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\navigationMenu\NavigationMenuDAO;
use PKP\navigationMenu\resources\NavigationMenuResource;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use PKP\services\PKPNavigationMenuService;
use PKP\services\PKPSchemaService;

class PKPNavigationMenuController extends PKPBaseController
{
    /** @var int Default maximum nesting depth for navigation menu items */
    public const DEFAULT_MAX_DEPTH = 2;
    /**
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return 'navigationMenus';
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getRouteGroupMiddleware()
     */
    public function getRouteGroupMiddleware(): array
    {
        return [
            'has.user',
            self::roleAuthorizer([
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_MANAGER,
            ]),
        ];
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getGroupRoutes()
     */
    public function getGroupRoutes(): void
    {
        // Get all available menu items (for new menu creation)
        Route::get('items', $this->getAllItems(...))
            ->name('navigationMenu.getAllItems');

        // Get navigation menu areas for the active theme
        Route::get('areas', $this->getAreas(...))
            ->name('navigationMenu.getAreas');

        Route::get('{navigationMenuId}/items', $this->getItems(...))
            ->name('navigationMenu.getItems')
            ->whereNumber('navigationMenuId');

        Route::post('', $this->add(...))
            ->name('navigationMenu.add');

        Route::put('{navigationMenuId}', $this->edit(...))
            ->name('navigationMenu.edit')
            ->whereNumber('navigationMenuId');
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::authorize()
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        $this->addPolicy(new UserRolesRequiredPolicy($request), true);

        $rolePolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);

        foreach ($roleAssignments as $role => $operations) {
            $rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
        }

        $this->addPolicy($rolePolicy);

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Get all available menu items for new menu creation
     * Returns empty assigned items and all context items as unassigned
     */
    public function getAllItems(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $context = $request->getContext();
        $contextId = $context?->getId() ?? PKPApplication::SITE_CONTEXT_ID;

        $navigationMenuService = app(PKPNavigationMenuService::class);

        return response()->json([
            'assigned' => [],
            'unassigned' => $navigationMenuService->getAllMenuItems($contextId),
            'itemTypes' => $navigationMenuService->getMenuItemTypes(),
        ], Response::HTTP_OK);
    }

    /**
     * Get navigation menu items (assigned and unassigned) for a specific menu
     */
    public function getItems(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $context = $request->getContext();
        $contextId = $context?->getId() ?? PKPApplication::SITE_CONTEXT_ID;

        $navigationMenuId = (int) $illuminateRequest->route('navigationMenuId');

        /** @var NavigationMenuDAO $navigationMenuDao */
        $navigationMenuDao = DAORegistry::getDAO('NavigationMenuDAO');
        $navigationMenu = $navigationMenuDao->getById($navigationMenuId, $contextId);

        if (!$navigationMenu) {
            return response()->json([
                'error' => __('api.navigationMenus.404.navigationMenuNotFound')
            ], Response::HTTP_NOT_FOUND);
        }

        $navigationMenuService = app(PKPNavigationMenuService::class);

        return response()->json([
            'assigned' => $navigationMenuService->getAssignedItemsTree($navigationMenuId, $navigationMenu),
            'unassigned' => $navigationMenuService->getUnassignedItems($contextId, $navigationMenuId, $navigationMenu),
            'itemTypes' => $navigationMenuService->getMenuItemTypes(),
        ], Response::HTTP_OK);
    }

    /**
     * Get navigation menu areas for the active theme
     */
    public function getAreas(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $context = $request->getContext();

        $navigationMenuService = app(PKPNavigationMenuService::class);

        return response()->json([
            'areas' => $navigationMenuService->getNavigationAreas($context),
        ], Response::HTTP_OK);
    }

    /**
     * Create a new navigation menu
     */
    public function add(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $context = $request->getContext();
        $contextId = $context?->getId() ?? PKPApplication::SITE_CONTEXT_ID;

        // Convert input to schema types
        $params = $this->convertStringsToSchema(
            PKPSchemaService::SCHEMA_NAVIGATION_MENU,
            $illuminateRequest->input()
        );

        // Validate required fields
        $errors = $this->validateNavigationMenu($params);
        if (!empty($errors)) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        /** @var NavigationMenuDAO $navigationMenuDao */
        $navigationMenuDao = DAORegistry::getDAO('NavigationMenuDAO');

        // Create new navigation menu
        $navigationMenu = $navigationMenuDao->newDataObject();
        $navigationMenu->setTitle($params['title']);
        $navigationMenu->setAreaName($params['areaName'] ?? '');
        $navigationMenu->setContextId($contextId);

        $navigationMenuId = $navigationMenuDao->insertObject($navigationMenu);

        // Save menu tree assignments if provided
        if (!empty($params['menuTree'])) {
            $navigationMenuService = app(PKPNavigationMenuService::class);
            $navigationMenuService->saveMenuTreeAssignments($navigationMenuId, $params['menuTree']);
        }

        return response()->json(
            (new NavigationMenuResource($navigationMenuDao->getById($navigationMenuId)))->toArray(null),
            Response::HTTP_CREATED
        );
    }

    /**
     * Update an existing navigation menu
     */
    public function edit(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $context = $request->getContext();
        $contextId = $context?->getId() ?? PKPApplication::SITE_CONTEXT_ID;

        $navigationMenuId = (int) $illuminateRequest->route('navigationMenuId');

        /** @var NavigationMenuDAO $navigationMenuDao */
        $navigationMenuDao = DAORegistry::getDAO('NavigationMenuDAO');
        $navigationMenu = $navigationMenuDao->getById($navigationMenuId, $contextId);

        if (!$navigationMenu) {
            return response()->json([
                'error' => __('api.navigationMenus.404.navigationMenuNotFound')
            ], Response::HTTP_NOT_FOUND);
        }

        // Convert input to schema types
        $params = $this->convertStringsToSchema(
            PKPSchemaService::SCHEMA_NAVIGATION_MENU,
            $illuminateRequest->input()
        );

        // Validate - title is required only if provided (for partial updates)
        // Pass menu ID to exclude it from area assignment check
        $errors = $this->validateNavigationMenu($params, false, $navigationMenuId);
        if (!empty($errors)) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        // Update fields if provided
        if (isset($params['title'])) {
            $navigationMenu->setTitle($params['title']);
        }
        if (array_key_exists('areaName', $params)) {
            $navigationMenu->setAreaName($params['areaName'] ?? '');
        }

        $navigationMenuDao->updateObject($navigationMenu);

        // Save menu tree assignments if provided
        if (isset($params['menuTree'])) {
            $navigationMenuService = app(PKPNavigationMenuService::class);
            $navigationMenuService->saveMenuTreeAssignments($navigationMenuId, $params['menuTree']);
        }

        return response()->json(
            (new NavigationMenuResource($navigationMenuDao->getById($navigationMenuId)))->toArray(null),
            Response::HTTP_OK
        );
    }

    /**
     * Validate navigation menu parameters
     *
     * @param array $params The parameters to validate
     * @param bool $requireTitle Whether title is required (true for create, false for update)
     * @param int|null $excludeMenuId Menu ID to exclude from area check (for updates)
     * @return array Validation errors, empty if valid
     */
    protected function validateNavigationMenu(array $params, bool $requireTitle = true, ?int $excludeMenuId = null): array
    {
        $errors = [];

        $request = $this->getRequest();
        $context = $request->getContext();
        $contextId = $context?->getId() ?? PKPApplication::SITE_CONTEXT_ID;

        /** @var NavigationMenuDAO $navigationMenuDao */
        $navigationMenuDao = DAORegistry::getDAO('NavigationMenuDAO');

        // Title validation
        if ($requireTitle && empty($params['title'])) {
            $errors['title'] = [__('manager.navigationMenus.form.titleRequired')];
        } elseif (isset($params['title']) && empty($params['title'])) {
            $errors['title'] = [__('manager.navigationMenus.form.titleRequired')];
        }

        // Check for duplicate titles
        if (!empty($params['title'])) {
            $existingMenu = $navigationMenuDao->getByTitle($contextId, $params['title']);
            if ($existingMenu && $existingMenu->getId() !== $excludeMenuId) {
                $errors['title'] = [__('manager.navigationMenus.form.duplicateTitles')];
            }
        }

        // Area assignment validation - check if another menu is already assigned to this area
        if (!empty($params['areaName'])) {
            $existingMenus = $navigationMenuDao->getByArea($contextId, $params['areaName'])->toArray();
            $existingMenu = $existingMenus[0] ?? null;

            if ($existingMenu && $existingMenu->getId() !== $excludeMenuId) {
                $errors['areaName'] = [__('manager.navigationMenus.form.menuAssigned')];
            }
        }

        return $errors;
    }
}
