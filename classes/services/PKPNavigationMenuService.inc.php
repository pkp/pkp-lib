<?php

/**
 * @file classes/services/PKPNavigationMenuService.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPNavigationMenuService
 * @ingroup services
 *
 * @brief Helper class that encapsulates NavigationMenu business logic
 */

namespace PKP\services;

use APP\core\Application;
use PKP\facades\Locale;
use APP\template\TemplateManager;

use NavigationMenuItemHandler;

use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\navigationMenu\NavigationMenu;
use PKP\navigationMenu\NavigationMenuItem;
use PKP\navigationMenu\NavigationMenuItemAssignment;
use PKP\plugins\HookRegistry;
use PKP\security\Role;
use PKP\security\Validation;

class PKPNavigationMenuService
{
    /**
     * Return all default navigationMenuItemTypes.
     *
     * @return array
     */
    public function getMenuItemTypes()
    {
        AppLocale::requireComponents(LOCALE_COMPONENT_APP_COMMON, LOCALE_COMPONENT_PKP_USER);
        $types = [
            NavigationMenuItem::NMI_TYPE_CUSTOM => [
                'title' => __('manager.navigationMenus.customPage'),
                'description' => __('manager.navigationMenus.customPage.description'),
            ],
            NavigationMenuItem::NMI_TYPE_REMOTE_URL => [
                'title' => __('manager.navigationMenus.remoteUrl'),
                'description' => __('manager.navigationMenus.remoteUrl.description'),
            ],
            NavigationMenuItem::NMI_TYPE_ABOUT => [
                'title' => __('navigation.about'),
                'description' => __('manager.navigationMenus.about.description'),
                'conditionalWarning' => __('manager.navigationMenus.about.conditionalWarning'),
            ],
            NavigationMenuItem::NMI_TYPE_EDITORIAL_TEAM => [
                'title' => __('about.editorialTeam'),
                'description' => __('manager.navigationMenus.editorialTeam.description'),
                'conditionalWarning' => __('manager.navigationMenus.editorialTeam.conditionalWarning'),
            ],
            NavigationMenuItem::NMI_TYPE_SUBMISSIONS => [
                'title' => __('about.submissions'),
                'description' => __('manager.navigationMenus.submissions.description'),
            ],
            NavigationMenuItem::NMI_TYPE_ANNOUNCEMENTS => [
                'title' => __('announcement.announcements'),
                'description' => __('manager.navigationMenus.announcements.description'),
                'conditionalWarning' => __('manager.navigationMenus.announcements.conditionalWarning'),
            ],
            NavigationMenuItem::NMI_TYPE_USER_LOGIN => [
                'title' => __('navigation.login'),
                'description' => __('manager.navigationMenus.login.description'),
                'conditionalWarning' => __('manager.navigationMenus.loggedIn.conditionalWarning'),
            ],
            NavigationMenuItem::NMI_TYPE_USER_REGISTER => [
                'title' => __('navigation.register'),
                'description' => __('manager.navigationMenus.register.description'),
                'conditionalWarning' => __('manager.navigationMenus.loggedIn.conditionalWarning'),
            ],
            NavigationMenuItem::NMI_TYPE_USER_DASHBOARD => [
                'title' => __('navigation.dashboard'),
                'description' => __('manager.navigationMenus.dashboard.description'),
                'conditionalWarning' => __('manager.navigationMenus.loggedOut.conditionalWarning'),
            ],
            NavigationMenuItem::NMI_TYPE_USER_PROFILE => [
                'title' => __('common.viewProfile'),
                'description' => __('manager.navigationMenus.profile.description'),
                'conditionalWarning' => __('manager.navigationMenus.loggedOut.conditionalWarning'),
            ],
            NavigationMenuItem::NMI_TYPE_ADMINISTRATION => [
                'title' => __('navigation.admin'),
                'description' => __('manager.navigationMenus.administration.description'),
                'conditionalWarning' => __('manager.navigationMenus.loggedOut.conditionalWarning'),
            ],
            NavigationMenuItem::NMI_TYPE_USER_LOGOUT => [
                'title' => __('user.logOut'),
                'description' => __('manager.navigationMenus.logOut.description'),
                'conditionalWarning' => __('manager.navigationMenus.loggedOut.conditionalWarning'),
            ],
            NavigationMenuItem::NMI_TYPE_CONTACT => [
                'title' => __('about.contact'),
                'description' => __('manager.navigationMenus.contact.description'),
                'conditionalWarning' => __('manager.navigationMenus.contact.conditionalWarning'),
            ],
            NavigationMenuItem::NMI_TYPE_SEARCH => [
                'title' => __('common.search'),
                'description' => __('manager.navigationMenus.search.description'),
            ],
            NavigationMenuItem::NMI_TYPE_PRIVACY => [
                'title' => __('manager.setup.privacyStatement'),
                'description' => __('manager.navigationMenus.privacyStatement.description'),
                'conditionalWarning' => __('manager.navigationMenus.privacyStatement.conditionalWarning'),
            ],
        ];

        HookRegistry::call('NavigationMenus::itemTypes', [&$types]);

        return $types;
    }

