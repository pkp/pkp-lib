<?php

/**
 * @file classes/navigationMenu/NavigationMenu.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenu
 * @ingroup navigationMenu
 *
 * @see NavigationMenuDAO
 *
 * @brief Class describing a NavigationMenu.
 */

namespace PKP\navigationMenu;

class NavigationMenu extends \PKP\core\DataObject
{
    /** @var array $menuTree Hierarchical array of NavigationMenuItems */
    public $menuTree = null;

    //
    // Get/set methods
    //

    /**
     * Get contextId of this NavigationMenu
     *
     * @return int
     */
    public function getContextId()
    {
        return $this->getData('contextId');
    }

    /**
     * Set contextId of this NavigationMenu
     *
     * @param int $contextId
     */
    public function setContextId($contextId)
    {
        $this->setData('contextId', $contextId);
    }

    /**
     * Get title of this NavigationMenu. Not localized.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->getData('title');
    }

    /**
     * Set title of this NavigationMenu. Not localized.
     *
     * @param string $title
     */
    public function setTitle($title)
    {
        $this->setData('title', $title);
    }

    /**
     * Get areaName of this NavigationMenu. Not localized.
     *
     * @return string
     */
    public function getAreaName()
    {
        return $this->getData('areaName');
    }

    /**
     * Set navigationArea name of this NavigationMenu. Not localized.
     *
     * @param string $areaName
     */
    public function setAreaName($areaName)
    {
        $this->setData('areaName', $areaName);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\navigationMenu\NavigationMenu', '\NavigationMenu');
}
