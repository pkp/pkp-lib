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
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\navigationMenu\NavigationMenu;
use PKP\navigationMenu\NavigationMenuDAO;
use PKP\navigationMenu\NavigationMenuItem;
use PKP\navigationMenu\NavigationMenuItemAssignment;
use PKP\navigationMenu\NavigationMenuItemAssignmentDAO;
use PKP\navigationMenu\NavigationMenuItemDAO;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use PKP\services\PKPNavigationMenuService;
use PKP\services\PKPSchemaService;

class PKPNavigationMenuController extends PKPBaseController
{
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
        $contextId = $context?->getId() ?? \PKP\core\PKPApplication::SITE_CONTEXT_ID;

        /** @var NavigationMenuItemDAO $menuItemDao */
        $menuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO');

        // Get all items for this context
        $allItems = $menuItemDao->getByContextId($contextId);
        $unassignedItems = [];

        while ($item = $allItems->next()) {
            $unassignedItems[] = $this->mapMenuItem($item);
        }

        // Get item types for reference
        $navigationMenuService = app(PKPNavigationMenuService::class);
        $itemTypes = $navigationMenuService->getMenuItemTypes();

        return response()->json([
            'assigned' => [],
            'unassigned' => $unassignedItems,
            'itemTypes' => $itemTypes,
            'maxDepth' => 3,
        ], Response::HTTP_OK);
    }

    /**
     * Get navigation menu items (assigned and unassigned) for a specific menu
     */
    public function getItems(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $context = $request->getContext();
        $contextId = $context?->getId() ?? \PKP\core\PKPApplication::SITE_CONTEXT_ID;

        $navigationMenuId = (int) $illuminateRequest->route('navigationMenuId');

        /** @var NavigationMenuDAO $navigationMenuDao */
        $navigationMenuDao = DAORegistry::getDAO('NavigationMenuDAO');
        $navigationMenu = $navigationMenuDao->getById($navigationMenuId, $contextId);

        if (!$navigationMenu) {
            return response()->json([
                'error' => __('api.navigationMenus.404.navigationMenuNotFound')
            ], Response::HTTP_NOT_FOUND);
        }

        // Get assigned items (hierarchical)
        $assignedItems = $this->getAssignedItemsTree($navigationMenuId, $navigationMenu);

        // Get unassigned items (flat list)
        $unassignedItems = $this->getUnassignedItems($contextId, $navigationMenuId, $navigationMenu);

        // Get item types for reference
        $navigationMenuService = app(PKPNavigationMenuService::class);
        $itemTypes = $navigationMenuService->getMenuItemTypes();

        return response()->json([
            'assigned' => $assignedItems,
            'unassigned' => $unassignedItems,
            'itemTypes' => $itemTypes,
            'maxDepth' => 3,
        ], Response::HTTP_OK);
    }

    /**
     * Get assigned items as a hierarchical tree
     */
    protected function getAssignedItemsTree(int $navigationMenuId, NavigationMenu $navigationMenu): array
    {
        /** @var NavigationMenuItemAssignmentDAO $assignmentDao */
        $assignmentDao = DAORegistry::getDAO('NavigationMenuItemAssignmentDAO');

        /** @var NavigationMenuItemDAO $menuItemDao */
        $menuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO');

        // Get all assignments for this menu
        $assignments = $assignmentDao->getByMenuId($navigationMenuId);
        $assignmentList = [];
        while ($assignment = $assignments->next()) {
            $assignmentList[] = $assignment;
        }

        // Build a map of assignments by parent ID
        $byParentId = [];
        foreach ($assignmentList as $assignment) {
            $parentId = $assignment->getParentId() ?? 0;
            if (!isset($byParentId[$parentId])) {
                $byParentId[$parentId] = [];
            }
            $byParentId[$parentId][] = $assignment;
        }

        // Sort each group by sequence
        foreach ($byParentId as $parentId => $children) {
            usort($children, fn($a, $b) => $a->getSequence() - $b->getSequence());
            $byParentId[$parentId] = $children;
        }

        // Build tree starting from root (parent_id = 0)
        return $this->buildAssignmentTree($byParentId, 0, $navigationMenu, $menuItemDao);
    }

    /**
     * Recursively build the assignment tree
     */
    protected function buildAssignmentTree(array $byParentId, int $parentId, NavigationMenu $navigationMenu, NavigationMenuItemDAO $menuItemDao): array
    {
        $result = [];

        if (!isset($byParentId[$parentId])) {
            return $result;
        }

        foreach ($byParentId[$parentId] as $assignment) {
            $menuItem = $menuItemDao->getById($assignment->getMenuItemId());
            if (!$menuItem) {
                continue;
            }

            // Build children first so we can pass them to mapMenuItem
            // Note: parent_id in the database stores the MENU ITEM ID of the parent, not the assignment ID
            $children = $this->buildAssignmentTree($byParentId, $menuItem->getId(), $navigationMenu, $menuItemDao);

            $result[] = $this->mapMenuItem($menuItem, $assignment, $children);
        }

        return $result;
    }

    /**
     * Get unassigned items for the context
     */
    protected function getUnassignedItems(int $contextId, int $navigationMenuId, NavigationMenu $navigationMenu): array
    {
        /** @var NavigationMenuItemDAO $menuItemDao */
        $menuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO');

        /** @var NavigationMenuItemAssignmentDAO $assignmentDao */
        $assignmentDao = DAORegistry::getDAO('NavigationMenuItemAssignmentDAO');

        // Get all items for this context
        $allItems = $menuItemDao->getByContextId($contextId);
        $allItemsList = [];
        while ($item = $allItems->next()) {
            $allItemsList[$item->getId()] = $item;
        }

        // Get assigned item IDs for this menu
        $assignments = $assignmentDao->getByMenuId($navigationMenuId);
        $assignedItemIds = [];
        while ($assignment = $assignments->next()) {
            $assignedItemIds[$assignment->getMenuItemId()] = true;
        }

        // Filter to unassigned items
        $unassignedItems = [];

        foreach ($allItemsList as $itemId => $menuItem) {
            if (!isset($assignedItemIds[$itemId])) {
                $unassignedItems[] = $this->mapMenuItem($menuItem);
            }
        }

        return $unassignedItems;
    }

    /**
     * Map a menu item to API response format
     *
     * This is the single source of truth for menu item serialization.
     * Used by getAllItems, getItems (assigned), and getItems (unassigned).
     *
     * @param NavigationMenuItem $menuItem The menu item
     * @param NavigationMenuItemAssignment|null $assignment The assignment (null for unassigned items)
     * @param array $children Pre-built children array for assigned items
     * @return array Mapped item data
     */
    protected function mapMenuItem(
        NavigationMenuItem $menuItem,
        ?NavigationMenuItemAssignment $assignment = null,
        array $children = []
    ): array {
        $navigationMenuService = app(PKPNavigationMenuService::class);
        $conditionalInfo = $this->getItemConditionalInfo($menuItem, $navigationMenuService);

        // For assigned items, use assignment ID; for unassigned, use menu item ID
        $id = $assignment ? $assignment->getId() : $menuItem->getId();

        // Get appropriate title based on whether this is an assignment or standalone item
        $title = $assignment
            ? $this->getItemTitle($assignment, $menuItem)
            : $this->getMenuItemTitle($menuItem);

        // Get localized title - assignment title takes precedence
        $localizedTitle = $assignment
            ? ($assignment->getTitle(null) ?? $menuItem->getTitle(null) ?? [])
            : ($menuItem->getTitle(null) ?? []);

        return [
            'id' => $id,
            'menuItemId' => $menuItem->getId(),
            'assignmentId' => $assignment?->getId(),
            'title' => $title,
            'localizedTitle' => $localizedTitle,
            'type' => $menuItem->getType(),
            'path' => $menuItem->getPath(),
            'url' => $menuItem->getUrl(),
            'isVisible' => !$conditionalInfo['hasConditionalDisplay'],
            'hasWarning' => count($children) > 0,
            'warningMessage' => count($children) > 0 ? __('manager.navigationMenus.form.submenuWarning') : null,
            'conditionalWarning' => $conditionalInfo['conditionalWarning'],
            'parentId' => $assignment?->getParentId(),
            'sequence' => $assignment?->getSequence(),
            'children' => $children,
        ];
    }

    /**
     * Get conditional display info for a menu item based on its type
     * Items with conditional display show a crossed-out eye icon
     */
    protected function getItemConditionalInfo(NavigationMenuItem $menuItem, PKPNavigationMenuService $service): array
    {
        $itemTypes = $service->getMenuItemTypes();
        $type = $menuItem->getType();

        $hasConditionalDisplay = false;
        $conditionalWarning = null;

        if (isset($itemTypes[$type]['conditionalWarning'])) {
            $hasConditionalDisplay = true;
            $conditionalWarning = $itemTypes[$type]['conditionalWarning'];
        }

        return [
            'hasConditionalDisplay' => $hasConditionalDisplay,
            'conditionalWarning' => $conditionalWarning,
        ];
    }

    /**
     * Get the display title for an assignment
     */
    protected function getItemTitle(NavigationMenuItemAssignment $assignment, NavigationMenuItem $menuItem): string
    {
        // First, ensure the menu item has its localized titles set
        $navigationMenuService = app(PKPNavigationMenuService::class);
        $navigationMenuService->setAllNMILocalizedTitles($menuItem);

        // Try assignment-specific title first (custom override)
        $assignmentTitles = $assignment->getTitle(null);
        if (is_array($assignmentTitles) && !empty($assignmentTitles)) {
            $locale = \PKP\facades\Locale::getLocale();
            if (isset($assignmentTitles[$locale]) && !empty($assignmentTitles[$locale])) {
                return $this->transformTitleVariables($assignmentTitles[$locale]);
            }
            // Fall back to first available assignment title
            $firstTitle = reset($assignmentTitles);
            if (!empty($firstTitle)) {
                return $this->transformTitleVariables($firstTitle);
            }
        } elseif (is_string($assignmentTitles) && !empty($assignmentTitles)) {
            return $this->transformTitleVariables($assignmentTitles);
        }

        // Fall back to menu item's localized title
        $title = $menuItem->getLocalizedTitle();
        if (!empty($title)) {
            return $this->transformTitleVariables($title);
        }

        // Final fallback to type-based title
        $itemTypes = $navigationMenuService->getMenuItemTypes();
        $type = $menuItem->getType();

        if (isset($itemTypes[$type]['title'])) {
            return $itemTypes[$type]['title'];
        }

        return $type ?? '';
    }

    /**
     * Transform template variables in title (e.g., {$loggedInUsername} -> actual username)
     */
    protected function transformTitleVariables(string $title): string
    {
        // Check for {$loggedInUsername} pattern
        if (strpos($title, '{$loggedInUsername}') !== false) {
            $request = $this->getRequest();
            $user = $request->getUser();
            if ($user) {
                $title = str_replace('{$loggedInUsername}', $user->getUsername(), $title);
            }
        }

        return $title;
    }

    /**
     * Get the display title for a menu item
     */
    protected function getMenuItemTitle(NavigationMenuItem $menuItem): string
    {
        // First, set all localized titles (translates titleLocaleKey if no explicit title)
        $navigationMenuService = app(PKPNavigationMenuService::class);
        $navigationMenuService->setAllNMILocalizedTitles($menuItem);

        // Now get the localized title
        $title = $menuItem->getLocalizedTitle();
        if (!empty($title)) {
            return $this->transformTitleVariables($title);
        }

        // Fall back to type-based title from the type definition
        $itemTypes = $navigationMenuService->getMenuItemTypes();
        $type = $menuItem->getType();

        if (isset($itemTypes[$type]['title'])) {
            return $itemTypes[$type]['title'];
        }

        return $type ?? '';
    }

    /**
     * Get navigation menu areas for the active theme
     */
    public function getAreas(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $context = $request->getContext();

        $areas = [];

        if ($context) {
            $themePlugins = \PKP\plugins\PluginRegistry::loadCategory('themes', true);
            $activeThemeNavigationAreas = [];

            foreach ($themePlugins as $themePlugin) {
                if ($themePlugin->isActive()) {
                    $themeAreas = $themePlugin->getMenuAreas();
                    foreach ($themeAreas as $area) {
                        // Use the area name directly as the label (matches old form behavior)
                        $activeThemeNavigationAreas[$area] = $area;
                    }
                    break;
                }
            }

            $areas = $activeThemeNavigationAreas;
        }

        return response()->json([
            'areas' => $areas,
        ], Response::HTTP_OK);
    }

    /**
     * Create a new navigation menu
     */
    public function add(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $context = $request->getContext();
        $contextId = $context?->getId() ?? \PKP\core\PKPApplication::SITE_CONTEXT_ID;

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
            $this->saveMenuTreeAssignments($navigationMenuId, $params['menuTree']);
        }

        return response()->json(
            $this->mapNavigationMenu($navigationMenuDao->getById($navigationMenuId)),
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
        $contextId = $context?->getId() ?? \PKP\core\PKPApplication::SITE_CONTEXT_ID;

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
        $errors = $this->validateNavigationMenu($params, false);
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
            $this->saveMenuTreeAssignments($navigationMenuId, $params['menuTree']);
        }

        return response()->json(
            $this->mapNavigationMenu($navigationMenuDao->getById($navigationMenuId)),
            Response::HTTP_OK
        );
    }

    /**
     * Save menu tree assignments from the flat menuTree format
     * Format: menuTree[menuItemId] = { seq: number, parentId: number|null }
     */
    protected function saveMenuTreeAssignments(int $navigationMenuId, array $menuTree): void
    {
        /** @var NavigationMenuItemAssignmentDAO $assignmentDao */
        $assignmentDao = DAORegistry::getDAO('NavigationMenuItemAssignmentDAO');

        // Delete all existing assignments for this menu
        $assignmentDao->deleteByMenuId($navigationMenuId);

        if (empty($menuTree)) {
            return;
        }

        // Sort by seq within each parent group
        $itemsByParent = [];
        foreach ($menuTree as $menuItemId => $data) {
            $parentId = $data['parentId'] ?? null;
            $key = $parentId ?? 'root';
            if (!isset($itemsByParent[$key])) {
                $itemsByParent[$key] = [];
            }
            $itemsByParent[$key][$menuItemId] = $data;
        }

        // Sort each group by seq
        foreach ($itemsByParent as $key => $items) {
            uasort($items, fn($a, $b) => ($a['seq'] ?? 0) - ($b['seq'] ?? 0));
            $itemsByParent[$key] = $items;
        }

        // Create root level assignments first
        if (isset($itemsByParent['root'])) {
            foreach ($itemsByParent['root'] as $menuItemId => $data) {
                $assignment = $assignmentDao->newDataObject();
                $assignment->setMenuId($navigationMenuId);
                $assignment->setMenuItemId((int) $menuItemId);
                $assignment->setParentId(null);
                $assignment->setSequence((int) ($data['seq'] ?? 0));

                $assignmentDao->insertObject($assignment);
            }
        }

        // Create child assignments - parent_id references the parent's menu item ID
        foreach ($itemsByParent as $parentKey => $items) {
            if ($parentKey === 'root') {
                continue;
            }

            foreach ($items as $menuItemId => $data) {
                $parentMenuItemId = $data['parentId'];

                $assignment = $assignmentDao->newDataObject();
                $assignment->setMenuId($navigationMenuId);
                $assignment->setMenuItemId((int) $menuItemId);
                $assignment->setParentId((int) $parentMenuItemId);
                $assignment->setSequence((int) ($data['seq'] ?? 0));

                $assignmentDao->insertObject($assignment);
            }
        }
    }

    /**
     * Validate navigation menu parameters
     *
     * @param array $params The parameters to validate
     * @param bool $requireTitle Whether title is required (true for create, false for update)
     * @return array Validation errors, empty if valid
     */
    protected function validateNavigationMenu(array $params, bool $requireTitle = true): array
    {
        $errors = [];

        // Title validation
        if ($requireTitle && empty($params['title'])) {
            $errors['title'] = [__('manager.navigationMenus.form.titleRequired')];
        } elseif (isset($params['title']) && empty($params['title'])) {
            $errors['title'] = [__('manager.navigationMenus.form.titleRequired')];
        }

        return $errors;
    }

    /**
     * Map a NavigationMenu to array format
     */
    protected function mapNavigationMenu(NavigationMenu $menu): array
    {
        return [
            'id' => $menu->getId(),
            'title' => $menu->getTitle(),
            'areaName' => $menu->getAreaName(),
            'contextId' => $menu->getContextId(),
        ];
    }
}