    /**
     * Return all custom edit navigationMenuItemTypes Templates.
     *
     * @return array
     */
    public function getMenuItemCustomEditTemplates()
    {
        $templates = [
            NavigationMenuItem::NMI_TYPE_CUSTOM => [
                'template' => 'core:controllers/grid/navigationMenus/customNMIType.tpl',
            ],
            NavigationMenuItem::NMI_TYPE_REMOTE_URL => [
                'template' => 'core:controllers/grid/navigationMenus/remoteUrlNMIType.tpl',
            ],
        ];

        HookRegistry::call('NavigationMenus::itemCustomTemplates', [&$templates]);

        return $templates;
    }

    /**
     * Callback for display menu item functionallity
     */
    public function getDisplayStatus(&$navigationMenuItem, &$navigationMenu)
    {
        $request = Application::get()->getRequest();
        $dispatcher = $request->getDispatcher();
        $templateMgr = TemplateManager::getManager($request);

        $isUserLoggedIn = Validation::isLoggedIn();
        $isUserLoggedInAs = Validation::isLoggedInAs();
        $context = $request->getContext();
        $currentUser = $request->getUser();

        $contextId = $context ? $context->getId() : \PKP\core\PKPApplication::CONTEXT_ID_NONE;

        // Transform an item title if the title includes a {$variable}
        $this->transformNavMenuItemTitle($templateMgr, $navigationMenuItem);

        $menuItemType = $navigationMenuItem->getType();

        // Conditionally hide some items
        switch ($menuItemType) {
            case NavigationMenuItem::NMI_TYPE_ANNOUNCEMENTS:
                $navigationMenuItem->setIsDisplayed($context && $context->getData('enableAnnouncements'));
                break;
            case NavigationMenuItem::NMI_TYPE_EDITORIAL_TEAM:
                $navigationMenuItem->setIsDisplayed($context && $context->getLocalizedData('editorialTeam'));
                break;
            case NavigationMenuItem::NMI_TYPE_CONTACT:
                $navigationMenuItem->setIsDisplayed($context && ($context->getData('mailingAddress') || $context->getData('contactName')));
                break;
            case NavigationMenuItem::NMI_TYPE_USER_REGISTER:
                $navigationMenuItem->setIsDisplayed(!$isUserLoggedIn && !($context && $context->getData('disableUserReg')));
                break;
            case NavigationMenuItem::NMI_TYPE_USER_LOGIN:
                $navigationMenuItem->setIsDisplayed(!$isUserLoggedIn);
                break;
            case NavigationMenuItem::NMI_TYPE_USER_LOGOUT:
            case NavigationMenuItem::NMI_TYPE_USER_PROFILE:
            case NavigationMenuItem::NMI_TYPE_USER_DASHBOARD:
                $navigationMenuItem->setIsDisplayed($isUserLoggedIn);
                break;
            case NavigationMenuItem::NMI_TYPE_ADMINISTRATION:
                $navigationMenuItem->setIsDisplayed($isUserLoggedIn && $currentUser->hasRole([Role::ROLE_ID_SITE_ADMIN], \PKP\core\PKPApplication::CONTEXT_SITE));
                break;
            case NavigationMenuItem::NMI_TYPE_SEARCH:
                $navigationMenuItem->setIsDisplayed($context);
                break;
            case NavigationMenuItem::NMI_TYPE_PRIVACY:
                $navigationMenuItem->setIsDisplayed($context && $context->getLocalizedData('privacyStatement'));
                break;
        }

        if ($navigationMenuItem->getIsDisplayed()) {
            // Adjust some titles
            switch ($menuItemType) {
                case NavigationMenuItem::NMI_TYPE_USER_LOGOUT:
                    if ($isUserLoggedInAs) {
                        $userName = $request->getUser() ? ' ' . $request->getUser()->getUserName() : '';
                        $navigationMenuItem->setTitle(__('user.logOutAs', ['username' => $userName]), Locale::getLocale());
                    }
                    break;
                case NavigationMenuItem::NMI_TYPE_USER_DASHBOARD:
                    $templateMgr->assign('navigationMenuItem', $navigationMenuItem);
                    if ($currentUser->hasRole([Role::ROLE_ID_MANAGER, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_REVIEWER, Role::ROLE_ID_AUTHOR], $contextId) || $currentUser->hasRole([Role::ROLE_ID_SITE_ADMIN], \PKP\core\PKPApplication::CONTEXT_SITE)) {
                        $displayTitle = $templateMgr->fetch('frontend/components/navigationMenus/dashboardMenuItem.tpl');
                        $navigationMenuItem->setTitle($displayTitle, Locale::getLocale());
                    }
                    break;
            }

            // Set the URL
            switch ($menuItemType) {
                case NavigationMenuItem::NMI_TYPE_ANNOUNCEMENTS:
                    $navigationMenuItem->setUrl($dispatcher->url(
                        $request,
                        PKPApplication::ROUTE_PAGE,
                        null,
                        'announcement',
                        null,
                        null
                    ));
                    break;
                case NavigationMenuItem::NMI_TYPE_ABOUT:
                    $navigationMenuItem->setUrl($dispatcher->url(
                        $request,
                        PKPApplication::ROUTE_PAGE,
                        null,
                        'about',
                        null,
                        null
                    ));
                    break;
                case NavigationMenuItem::NMI_TYPE_SUBMISSIONS:
                    $navigationMenuItem->setUrl($dispatcher->url(
                        $request,
                        PKPApplication::ROUTE_PAGE,
                        null,
                        'about',
                        'submissions',
                        null
                    ));
                    break;
                case NavigationMenuItem::NMI_TYPE_EDITORIAL_TEAM:
                    $navigationMenuItem->setUrl($dispatcher->url(
                        $request,
                        PKPApplication::ROUTE_PAGE,
                        null,
                        'about',
                        'editorialTeam',
                        null
                    ));
                    break;
                case NavigationMenuItem::NMI_TYPE_CONTACT:
                    $navigationMenuItem->setUrl($dispatcher->url(
                        $request,
                        PKPApplication::ROUTE_PAGE,
                        null,
                        'about',
                        'contact',
                        null
                    ));
                    break;
                case NavigationMenuItem::NMI_TYPE_USER_LOGOUT:
                    $navigationMenuItem->setUrl($dispatcher->url(
                        $request,
                        PKPApplication::ROUTE_PAGE,
                        null,
                        'login',
                        $isUserLoggedInAs ? 'signOutAsUser' : 'signOut',
                        null
                    ));
                    break;
                case NavigationMenuItem::NMI_TYPE_USER_PROFILE:
                    $navigationMenuItem->setUrl($dispatcher->url(
                        $request,
                        PKPApplication::ROUTE_PAGE,
                        null,
                        'user',
                        'profile',
                        null
                    ));
                    break;
                case NavigationMenuItem::NMI_TYPE_ADMINISTRATION:
                    $navigationMenuItem->setUrl($dispatcher->url(
                        $request,
                        PKPApplication::ROUTE_PAGE,
                        'index',
                        'admin',
                        'index',
                        null
                    ));
                    break;
                case NavigationMenuItem::NMI_TYPE_USER_DASHBOARD:
                    if ($currentUser->hasRole([Role::ROLE_ID_MANAGER, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_REVIEWER, Role::ROLE_ID_AUTHOR], $contextId) || $currentUser->hasRole([Role::ROLE_ID_SITE_ADMIN], \PKP\core\PKPApplication::CONTEXT_SITE)) {
                        $navigationMenuItem->setUrl($dispatcher->url(
                            $request,
                            PKPApplication::ROUTE_PAGE,
                            null,
                            'submissions',
                            null,
                            null
                        ));
                    } else {
                        $navigationMenuItem->setUrl($dispatcher->url(
                            $request,
                            PKPApplication::ROUTE_PAGE,
                            null,
                            'user',
                            'profile',
                            null
                        ));
                    }

                    break;
                case NavigationMenuItem::NMI_TYPE_USER_REGISTER:
                    $navigationMenuItem->setUrl($dispatcher->url(
                        $request,
                        PKPApplication::ROUTE_PAGE,
                        null,
                        'user',
                        'register',
                        null
                    ));
                    break;
                case NavigationMenuItem::NMI_TYPE_USER_LOGIN:
                    $navigationMenuItem->setUrl($dispatcher->url(
                        $request,
                        PKPApplication::ROUTE_PAGE,
                        null,
                        'login',
                        null,
                        null
                    ));
                    break;
                case NavigationMenuItem::NMI_TYPE_CUSTOM:
                    if ($navigationMenuItem->getPath()) {
                        $path = explode('/', $navigationMenuItem->getPath());
                        $page = array_shift($path);
                        $op = array_shift($path);
                        $navigationMenuItem->setUrl($dispatcher->url(
                            $request,
                            PKPApplication::ROUTE_PAGE,
                            null,
                            $page,
                            $op,
                            $path
                        ));
                    }
                    break;
                case NavigationMenuItem::NMI_TYPE_SEARCH:
                    $navigationMenuItem->setUrl($dispatcher->url(
                        $request,
                        PKPApplication::ROUTE_PAGE,
                        null,
                        'search',
                        null,
                        null
                    ));
                    break;
                case NavigationMenuItem::NMI_TYPE_PRIVACY:
                    $navigationMenuItem->setUrl($dispatcher->url(
                        $request,
                        PKPApplication::ROUTE_PAGE,
                        null,
                        'about',
                        'privacy',
                        null
                    ));
                    break;
                case NavigationMenuItem::NMI_TYPE_REMOTE_URL:
                    $navigationMenuItem->setUrl($navigationMenuItem->getLocalizedRemoteUrl());
                    break;
            }
        }

        HookRegistry::call('NavigationMenus::displaySettings', [$navigationMenuItem, $navigationMenu]);

        $templateMgr->assign('navigationMenuItem', $navigationMenuItem);
    }

