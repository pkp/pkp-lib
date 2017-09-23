<?php

/**
 * @file classes/services/NavigationMenuService.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenuService
 * @ingroup services
 *
 * @brief Helper class that encapsulates NavigationMenu business logic
 */

namespace PKP\Services;

class NavigationMenuService {

	public function getMenuItemTypes() {

		$types = array(
			NMI_TYPE_ABOUT => __('navigation.about'),
			NMI_TYPE_SUBMISSIONS => __('navigation.submissions'),
			NMI_TYPE_CURRENT => __('site.journalCurrent'),
			NMI_TYPE_ABOUT_CONTEXT => __('about.aboutContext'),
			NMI_TYPE_EDITORIAL_TEAM => __('about.editorialTeam'),
			NMI_TYPE_CONTACT => __('about.contact'),
			NMI_TYPE_ANNOUNCEMENTS => __('manager.announcements'),
			NMI_TYPE_CUSTOM => __('manager.navigationMenus.customType'),

			NMI_TYPE_USER_LOGOUT => __('user.logOut'),
			NMI_TYPE_USER_LOGOUT_AS => __('user.logOutAs'),
			NMI_TYPE_USER_PROFILE => __('common.viewProfile'),
			NMI_TYPE_ADMINISTRATION => __('navigation.admin'),
			NMI_TYPE_USER_DASHBOARD => __('navigation.dashboard'),
			NMI_TYPE_USER_REGISTER => __('navigation.register'),
			NMI_TYPE_USER_LOGIN => __('navigation.login'),

		);

		\HookRegistry::call('NavigationMenus::itemTypes', array(&$types));

		return $types;
	}

