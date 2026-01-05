<?php

/**
 * @file api/v1/navigationMenus/PKPNavigationMenuController.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPNavigationMenuController
 *
 * @ingroup api_v1_navigationMenus
 *
 * @brief Controller class to handle API requests for navigation menu operations.
 */

namespace PKP\API\v1\navigationMenus;

use APP\core\Application;
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
        Route::get('', $this->getMany(...))
            ->name('navigationMenu.getMany');

        Route::get('{navigationMenuId}', $this->get(...))
            ->name('navigationMenu.get')
            ->whereNumber('navigationMenuId');

        Route::get('{navigationMenuId}/items', $this->getItems(...))
            ->name('navigationMenu.getItems')
            ->whereNumber('navigationMenuId');

        Route::put('{navigationMenuId}/items', $this->saveItems(...))
            ->name('navigationMenu.saveItems')
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
     * Get all navigation menus for the current context
     */
    public function getMany(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $context = $request->getContext();
        $contextId = $context?->getId() ?? \PKP\core\PKPApplication::SITE_CONTEXT_ID;

        /** @var NavigationMenuDAO $navigationMenuDao */
        $navigationMenuDao = DAORegistry::getDAO('NavigationMenuDAO');
        $navigationMenus = $navigationMenuDao->getByContextId($contextId);

        $items = [];
        while ($menu = $navigationMenus->next()) {
            $items[] = $this->mapNavigationMenu($menu);
        }

        return response()->json([
            'items' => $items,
            'itemsMax' => count($items),
        ], Response::HTTP_OK);
    }

    /**
     * Get a single navigation menu
     */
    public function get(Request $illuminateRequest): JsonResponse
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

        return response()->json($this->mapNavigationMenu($navigationMenu), Response::HTTP_OK);
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
     * Save navigation menu items (update assignments)
     */
    public function saveItems(Request $illuminateRequest): JsonResponse
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

        $assignedItems = $illuminateRequest->input('assigned', []);

        /** @var NavigationMenuItemAssignmentDAO $assignmentDao */
        $assignmentDao = DAORegistry::getDAO('NavigationMenuItemAssignmentDAO');

        // Delete all existing assignments for this menu
        $assignmentDao->deleteByMenuId($navigationMenuId);

        // Create new assignments from the provided structure
        $this->saveAssignmentsRecursively($assignedItems, $navigationMenuId, null, 0);

        // Return updated items
        $updatedAssigned = $this->getAssignedItemsTree($navigationMenuId, $navigationMenu);
        $updatedUnassigned = $this->getUnassignedItems($contextId, $navigationMenuId, $navigationMenu);

        return response()->json([
            'assigned' => $updatedAssigned,
            'unassigned' => $updatedUnassigned,
        ], Response::HTTP_OK);
    }

    /**
     * Recursively save menu item assignments
     */
    protected function saveAssignmentsRecursively(array $items, int $menuId, ?int $parentAssignmentId, int $startSeq): void
    {
        /** @var NavigationMenuItemAssignmentDAO $assignmentDao */
        $assignmentDao = DAORegistry::getDAO('NavigationMenuItemAssignmentDAO');

        $seq = $startSeq;
        foreach ($items as $item) {
            $assignment = $assignmentDao->newDataObject();
            $assignment->setMenuId($menuId);
            $assignment->setMenuItemId($item['menuItemId']);
            $assignment->setParentId($parentAssignmentId);
            $assignment->setSequence($seq);

            // Set custom title if provided
            if (!empty($item['localizedTitle'])) {
                $assignment->setTitle($item['localizedTitle'], null);
            }

            $assignmentId = $assignmentDao->insertObject($assignment);

            // Recursively save children
            if (!empty($item['children'])) {
                $this->saveAssignmentsRecursively($item['children'], $menuId, $assignmentId, 0);
            }

            $seq++;
        }
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

        $navigationMenuService = app(PKPNavigationMenuService::class);

        foreach ($byParentId[$parentId] as $assignment) {
            $menuItem = $menuItemDao->getById($assignment->getMenuItemId());
            if (!$menuItem) {
                continue;
            }

            // Build children first so we can check if there are any
            // Note: parent_id in the database stores the MENU ITEM ID of the parent, not the assignment ID
            $children = $this->buildAssignmentTree($byParentId, $menuItem->getId(), $navigationMenu, $menuItemDao);

            // Get conditional display info (for crossed-out eye icon)
            $conditionalInfo = $this->getItemConditionalInfo($menuItem, $navigationMenuService);

            // hasWarning = true when item has children (submenu warning)
            // isVisible = false when item has conditional display (crossed-out eye)
            $item = [
                'id' => $assignment->getId(),
                'menuItemId' => $menuItem->getId(),
                'assignmentId' => $assignment->getId(),
                'title' => $this->getItemTitle($assignment, $menuItem),
                'localizedTitle' => $assignment->getTitle(null) ?? $menuItem->getTitle(null) ?? [],
                'type' => $menuItem->getType(),
                'path' => $menuItem->getPath(),
                'url' => $menuItem->getUrl(),
                'isVisible' => !$conditionalInfo['hasConditionalDisplay'],
                'hasWarning' => count($children) > 0,
                'warningMessage' => count($children) > 0 ? __('manager.navigationMenus.form.submenuWarning') : null,
                'conditionalWarning' => $conditionalInfo['conditionalWarning'],
                'parentId' => $assignment->getParentId(),
                'sequence' => $assignment->getSequence(),
                'children' => $children,
            ];

            $result[] = $item;
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
        $navigationMenuService = app(PKPNavigationMenuService::class);
        $unassignedItems = [];

        foreach ($allItemsList as $itemId => $menuItem) {
            if (!isset($assignedItemIds[$itemId])) {
                // Get conditional display info (for crossed-out eye icon)
                $conditionalInfo = $this->getItemConditionalInfo($menuItem, $navigationMenuService);

                // Unassigned items can't have children, so hasWarning is always false
                $unassignedItems[] = [
                    'id' => $menuItem->getId(),
                    'menuItemId' => $menuItem->getId(),
                    'title' => $this->getMenuItemTitle($menuItem),
                    'localizedTitle' => $menuItem->getTitle(null) ?? [],
                    'type' => $menuItem->getType(),
                    'path' => $menuItem->getPath(),
                    'url' => $menuItem->getUrl(),
                    'isVisible' => !$conditionalInfo['hasConditionalDisplay'],
                    'hasWarning' => false,
                    'warningMessage' => null,
                    'conditionalWarning' => $conditionalInfo['conditionalWarning'],
                    'parentId' => null,
                    'children' => [],
                ];
            }
        }

        return $unassignedItems;
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