    public function loadMenuTree(&$navigationMenu)
    {
        $navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO');
        $items = $navigationMenuItemDao->getByMenuId($navigationMenu->getId())->toArray();

        $navigationMenuItemAssignmentDao = DAORegistry::getDAO('NavigationMenuItemAssignmentDAO');
        $assignments = $navigationMenuItemAssignmentDao->getByMenuId($navigationMenu->getId())
            ->toArray();

        foreach ($assignments as $assignment) {
            foreach ($items as $item) {
                if ($item->getId() === $assignment->getMenuItemId()) {
                    $assignment->setMenuItem($item);
                    break;
                }
            }
        }

        // Create an array of parent items and array of child items sorted by
        // their parent id as the array key
        $navigationMenu->menuTree = [];
        $children = [];
        foreach ($assignments as $assignment) {
            if (!$assignment->getParentId()) {
                $navigationMenu->menuTree[] = $assignment;
            } else {
                if (!isset($children[$assignment->getParentId()])) {
                    $children[$assignment->getParentId()] = [];
                }

                $children[$assignment->getParentId()][] = $assignment;
            }
        }

        // Assign child items to parent in array
        for ($i = 0; $i < count($navigationMenu->menuTree); $i++) {
            $assignmentId = $navigationMenu->menuTree[$i]->getMenuItemId();
            if (isset($children[$assignmentId])) {
                $navigationMenu->menuTree[$i]->children = $children[$assignmentId];
            }
        }

        $navigationMenuDao = DAORegistry::getDAO('NavigationMenuDAO');
        $cache = $navigationMenuDao->getCache($navigationMenu->getId());
        $json = json_encode($navigationMenu);
        $cache->setEntireCache($json);
    }

