<?php

/**
 * @file classes/navigationMenu/NavigationMenuItem.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenuItem
 *
 * @see NavigationMenuItemDAO
 *
 * @brief Basic class describing a NavigationMenuItem.
 */

namespace PKP\navigationMenu;

class NavigationMenuItem extends \PKP\core\DataObject
{
    // Types for all default navigationMenuItems
    public const NMI_TYPE_ABOUT = 'NMI_TYPE_ABOUT';
    public const NMI_TYPE_SUBMISSIONS = 'NMI_TYPE_SUBMISSIONS';
    public const NMI_TYPE_MASTHEAD = 'NMI_TYPE_MASTHEAD';
    public const NMI_TYPE_CONTACT = 'NMI_TYPE_CONTACT';
    public const NMI_TYPE_ANNOUNCEMENTS = 'NMI_TYPE_ANNOUNCEMENTS';
    public const NMI_TYPE_CUSTOM = 'NMI_TYPE_CUSTOM';
    public const NMI_TYPE_REMOTE_URL = 'NMI_TYPE_REMOTE_URL';

    public const NMI_TYPE_USER_LOGOUT = 'NMI_TYPE_USER_LOGOUT';
    public const NMI_TYPE_USER_LOGOUT_AS = 'NMI_TYPE_USER_LOGOUT_AS';
    public const NMI_TYPE_USER_PROFILE = 'NMI_TYPE_USER_PROFILE';
    public const NMI_TYPE_ADMINISTRATION = 'NMI_TYPE_ADMINISTRATION';
    public const NMI_TYPE_USER_DASHBOARD = 'NMI_TYPE_USER_DASHBOARD';
    public const NMI_TYPE_USER_REGISTER = 'NMI_TYPE_USER_REGISTER';
    public const NMI_TYPE_USER_LOGIN = 'NMI_TYPE_USER_LOGIN';
    public const NMI_TYPE_SEARCH = 'NMI_TYPE_SEARCH';
    public const NMI_TYPE_PRIVACY = 'NMI_TYPE_PRIVACY';

    /** @var array $navigationMenuItems The navigationMenuItems underneath this navigationMenuItem */
    public array $navigationMenuItems = [];

    public bool $_isDisplayed = true;
    public bool $_isChildVisible = false;

    //
    // Get/set methods
    //

    /**
     * Set path for this navigation menu item.
     */
    public function setPath(?string $path): void
    {
        $this->setData('path', $path);
    }

    /**
     * Get path for this navigation menu item.
     */
    public function getPath(): ?string
    {
        return $this->getData('path');
    }

    /**
     * Set url for this navigation menu item.
     */
    public function setUrl(string $url): void
    {
        $this->setData('url', $url);
    }

    /**
     * Get url for this navigation menu item.
     */
    public function getUrl(): string
    {
        return $this->getData('url');
    }

    /**
     * Set type for this navigation menu item.
     */
    public function setType(string $type): void
    {
        $this->setData('type', $type);
    }

    /**
     * Get type for this navigation menu item.
     */
    public function getType(): string
    {
        return $this->getData('type');
    }

    /**
     * Get contextId for this navigation menu item.
     */
    public function getContextId(): ?int
    {
        return $this->getData('contextId');
    }

    /**
     * Set context_id for this navigation menu item.
     */
    public function setContextId(?int $contextId): void
    {
        $this->setData('contextId', $contextId);
    }

    /**
     * Get the title of the navigation Menu.
     */
    public function getLocalizedTitle(): ?string
    {
        return $this->getLocalizedData('title');
    }

    /**
     * Get the title of the navigation menu item.
     */
    public function getTitle(?string $locale = null): null|string|array
    {
        return $this->getData('title', $locale);
    }

    /**
     * Set the title of the navigation menu item.
     */
    public function setTitle(null|array|string $title, ?string $locale): void
    {
        $this->setData('title', $title, $locale);
    }

    /**
     * Get the content of the navigation Menu.
     */
    public function getLocalizedContent(): ?string
    {
        return $this->getLocalizedData('content');
    }

    /**
     * Get the content of the navigation menu item.
     */
    public function getContent(?string $locale): null|string|array
    {
        return $this->getData('content', $locale);
    }

    /**
     * Set the content of the navigation menu item.
     */
    public function setContent(string|array $content, ?string $locale): void
    {
        $this->setData('content', $content, $locale);
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
     * Get $isDisplayed for this navigation menu item.
     */
    public function getIsDisplayed(): bool
    {
        return $this->_isDisplayed;
    }

    /**
     * Set $isDisplayed for this navigation menu item.
     */
    public function setIsDisplayed(bool $isDisplayed): void
    {
        $this->_isDisplayed = $isDisplayed;
    }

    /**
     * Get $isChildVisible for this navigation menu item.
     *
     * @return bool True if at least one NMI child is visible. It is defined at the Service functionality level
     */
    public function getIsChildVisible(): bool
    {
        return $this->_isChildVisible;
    }

    /**
     * Set $isChildVisible for this navigation menu item.
     *
     * @param bool $isChildVisible true if at least one NMI child is visible. It is defined at the Service functionality level
     */
    public function setIsChildVisible(bool $isChildVisible): void
    {
        $this->_isChildVisible = $isChildVisible;
    }

    /**
     * Get the titleLocaleKey of the navigation Menu.
     */
    public function getTitleLocaleKey(): ?string
    {
        return $this->getData('titleLocaleKey');
    }

    /**
     * Set titleLocaleKey for this navigation menu item.
     */
    public function setTitleLocaleKey(string $titleLocaleKey): void
    {
        $this->setData('titleLocaleKey', $titleLocaleKey);
    }

    /**
     * Get the remoteUrl of the navigation Menu.
     */
    public function getLocalizedRemoteUrl(): ?string
    {
        return $this->getLocalizedData('remoteUrl');
    }

    /**
     * Get the remoteUrl of the navigation menu item.
     */
    public function getRemoteUrl(?string $locale): array|string|null
    {
        return $this->getData('remoteUrl', $locale);
    }

    /**
     * Set the remoteUrl of the navigation menu item.
     */
    public function setRemoteUrl(array|string $url, ?string $locale): void
    {
        $this->setData('remoteUrl', $url, $locale);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\navigationMenu\NavigationMenuItem', '\NavigationMenuItem');
    foreach ([
        'NMI_TYPE_ABOUT',
        'NMI_TYPE_SUBMISSIONS',
        'NMI_TYPE_MASTHEAD',
        'NMI_TYPE_CONTACT',
        'NMI_TYPE_ANNOUNCEMENTS',
        'NMI_TYPE_CUSTOM',
        'NMI_TYPE_REMOTE_URL',
        'NMI_TYPE_USER_LOGOUT',
        'NMI_TYPE_USER_LOGOUT_AS',
        'NMI_TYPE_USER_PROFILE',
        'NMI_TYPE_ADMINISTRATION',
        'NMI_TYPE_USER_DASHBOARD',
        'NMI_TYPE_USER_REGISTER',
        'NMI_TYPE_USER_LOGIN',
        'NMI_TYPE_SEARCH',
        'NMI_TYPE_PRIVACY',
    ] as $constantName) {
        define($constantName, constant('\NavigationMenuItem::' . $constantName));
    }
}
