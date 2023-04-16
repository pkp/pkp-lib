<?php

/**
 * @file classes/navigationMenu/NavigationMenuItem.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenuItem
 *
 * @ingroup navigationMenu
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
    public const NMI_TYPE_EDITORIAL_TEAM = 'NMI_TYPE_EDITORIAL_TEAM';
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
    public $navigationMenuItems = [];

    public $_isDisplayed = true;
    public $_isChildVisible = false;

    //
    // Get/set methods
    //

    /**
     * Set path for this navigation menu item.
     *
     * @param string $path
     */
    public function setPath($path)
    {
        $this->setData('path', $path);
    }

    /**
     * Get path for this navigation menu item.
     *
     * @return string
     */
    public function getPath()
    {
        return $this->getData('path');
    }

    /**
     * Set url for this navigation menu item.
     *
     * @param string $url
     */
    public function setUrl($url)
    {
        $this->setData('url', $url);
    }

    /**
     * Get url for this navigation menu item.
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->getData('url');
    }

    /**
     * Set type for this navigation menu item.
     *
     * @param string $type
     */
    public function setType($type)
    {
        $this->setData('type', $type);
    }

    /**
     * Get type for this navigation menu item.
     *
     * @return string
     */
    public function getType()
    {
        return $this->getData('type');
    }

    /**
     * Get contextId for this navigation menu item.
     *
     * @return int
     */
    public function getContextId()
    {
        return $this->getData('contextId');
    }

    /**
     * Set context_id for this navigation menu item.
     *
     * @param int $contextId
     */
    public function setContextId($contextId)
    {
        $this->setData('contextId', $contextId);
    }

    /**
     * Get the title of the navigation Menu.
     *
     * @return string
     */
    public function getLocalizedTitle()
    {
        return $this->getLocalizedData('title');
    }

    /**
     * Get the title of the navigation menu item.
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
     * Set the title of the navigation menu item.
     *
     * @param string $title
     * @param string $locale
     */
    public function setTitle($title, $locale)
    {
        $this->setData('title', $title, $locale);
    }

    /**
     * Get the content of the navigation Menu.
     *
     * @return string
     */
    public function getLocalizedContent()
    {
        return $this->getLocalizedData('content');
    }

    /**
     * Get the content of the navigation menu item.
     *
     * @param string $locale
     *
     * @return string
     */
    public function getContent($locale)
    {
        return $this->getData('content', $locale);
    }

    /**
     * Set the content of the navigation menu item.
     *
     * @param string $content
     * @param string $locale
     */
    public function setContent($content, $locale)
    {
        $this->setData('content', $content, $locale);
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
     * Get $isDisplayed for this navigation menu item.
     *
     * @return bool
     */
    public function getIsDisplayed()
    {
        return $this->_isDisplayed;
    }

    /**
     * Set $isDisplayed for this navigation menu item.
     *
     * @param bool $isDisplayed
     */
    public function setIsDisplayed($isDisplayed)
    {
        $this->_isDisplayed = $isDisplayed;
    }

    /**
     * Get $isChildVisible for this navigation menu item.
     *
     * @return bool true if at least one NMI child is visible. It is defined at the Service functionality level
     */
    public function getIsChildVisible()
    {
        return $this->_isChildVisible;
    }

    /**
     * Set $isChildVisible for this navigation menu item.
     *
     * @param bool $isChildVisible true if at least one NMI child is visible. It is defined at the Service functionality level
     */
    public function setIsChildVisible($isChildVisible)
    {
        $this->_isChildVisible = $isChildVisible;
    }

    /**
     * Get the titleLocaleKey of the navigation Menu.
     *
     * @return string
     */
    public function getTitleLocaleKey()
    {
        return $this->getData('titleLocaleKey');
    }

    /**
     * Set titleLocaleKey for this navigation menu item.
     *
     * @param string $titleLocaleKey
     */
    public function setTitleLocaleKey($titleLocaleKey)
    {
        return $this->setData('titleLocaleKey', $titleLocaleKey);
    }

    /**
     * Get the remoteUrl of the navigation Menu.
     *
     * @return string
     */
    public function getLocalizedRemoteUrl()
    {
        return $this->getLocalizedData('remoteUrl');
    }

    /**
     * Get the remoteUrl of the navigation menu item.
     *
     * @param string $locale
     */
    public function getRemoteUrl($locale)
    {
        return $this->getData('remoteUrl', $locale);
    }

    /**
     * Set the remoteUrl of the navigation menu item.
     *
     * @param string $url
     * @param string $locale
     */
    public function setRemoteUrl($url, $locale)
    {
        $this->setData('remoteUrl', $url, $locale);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\navigationMenu\NavigationMenuItem', '\NavigationMenuItem');
    foreach ([
        'NMI_TYPE_ABOUT',
        'NMI_TYPE_SUBMISSIONS',
        'NMI_TYPE_EDITORIAL_TEAM',
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