    /**
     * Get a tree of NavigationMenuItems assigned to this menu
     *
     * @param NavigationMenu $navigationMenu
     *
     */
    public function getMenuTree(&$navigationMenu)
    {
        $navigationMenuDao = DAORegistry::getDAO('NavigationMenuDAO');
        $cache = $navigationMenuDao->getCache($navigationMenu->getId());
        if ($cache->cache) {
            $navigationMenu = json_decode($cache->cache, true);
            $navigationMenu = $this->arrayToObject('NavigationMenu', $navigationMenu);
            $this->loadMenuTreeDisplayState($navigationMenu);
            return;
        }
        $this->loadMenuTree($navigationMenu);
        $this->loadMenuTreeDisplayState($navigationMenu);
    }

    private function loadMenuTreeDisplayState(&$navigationMenu)
    {
        foreach ($navigationMenu->menuTree as $assignment) {
            $nmi = $assignment->getMenuItem();
            if ($assignment->children) {
                foreach ($assignment->children as $childAssignment) {
                    $childNmi = $childAssignment->getMenuItem();
                    $this->getDisplayStatus($childNmi, $navigationMenu);

                    if ($childNmi->getIsDisplayed()) {
                        $nmi->setIsChildVisible(true);
                    }
                }
            }
            $this->getDisplayStatus($nmi, $navigationMenu);
        }
    }

