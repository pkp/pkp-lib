<?php

/**
 * @file classes/navigationMenu/resources/NavigationMenuItemResource.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenuItemResource
 *
 * @brief Resource class for transforming NavigationMenuItem to API response format
 */

namespace PKP\navigationMenu\resources;

use APP\core\Application;
use Illuminate\Http\Resources\Json\JsonResource;
use PKP\facades\Locale;
use PKP\navigationMenu\NavigationMenuItem;
use PKP\navigationMenu\NavigationMenuItemAssignment;
use PKP\services\PKPNavigationMenuService;

class NavigationMenuItemResource extends JsonResource
{
    /** @var NavigationMenuItemAssignment|null The assignment (null for unassigned items) */
    protected ?NavigationMenuItemAssignment $assignment;

    /** @var array Pre-built children array for assigned items */
    protected array $children;

    /**
     * Create a new resource instance
     *
     * @param NavigationMenuItem $menuItem The menu item
     * @param NavigationMenuItemAssignment|null $assignment The assignment (null for unassigned items)
     * @param array $children Pre-built children array for assigned items
     */
    public function __construct(
        NavigationMenuItem $menuItem,
        ?NavigationMenuItemAssignment $assignment = null,
        array $children = []
    ) {
        parent::__construct($menuItem);
        $this->assignment = $assignment;
        $this->children = $children;
    }

    /**
     * Transform the resource into an array
     */
    public function toArray($request): array
    {
        /** @var NavigationMenuItem $menuItem */
        $menuItem = $this->resource;
        $assignment = $this->assignment;

        $conditionalInfo = $this->getItemConditionalInfo($menuItem);

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
            'hasWarning' => count($this->children) > 0,
            'warningMessage' => count($this->children) > 0 ? __('manager.navigationMenus.form.submenuWarning') : null,
            'conditionalWarning' => $conditionalInfo['conditionalWarning'],
            'parentId' => $assignment?->getParentId(),
            'sequence' => $assignment?->getSequence(),
            'children' => $this->children,
        ];
    }

    /**
     * Get conditional display info for a menu item based on its type
     */
    protected function getItemConditionalInfo(NavigationMenuItem $menuItem): array
    {
        $navigationMenuService = app(PKPNavigationMenuService::class);
        $itemTypes = $navigationMenuService->getMenuItemTypes();
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
        $navigationMenuService = app(PKPNavigationMenuService::class);
        $navigationMenuService->setAllNMILocalizedTitles($menuItem);

        // Try assignment-specific title first (custom override)
        $assignmentTitles = $assignment->getTitle(null);
        if (is_array($assignmentTitles) && !empty($assignmentTitles)) {
            $locale = Locale::getLocale();
            if (isset($assignmentTitles[$locale]) && !empty($assignmentTitles[$locale])) {
                return $this->transformTitleVariables($assignmentTitles[$locale]);
            }
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
     * Get the display title for a menu item
     */
    protected function getMenuItemTitle(NavigationMenuItem $menuItem): string
    {
        $navigationMenuService = app(PKPNavigationMenuService::class);
        $navigationMenuService->setAllNMILocalizedTitles($menuItem);

        $title = $menuItem->getLocalizedTitle();
        if (!empty($title)) {
            return $this->transformTitleVariables($title);
        }

        $itemTypes = $navigationMenuService->getMenuItemTypes();
        $type = $menuItem->getType();

        if (isset($itemTypes[$type]['title'])) {
            return $itemTypes[$type]['title'];
        }

        return $type ?? '';
    }

    /**
     * Transform template variables in title
     */
    protected function transformTitleVariables(string $title): string
    {
        if (strpos($title, '{$loggedInUsername}') !== false) {
            $request = Application::get()->getRequest();
            $user = $request->getUser();
            if ($user) {
                $title = str_replace('{$loggedInUsername}', $user->getUsername(), $title);
            }
        }

        return $title;
    }
}
