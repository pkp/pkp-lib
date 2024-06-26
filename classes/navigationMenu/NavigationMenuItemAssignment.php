<?php

/**
 * @file classes/navigationMenu/NavigationMenuItemAssignment.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenuItemAssignment
 *
 * @see NavigationMenuItemAssignmentDAO
 *
 * @brief Basic class describing a NavigationMenuItemAssignment. Each
 *  assignment describes a NavigationMenuItem assigned to a NavigationMenu,
 *  including it's position and if it's nested within another NavigationMenuItem
 */

namespace PKP\navigationMenu;

class NavigationMenuItemAssignment extends \PKP\core\DataObject
{
    /** @var ?NavigationMenuItem The object this assignment refers to */
    public ?NavigationMenuItem $navigationMenuItem = null;

    /** @var array List of NavigationMenuItem objects nested under this one. */
    public array $children = [];

    //
    // Get/set methods
    //
    /**
     * Get menuId for this navigation menu item assignment.
     */
    public function getMenuId(): int
    {
        return $this->getData('menuId');
    }

    /**
     * Set menuId for this navigation menu item assignment.
     */
    public function setMenuId(int $menuId): void
    {
        $this->setData('menuId', $menuId);
    }

    /**
     * Get menuItemId for this navigation menu item assignment.
     */
    public function getMenuItemId(): int
    {
        return $this->getData('menuItemId');
    }

    /**
     * Set menuItemId for this navigation menu item assignment.
     *
     */
    public function setMenuItemId(int $menuItemId): void
    {
        $this->setData('menuItemId', $menuItemId);
    }

    /**
     * Get parent menu item ID
     */
    public function getParentId(): ?int
    {
        return $this->getData('parentId');
    }

    /**
     * Set parent menu item ID
     */
    public function setParentId(?int $parentId): void
    {
        $this->setData('parentId', $parentId);
    }

    /**
     * Get seq for this navigation menu item.
     */
    public function getSequence(): int
    {
        return $this->getData('seq');
    }

    /**
     * Set seq for this navigation menu item.
     */
    public function setSequence(int $seq): void
    {
        $this->setData('seq', $seq);
    }

    /**
     * Get the NavigationMenuItem this assignment represents.
     *
     * This object is only available in some cases, when the NavigationMenuItem
     * has been stored for re-use.
     */
    public function getMenuItem(): ?NavigationMenuItem
    {
        return $this->navigationMenuItem;
    }

    /**
     * Set the NavigationMenuItem this assignment represents
     */
    public function setMenuItem(?NavigationMenuItem $navigationMenuItem)
    {
        $this->navigationMenuItem = $navigationMenuItem;
    }

    /**
     * Get the title of the object.
     */
    public function getLocalizedTitle(): string
    {
        return $this->getLocalizedData('title');
    }

    /**
     * Get the title of the object.
     */
    public function getTitle(?string $locale): null|array|string
    {
        return $this->getData('title', $locale);
    }

    /**
     * Set the title of the object.
     */
    public function setTitle(string|array $title, ?string $locale): void
    {
        $this->setData('title', $title, $locale);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\navigationMenu\NavigationMenuItemAssignment', '\NavigationMenuItemAssignment');
}