    /**
     * Helper function to transform the json_decoded cached NavigationMenu object (stdClass) to the actual NavigationMenu object
     * Some changes on the NavigationMenu objects must be reflected here
     */
    public function arrayToObject($class, $array)
    {
        if ($class == 'NavigationMenu') {
            $obj = new NavigationMenu();
        } elseif ($class == 'NavigationMenuItem') {
            $obj = new NavigationMenuItem();
        } elseif ($class == 'NavigationMenuItemAssignment') {
            $obj = new NavigationMenuItemAssignment();
        }
        foreach ($array as $k => $v) {
            if (strlen($k)) {
                if (is_array($v) && $k == 'menuTree') {
                    $treeChildren = [];
                    foreach ($v as $treeChild) {
                        array_push($treeChildren, $this->arrayToObject('NavigationMenuItemAssignment', $treeChild));
                    }
                    $obj->{$k} = $treeChildren;
                } elseif (is_array($v) && $k == 'navigationMenuItem') {
                    $obj->{$k} = $this->arrayToObject('NavigationMenuItem', $v); //RECURSION
                } elseif (is_array($v) && $k == 'children') {
                    $treeChildren = [];
                    foreach ($v as $treeChild) {
                        array_push($treeChildren, $this->arrayToObject('NavigationMenuItemAssignment', $treeChild));
                    }
                    $obj->{$k} = $treeChildren;
                } else {
                    $obj->{$k} = $v;
                }
            }
        }

        // should call transformNavMenuItemTitle because some
        // request don't have all template variables in place
        if ($class == 'NavigationMenuItem') {
            $templateMgr = TemplateManager::getManager(Application::get()->getRequest());
            $this->transformNavMenuItemTitle($templateMgr, $obj);
        }

        return $obj;
    }

    /**
     * Transform an item title if the title includes a {$variable}
     *
     * @param TemplateManager $templateMgr
     */
    public function transformNavMenuItemTitle($templateMgr, &$navigationMenuItem)
    {
        $this->setNMITitleLocalized($navigationMenuItem);

        $title = $navigationMenuItem->getLocalizedTitle();
        $prefix = '{$';
        $postfix = '}';

        $prefixPos = strpos($title, $prefix);
        $postfixPos = strpos($title, $postfix);

        if ($prefixPos !== false && $postfixPos !== false && ($postfixPos - $prefixPos) > 0) {
            $titleRepl = substr($title, $prefixPos + strlen($prefix), $postfixPos - $prefixPos - strlen($prefix));

            $templateReplaceTitle = $templateMgr->getTemplateVars($titleRepl);
            if ($templateReplaceTitle) {
                $navigationMenuItem->setTitle($templateReplaceTitle, Locale::getLocale());
            }
        }
    }

    /**
     * Populate the navigationMenuItem and the children properties of the NMIAssignment object
     *
     * @param NavigationMenuItemAssignment $nmiAssignment The NMIAssignment object passed by reference
     */
    public function populateNMIAssignmentContainedObjects(&$nmiAssignment)
    {
        // Set NMI
        $navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO');
        $nmiAssignment->setMenuItem($navigationMenuItemDao->getById($nmiAssignment->getMenuItemId()));

        // Set Children
        $navigationMenuItemAssignmentDao = DAORegistry::getDAO('NavigationMenuItemAssignmentDAO');
        $nmiAssignment->children = $navigationMenuItemAssignmentDao->getByMenuIdAndParentId($nmiAssignment->getMenuId(), $nmiAssignment->getId())
            ->toArray();

        // Recursive call to populate NMI and children properties of NMIAssignment's children
        foreach ($nmiAssignment->children as $assignmentChild) {
            $this->populateNMIAssignmentContainedObjects($assignmentChild);
        }
    }

