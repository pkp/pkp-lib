<?php

/**
 * @file classes/services/NavigationMenuService.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenuService
 * @ingroup services
 *
 * @brief Helper class that encapsulates NavigationMenu business logic
 */

namespace APP\services;

use APP\core\Application;
use APP\template\TemplateManager;
use PKP\core\PKPApplication;
use PKP\plugins\Hook;
use PKP\security\Validation;

class NavigationMenuService extends \PKP\services\PKPNavigationMenuService
{
    // Types for all ops default navigationMenuItems
    public const NMI_TYPE_ARCHIVES = 'NMI_TYPE_ARCHIVES';

    /**
     * Initialize hooks for extending PKPSubmissionService
     */
    public function __construct()
    {
        Hook::add('NavigationMenus::itemTypes', [$this, 'getMenuItemTypesCallback']);
        Hook::add('NavigationMenus::displaySettings', [$this, 'getDisplayStatusCallback']);
    }

    /**
     * Return all default navigationMenuItemTypes.
     *
     * @param string $hookName
     * @param array $args of arguments passed
     */
    public function getMenuItemTypesCallback($hookName, $args)
    {
        $types = & $args[0];

        $opsTypes = [
            self::NMI_TYPE_ARCHIVES => [
                'title' => __('navigation.archives'),
                'description' => __('manager.navigationMenus.archives.description'),
            ],
        ];

        $types = array_merge($types, $opsTypes);
    }

    /**
     * Callback for display menu item functionality
     *
     * @param string $hookName
     * @param array $args of arguments passed
     */
    public function getDisplayStatusCallback($hookName, $args)
    {
        $navigationMenuItem = & $args[0];

        $request = Application::get()->getRequest();
        $dispatcher = $request->getDispatcher();
        $templateMgr = TemplateManager::getManager(Application::get()->getRequest());

        $isUserLoggedIn = Validation::isLoggedIn();
        $isUserLoggedInAs = Validation::isLoggedInAs();
        $context = $request->getContext();

        $this->transformNavMenuItemTitle($templateMgr, $navigationMenuItem);

        $menuItemType = $navigationMenuItem->getType();

        // Conditionally hide some items
        switch ($menuItemType) {
            case self::NMI_TYPE_ARCHIVES:
                $navigationMenuItem->setIsDisplayed($context && $context->getData('publishingMode') != \APP\server\Server::PUBLISHING_MODE_NONE);
                break;
        }

        if ($navigationMenuItem->getIsDisplayed()) {

            // Set the URL
            switch ($menuItemType) {
                case self::NMI_TYPE_ARCHIVES:
                    $navigationMenuItem->setUrl($dispatcher->url(
                        $request,
                        PKPApplication::ROUTE_PAGE,
                        null,
                        'preprints',
                        null,
                        null
                    ));
                    break;
            }
        }
    }
}

if (!PKP_STRICT_MODE) {
    define('NMI_TYPE_ARCHIVES', \APP\services\NavigationMenuService::NMI_TYPE_ARCHIVES);
}
