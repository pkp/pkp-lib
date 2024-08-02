<?php

/**
 * @file classes/navigationMenu/NavigationMenu.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenu
 *
 * @see NavigationMenuDAO
 *
 * @brief Class describing a NavigationMenu.
 */

namespace PKP\navigationMenu;

class NavigationMenu extends \PKP\core\DataObject
{
    /** @var array $menuTree Hierarchical array of NavigationMenuItems */
    public ?array $menuTree = null;

    //
    // Get/set methods
    //

    /**
     * Get contextId of this NavigationMenu
     */
    public function getContextId(): ?int
    {
        return $this->getData('contextId');
    }

    /**
     * Set contextId of this NavigationMenu
     */
    public function setContextId(?int $contextId): void
    {
        $this->setData('contextId', $contextId);
    }

    /**
     * Get title of this NavigationMenu. Not localized.
     */
    public function getTitle(): string
    {
        return $this->getData('title') ?? '';
    }

    /**
     * Set title of this NavigationMenu. Not localized.
     */
    public function setTitle(string $title): void
    {
        $this->setData('title', $title);
    }

    /**
     * Get areaName of this NavigationMenu. Not localized.
     */
    public function getAreaName(): string
    {
        return $this->getData('areaName') ?? '';
    }

    /**
     * Set navigationArea name of this NavigationMenu. Not localized.
     */
    public function setAreaName(string $areaName): void
    {
        $this->setData('areaName', $areaName);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\navigationMenu\NavigationMenu', '\NavigationMenu');
}
