<?php

/**
 * @file classes/navigationMenu/NavigationMenuItemAssignment.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenuItemAssignment
 * @ingroup navigationMenu
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
    /** @var NavigationMenuItem $navigationMenuItem The object this assignment refers to */
    public $navigationMenuItem = null;

    /** @var array $children List of NavigationMenuItem objects nested under this one. */
    public $children = [];

    //
    // Get/set methods
    //
    /**
     * Get menuId for this navigation menu item assignment.
     *
     * @return int
     */
    public function getMenuId()
    {
        return $this->getData('menuId');
    }

    /**
     * Set menuId for this navigation menu item assignment.
     *
     * @param int $menuId
     */
    public function setMenuId($menuId)
    {
        $this->setData('menuId', $menuId);
    }

    /**
     * Get menuItemId for this navigation menu item assignment.
     *
     * @return int
     */
    public function getMenuItemId()
    {
        return $this->getData('menuItemId');
    }

    /**
     * Set menuItemId for this navigation menu item assignment.
     *
     * @param int $menuItemId
     */
    public function setMenuItemId($menuItemId)
    {
        $this->setData('menuItemId', $menuItemId);
    }

    /**
     * Get parent menu item ID
     *
     * @return int
     */
    public function getParentId()
    {
        return $this->getData('parentId');
    }

    /**
     * Set parent menu item ID
     *
     * @param int $parentId
     */
    public function setParentId($parentId)
    {
        $this->setData('parentId', $parentId);
    }

    /**
     * Get seq for this navigation menu item.
     *
     * @return int
     */
    public function getSequence()
    {
        return $this->getData('seq');
    }

    /**
     * Set seq for this navigation menu item.
     *
     * @param int $seq
     */
    public function setSequence($seq)
    {
        $this->setData('seq', $seq);
    }

    /**
     * Get the NavigationMenuItem this assignment represents.
     *
     * This object is only available in some cases, when the NavigationMenuItem
     * has been stored for re-use.
     *
     * @return int
     */
    public function getMenuItem()
    {
        return $this->navigationMenuItem;
    }

    /**
     * Set the NavigationMenuItem this assignment represents
     */
    public function setMenuItem($obj)
    {
        $this->navigationMenuItem = $obj instanceof \PKP\navigationMenu\NavigationMenuItem ? $obj : null;
    }

    /**
     * Get the title of the object.
     *
     * @return string
     */
    public function getLocalizedTitle()
    {
        return $this->getLocalizedData('title');
    }

    /**
     * Get the title of the object.
     *
     * @param string $locale
     *
     * @return string
     */
    public function getTitle($locale)
    {
        return $this->getData('title', $locale);
    }

    /**
     * Set the title of the object.
     *
     * @param string $title
     * @param string $locale
     */
    public function setTitle($title, $locale)
    {
        $this->setData('title', $title, $locale);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\navigationMenu\NavigationMenuItemAssignment', '\NavigationMenuItemAssignment');
}