    /**
     * Returns whether a NM's NMI has a child of a certain NMIType
     *
     * @param NavigationMenu $navigationMenu The NM to be searched
     * @param NavigationMenuItem $navigationMenuItem The NMI to check its children for NMIType
     * @param string $nmiType The NMIType
     * @param bool $isDisplayed optional. If true the function checks if the found NMI of type $nmiType is displayed.
     *
     * @return bool Returns true if a NMI of type $nmiType has been found as child of the given $navigationMenuItem.
     */
    private function _hasNMTreeNMIAssignmentWithChildOfNMIType($navigationMenu, $navigationMenuItem, $nmiType, $isDisplayed = true)
    {
        foreach ($navigationMenu->menuTree as $nmiAssignment) {
            $nmi = $nmiAssignment->getMenuItem();
            if (isset($nmi) && $nmi->getId() == $navigationMenuItem->getId()) {
                foreach ($nmiAssignment->children as $childNmiAssignment) {
                    $childNmi = $childNmiAssignment->getMenuItem();
                    if (isset($nmi) && $childNmi->getType() == $nmiType) {
                        if ($isDisplayed) {
                            return $childNmi->getIsDisplayed();
                        } else {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Sets the title of a navigation menu item, depending on its title or locale-key
     *
     * @param NavigationMenuItem $nmi The NMI to set its title
     */
    public function setNMITitleLocalized($nmi)
    {
        if ($nmi) {
            AppLocale::requireComponents(LOCALE_COMPONENT_PKP_COMMON, LOCALE_COMPONENT_PKP_MANAGER, LOCALE_COMPONENT_APP_COMMON, LOCALE_COMPONENT_PKP_USER);
            if ($localisedTitle = $nmi->getLocalizedTitle()) {
                $nmi->setTitle($localizedTitle, Locale::getLocale());
            } elseif ($nmi->getTitleLocaleKey() === '{$loggedInUsername}') {
                $nmi->setTitle($nmi->getTitleLocaleKey(), Locale::getLocale());
            } else {
                $nmi->setTitle(__($nmi->getTitleLocaleKey()), Locale::getLocale());
            }
        }
    }

    /**
     * Sets the title of a navigation menu item, depending on its title or locale-key
     *
     * @param NavigationMenuItem $nmi The NMI to set its title
     */
    public function setAllNMILocalisedTitles($nmi)
    {
        if ($nmi) {
            $supportedFormLocales = Locale::getSupportedFormLocales();

            foreach ($supportedFormLocales as $supportedFormLocale => $supportedFormLocaleValue) {
                AppLocale::requireComponents(
                    LOCALE_COMPONENT_PKP_COMMON,
                    LOCALE_COMPONENT_PKP_MANAGER,
                    LOCALE_COMPONENT_APP_COMMON,
                    LOCALE_COMPONENT_PKP_USER,
                    $supportedFormLocale
                );

                if ($localisedTitle = $nmi->getTitle($supportedFormLocale)) {
                    $nmi->setTitle($localisedTitle, $supportedFormLocale);
                } else {
                    $nmi->setTitle(__($nmi->getTitleLocaleKey(), [], $supportedFormLocale), $supportedFormLocale);
                }
            }
        }
    }

    /**
     * Callback to be registered from PKPTemplateManager for the LoadHandler hook.
     * Used by the Custom NMI to point their URL target to [context]/[path]
     *
     * @return bool true if the callback has handled the request.
     */
    public function _callbackHandleCustomNavigationMenuItems($hookName, $args)
    {
        $request = Application::get()->getRequest();

        $page = & $args[0];
        $op = & $args[1];

        // Construct a path to look for
        $path = $page;
        if ($op !== 'index') {
            $path .= "/${op}";
        }
        if ($arguments = $request->getRequestedArgs()) {
            $path .= '/' . implode('/', $arguments);
        }

        // Look for a static page with the given path
        $navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO');

        $context = $request->getContext();
        $contextId = $context ? $context->getId() : \PKP\core\PKPApplication::CONTEXT_ID_NONE;
        $customNMI = $navigationMenuItemDao->getByPath($contextId, $path);

        // Check if a custom NMI with the requested path existes
        if ($customNMI) {
            // Trick the handler into dealing with it normally
            $page = 'pages';
            $op = 'view';


            // It is -- attach the custom NMI handler.
            define('HANDLER_CLASS', 'NavigationMenuItemHandler');
            import('lib.pkp.pages.navigationMenu.NavigationMenuItemHandler');

            NavigationMenuItemHandler::setPage($customNMI);

            return true;
        }

        return false;
    }
}