	function getDisplayStatus(&$navigationMenuItem) {
		$request = \Application::getRequest();
		$dispatcher = $request->getDispatcher();

		$isUserLoggedIn = \Validation::isLoggedIn();
		$isUserLoggedInAs = \Validation::isLoggedInAs();
		$context = $request->getContext();

		// Set navigationMenuItem type display template
		$templateMgr = \TemplateManager::getManager(\Application::getRequest());
		$templateReplaceTitle = $templateMgr->get_template_vars($navigationMenuItem->getLocalizedTitle());
		if ($templateReplaceTitle) {
			$navigationMenuItem->setTitle();
			$navigationMenuItem->setTitle($templateReplaceTitle, \AppLocale::getLocale());
		}

		$templateMgr->assign(array(
			'navigationMenuItem' => $navigationMenuItem,
		));

		$menuItemType = $navigationMenuItem->getType();
		switch ($menuItemType) {
			case NMI_TYPE_ANNOUNCEMENTS: // should be made as symbolic type - globally accessible
				// Set navigationMenuItem type Display function
				$display = false;
				if ($context) {
					$display = $context->getSetting('enableAnnouncements');
				}
				$navigationMenuItem->setIsDisplayed($display);

				// Set navigationMenuItem type URL
				$menuItemUrl = $dispatcher->url(
					$request,
					ROUTE_PAGE,
					null,
					'announcement',
					null,
					null
				);

				$navigationMenuItem->setUrl($menuItemUrl);
				break;
			case NMI_TYPE_ABOUT:
				// Set navigationMenuItem type Display function
				$navigationMenuItem->setIsDisplayed(true);

				// Set navigationMenuItem type URL
				$menuItemUrl = $dispatcher->url(
					$request,
					ROUTE_PAGE,
					null,
					'about',
					null,
					null
				);

				$navigationMenuItem->setUrl($menuItemUrl);
				break;
			case NMI_TYPE_CURRENT:
				// Set navigationMenuItem type Display function
				$display = false;
				if ($context) {
					if ($context->getSetting('publishingMode') != PUBLISHING_MODE_NONE) {
						$display = true;
					}
				}
				$navigationMenuItem->setIsDisplayed($display);

				// Set navigationMenuItem type URL
				$menuItemUrl = $dispatcher->url(
					$request,
					ROUTE_PAGE,
					null,
					'issue',
					'current',
					null
				);

				$navigationMenuItem->setUrl($menuItemUrl);
				break;
			case NMI_TYPE_ARCHIVES:
				// Set navigationMenuItem type Display function
				$display = false;
				if ($context) {
					if ($context->getSetting('publishingMode') != PUBLISHING_MODE_NONE) {
						$display = true;
					}
				}
				$navigationMenuItem->setIsDisplayed($display);

				// Set navigationMenuItem type URL
				$menuItemUrl = $dispatcher->url(
					$request,
					ROUTE_PAGE,
					null,
					'issue',
					'archive',
					null
				);

				$navigationMenuItem->setUrl($menuItemUrl);
				break;
			case NMI_TYPE_ABOUT_CONTEXT:
				// Set navigationMenuItem type Display function
				$navigationMenuItem->setIsDisplayed(true);

				// Set navigationMenuItem type URL
				$menuItemUrl = $dispatcher->url(
					$request,
					ROUTE_PAGE,
					null,
					'about',
					'contact',
					null
				);

				$navigationMenuItem->setUrl($menuItemUrl);
				break;
			case NMI_TYPE_SUBMISSIONS:
				// Set navigationMenuItem type Display function
				$navigationMenuItem->setIsDisplayed(true);

				// Set navigationMenuItem type URL
				$menuItemUrl = $dispatcher->url(
					$request,
					ROUTE_PAGE,
					null,
					'about',
					'submissions',
					null
				);

				$navigationMenuItem->setUrl($menuItemUrl);
				break;
			case NMI_TYPE_EDITORIAL_TEAM:
				// Set navigationMenuItem type Display function
				$display = false;
				if ($context) {
					if ($context->getLocalizedSetting('masthead')) {
						$display = true;
					}
				}
				$navigationMenuItem->setIsDisplayed($display);

				// Set navigationMenuItem type URL
				$menuItemUrl = $dispatcher->url(
					$request,
					ROUTE_PAGE,
					null,
					'about',
					'editorialTeam',
					null
				);

					$navigationMenuItem->setUrl($menuItemUrl);
				break;
			case NMI_TYPE_CONTACT:
				// Set navigationMenuItem type Display function
				$display = false;
				if ($context) {
					if ($context->getSetting('mailingAddress') || $context->getSetting('contactName')) {
						$display = true;
					}
				}
				$navigationMenuItem->setIsDisplayed($display);

				// Set navigationMenuItem type URL
				$menuItemUrl = $dispatcher->url(
					$request,
					ROUTE_PAGE,
					null,
					'about',
					'contact',
					null
				);

				$navigationMenuItem->setUrl($menuItemUrl);
				break;
			case NMI_TYPE_USER_LOGOUT:
				// Set navigationMenuItem type Display function
				$navigationMenuItem->setIsDisplayed($isUserLoggedIn);

				// Set navigationMenuItem type URL
				$menuItemUrl = $dispatcher->url(
					$request,
					ROUTE_PAGE,
					null,
					'login',
					'signOut',
					null
				);

				$navigationMenuItem->setUrl($menuItemUrl);
				break;
			case NMI_TYPE_USER_LOGOUT_AS:
				// Set navigationMenuItem type Display function
				$navigationMenuItem->setIsDisplayed($isUserLoggedInAs);

				// Set navigationMenuItem type URL
				$menuItemUrl = $dispatcher->url(
					$request,
					ROUTE_PAGE,
					null,
					'login',
					'signOutAsUser',
					null
				);

				$navigationMenuItem->setUrl($menuItemUrl);
				break;
			case NMI_TYPE_USER_PROFILE:
				// Set navigationMenuItem type Display function
				$navigationMenuItem->setIsDisplayed($isUserLoggedIn);

				// Set navigationMenuItem type URL
				$menuItemUrl = $dispatcher->url(
					$request,
					ROUTE_PAGE,
					null,
					'user',
					'profile',
					null
				);

				$navigationMenuItem->setUrl($menuItemUrl);
				break;
			case NMI_TYPE_ADMINISTRATION:
				// Set navigationMenuItem type Display function
				$navigationMenuItem->setIsDisplayed($isUserLoggedIn);

				// Set navigationMenuItem type URL
				$availableContexts = \Application::getContextDAO()->getAvailable();

				if ($availableContexts->count > 0) {
					$menuItemUrl = $dispatcher->url(
						$request,
						ROUTE_PAGE,
						null,
						'admin',
						'index',
						null
					);
				} else {
					$menuItemUrl = $dispatcher->url(
						$request,
						ROUTE_PAGE,
						'index',
						'admin',
						'index',
						null
					);
				}

				$navigationMenuItem->setUrl($menuItemUrl);
				break;
			case NMI_TYPE_USER_DASHBOARD:
				// Set navigationMenuItem type Display function
				$navigationMenuItem->setIsDisplayed($isUserLoggedIn);

				// Set navigationMenuItem type display template
				$displayTitle = $templateMgr->fetch('frontend/components/navigationMenus/dashboardMenuItem.tpl');
				$navigationMenuItem->setTitle($displayTitle, \AppLocale::getLocale());

				// Set navigationMenuItem type URL
				$menuItemUrl = $dispatcher->url(
					$request,
					ROUTE_PAGE,
					null,
					'submissions',
					null,
					null
				);

				$navigationMenuItem->setUrl($menuItemUrl);
				break;
			case NMI_TYPE_USER_REGISTER:
				// Set navigationMenuItem type Display function
				$navigationMenuItem->setIsDisplayed(!$isUserLoggedIn);

				// Set navigationMenuItem type URL
				$menuItemUrl = $dispatcher->url(
					$request,
					ROUTE_PAGE,
					null,
					'user',
					'register',
					null
				);

				$navigationMenuItem->setUrl($menuItemUrl);
				break;
			case NMI_TYPE_USER_LOGIN:
				// Set navigationMenuItem type Display function
				$navigationMenuItem->setIsDisplayed(!$isUserLoggedIn);

				// Set navigationMenuItem type URL
				$menuItemUrl = $dispatcher->url(
					$request,
					ROUTE_PAGE,
					null,
					'user',
					'login',
					null
				);

				$navigationMenuItem->setUrl($menuItemUrl);
				break;
			case NMI_TYPE_CUSTOM:
				// Set navigationMenuItem type Display function
				$navigationMenuItem->setIsDisplayed(true);

				if (!$navigationMenuItem->getUrl()) {
					if ($navigationMenuItem->getPath()) {
						// Set navigationMenuItem type URL
						$menuItemUrl = $dispatcher->url(
							$request,
							ROUTE_PAGE,
							null,
							'navigationMenu',
							'view',
							$navigationMenuItem->getPath()
						);

						$navigationMenuItem->setUrl($menuItemUrl);
					}
				}

				break;
			default:
				// Fire hook for determining display status of third-party types. Default: true
				\HookRegistry::call('NavigationMenus::displayType', array(&$navigationMenuItem));


		}
	}

	/**
	 * Get a tree of NavigationMenuItems assigned to this menu
	 *
	 * @return array Hierarchical array of menu items
	 */
	public function getMenuTree(&$navigationMenu) {
		$navigationMenuItemDao = \DAORegistry::getDAO('NavigationMenuItemDAO');
		$items = $navigationMenuItemDao->getByMenuId($navigationMenu->getId())->toArray();
		foreach($items as $item) {
			$this->getDisplayStatus($item);
		}

		$navigationMenuItemAssignmentDao = \DAORegistry::getDAO('NavigationMenuItemAssignmentDAO');
		$assignments = $navigationMenuItemAssignmentDao->getByMenuId($navigationMenu->getId())
				->toArray();

		for ($i = 0; $i < count($assignments); $i++) {
			foreach($items as $item) {
				if ($item->getId() === $assignments[$i]->getMenuItemId()) {
					$assignments[$i]->setMenuItem($item);
					break;
				}
			}
		}

		// Create an array of parent items and array of child items sorted by
		// their parent id as the array key
		$navigationMenu->menuTree = array();
		$children = array();
		foreach ($assignments as $assignment) {
			if (!$assignment->getParentId()) {
				$navigationMenu->menuTree[] = $assignment;
			} else {
				if (!isset($children[$assignment->getParentId()])) {
					$children[$assignment->getParentId()] = array();
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

		// return $navigationMenu->menuTree;
	}
}
