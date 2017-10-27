<?php

/**
 * @file classes/services/PKPNavigationMenuService.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPNavigationMenuService
 * @ingroup services
 *
 * @brief Helper class that encapsulates NavigationMenu business logic
 */

namespace PKP\Services;

class PKPNavigationMenuService {

	/**
	 * Return all default navigationMenuItemTypes.
	 * @return array
	 */
	public function getMenuItemTypes() {
		\AppLocale::requireComponents(LOCALE_COMPONENT_APP_COMMON, LOCALE_COMPONENT_PKP_USER);
		$types = array(
			NMI_TYPE_CUSTOM => array(
				'title' => __('manager.navigationMenus.customPage'),
				'description' => __('manager.navigationMenus.customPage.description'),
			),
			NMI_TYPE_REMOTE_URL => array(
				'title' => __('manager.navigationMenus.remoteUrl'),
				'description' => __('manager.navigationMenus.remoteUrl.description'),
			),
			NMI_TYPE_ABOUT => array(
				'title' => __('navigation.about'),
				'description' => __('manager.navigationMenus.about.description'),
				'conditionalWarning' => __('manager.navigationMenus.about.conditionalWarning'),
			),
			NMI_TYPE_EDITORIAL_TEAM => array(
				'title' => __('about.editorialTeam'),
				'description' => __('manager.navigationMenus.editorialTeam.description'),
				'conditionalWarning' => __('manager.navigationMenus.editorialTeam.conditionalWarning'),
			),
			NMI_TYPE_SUBMISSIONS => array(
				'title' => __('navigation.submissions'),
				'description' => __('manager.navigationMenus.submissions.description'),
			),
			NMI_TYPE_ANNOUNCEMENTS => array(
				'title' => __('announcement.announcements'),
				'description' => __('manager.navigationMenus.announcements.description'),
				'conditionalWarning' => __('manager.navigationMenus.announcements.conditionalWarning'),
			),
			NMI_TYPE_USER_LOGIN => array(
				'title' => __('navigation.login'),
				'description' => __('manager.navigationMenus.login.description'),
				'conditionalWarning' => __('manager.navigationMenus.loggedIn.conditionalWarning'),
			),
			NMI_TYPE_USER_REGISTER => array(
				'title' => __('navigation.register'),
				'description' => __('manager.navigationMenus.register.description'),
				'conditionalWarning' => __('manager.navigationMenus.loggedIn.conditionalWarning'),
			),
			NMI_TYPE_USER_DASHBOARD => array(
				'title' => __('navigation.dashboard'),
				'description' => __('manager.navigationMenus.dashboard.description'),
				'conditionalWarning' => __('manager.navigationMenus.loggedOut.conditionalWarning'),
			),
			NMI_TYPE_USER_PROFILE => array(
				'title' => __('common.viewProfile'),
				'description' => __('manager.navigationMenus.profile.description'),
				'conditionalWarning' => __('manager.navigationMenus.loggedOut.conditionalWarning'),
			),
			NMI_TYPE_ADMINISTRATION => array(
				'title' => __('navigation.admin'),
				'description' => __('manager.navigationMenus.administration.description'),
				'conditionalWarning' => __('manager.navigationMenus.loggedOut.conditionalWarning'),
			),
			NMI_TYPE_USER_LOGOUT => array(
				'title' => __('user.logOut'),
				'description' => __('manager.navigationMenus.logOut.description'),
				'conditionalWarning' => __('manager.navigationMenus.loggedOut.conditionalWarning'),
			),
			NMI_TYPE_CONTACT => array(
				'title' => __('about.contact'),
				'description' => __('manager.navigationMenus.contact.description'),
				'conditionalWarning' => __('manager.navigationMenus.contact.conditionalWarning'),
			),
		);

		\HookRegistry::call('NavigationMenus::itemTypes', array(&$types));

		return $types;
	}

	/**
	 * Callback for display menu item functionallity
	 */
	function getDisplayStatus(&$navigationMenuItem, &$navigationMenu) {
		$request = \Application::getRequest();
		$dispatcher = $request->getDispatcher();
		$templateMgr = \TemplateManager::getManager(\Application::getRequest());

		$isUserLoggedIn = \Validation::isLoggedIn();
		$isUserLoggedInAs = \Validation::isLoggedInAs();
		$context = $request->getContext();
		$currentUser = $request->getUser();

		$contextId = $context ? $context->getId() : CONTEXT_ID_NONE;

		// Transform an item title if the title includes a {$variable}
		$this->transformNavMenuItemTitle($templateMgr, $navigationMenuItem);

		$menuItemType = $navigationMenuItem->getType();

		// Conditionally hide some items
		switch ($menuItemType) {
			case NMI_TYPE_ANNOUNCEMENTS:
				$navigationMenuItem->setIsDisplayed($context && $context->getSetting('enableAnnouncements'));
				break;
			case NMI_TYPE_EDITORIAL_TEAM:
				$navigationMenuItem->setIsDisplayed($context && $context->getLocalizedSetting('masthead'));
				break;
			case NMI_TYPE_CONTACT:
				$navigationMenuItem->setIsDisplayed($context && ($context->getSetting('mailingAddress') || $context->getSetting('contactName')));
				break;
			case NMI_TYPE_USER_REGISTER:
				$navigationMenuItem->setIsDisplayed(!$isUserLoggedIn && !($context && $context->getSetting('disableUserReg')));
				break;
			case NMI_TYPE_USER_LOGIN:
				$navigationMenuItem->setIsDisplayed(!$isUserLoggedIn);
				break;
			case NMI_TYPE_USER_LOGOUT:
			case NMI_TYPE_USER_PROFILE:
			case NMI_TYPE_USER_DASHBOARD:
				$navigationMenuItem->setIsDisplayed($isUserLoggedIn);
				break;
			case NMI_TYPE_ADMINISTRATION:
				$navigationMenuItem->setIsDisplayed($isUserLoggedIn && ($currentUser->hasRole(array(ROLE_ID_SITE_ADMIN), $contextId) || $currentUser->hasRole(array(ROLE_ID_SITE_ADMIN), CONTEXT_SITE)));
				break;
		}

		if ($navigationMenuItem->getIsDisplayed()) {

			// Adjust some titles
			switch ($menuItemType) {
				case NMI_TYPE_USER_LOGOUT:
					if ($isUserLoggedInAs) {
						$userName = $request->getUser() ? ' ' . $request->getUser()->getUserName() : '';
						$navigationMenuItem->setTitle(__('user.logOutAs') . $userName, \AppLocale::getLocale());
					}
					break;
				case NMI_TYPE_USER_DASHBOARD:
					$templateMgr->assign('navigationMenuItem', $navigationMenuItem);
					if ($currentUser->hasRole(array(ROLE_ID_MANAGER, ROLE_ID_ASSISTANT, ROLE_ID_REVIEWER, ROLE_ID_AUTHOR), $contextId) || $currentUser->hasRole(array(ROLE_ID_SITE_ADMIN), CONTEXT_SITE)) {
						$displayTitle = $templateMgr->fetch('frontend/components/navigationMenus/dashboardMenuItem.tpl');
						$navigationMenuItem->setTitle($displayTitle, \AppLocale::getLocale());
					}

					break;
			}

			// Set the URL
			switch ($menuItemType) {
				case NMI_TYPE_ANNOUNCEMENTS:
					$navigationMenuItem->setUrl($dispatcher->url(
						$request,
						ROUTE_PAGE,
						null,
						'announcement',
						null,
						null
					));
					break;
				case NMI_TYPE_ABOUT:
					$navigationMenuItem->setUrl($dispatcher->url(
						$request,
						ROUTE_PAGE,
						null,
						'about',
						null,
						null
					));
					break;
				case NMI_TYPE_SUBMISSIONS:
					$navigationMenuItem->setUrl($dispatcher->url(
						$request,
						ROUTE_PAGE,
						null,
						'about',
						'submissions',
						null
					));
					break;
				case NMI_TYPE_EDITORIAL_TEAM:
					$navigationMenuItem->setUrl($dispatcher->url(
						$request,
						ROUTE_PAGE,
						null,
						'about',
						'editorialTeam',
						null
					));
					break;
				case NMI_TYPE_CONTACT:
					$navigationMenuItem->setUrl($dispatcher->url(
						$request,
						ROUTE_PAGE,
						null,
						'about',
						'contact',
						null
					));
					break;
				case NMI_TYPE_USER_LOGOUT:
					$navigationMenuItem->setUrl($dispatcher->url(
						$request,
						ROUTE_PAGE,
						null,
						'login',
						$isUserLoggedInAs ? 'signOutAsUser' : 'signOut',
						null
					));
					break;
				case NMI_TYPE_USER_PROFILE:
					$navigationMenuItem->setUrl($dispatcher->url(
						$request,
						ROUTE_PAGE,
						null,
						'user',
						'profile',
						null
					));
					break;
				case NMI_TYPE_ADMINISTRATION:
					$contextPath = 'index';
					$user = $request->getUser();
					$contextDao = \Application::getContextDAO();
					$workingContexts = $contextDao->getAvailable($user?$user->getId():null);
					if ($workingContexts && $workingContexts->getCount() == 1) {
						$workingContext = $workingContexts->next();
						$contextPath = $workingContext->getPath();
					}
					$navigationMenuItem->setUrl($dispatcher->url(
						$request,
						ROUTE_PAGE,
						$contextPath,
						'admin',
						'index',
						null
					));
					break;
				case NMI_TYPE_USER_DASHBOARD:
					if ($currentUser->hasRole(array(ROLE_ID_MANAGER, ROLE_ID_ASSISTANT, ROLE_ID_REVIEWER, ROLE_ID_AUTHOR), $contextId) || $currentUser->hasRole(array(ROLE_ID_SITE_ADMIN), CONTEXT_SITE)) {
						$navigationMenuItem->setUrl($dispatcher->url(
							$request,
							ROUTE_PAGE,
							null,
							'submissions',
							null,
							null
						));
					} else {
						$navigationMenuItem->setUrl($dispatcher->url(
							$request,
							ROUTE_PAGE,
							null,
							'user',
							'profile',
							null
						));
					}

					break;
				case NMI_TYPE_USER_REGISTER:
					$navigationMenuItem->setUrl($dispatcher->url(
						$request,
						ROUTE_PAGE,
						null,
						'user',
						'register',
						null
					));
					break;
				case NMI_TYPE_USER_LOGIN:
					$navigationMenuItem->setUrl($dispatcher->url(
						$request,
						ROUTE_PAGE,
						null,
						'login',
						null,
						null
					));
					break;
				case NMI_TYPE_CUSTOM:
					if ($navigationMenuItem->getPath()) {
						$navigationMenuItem->setUrl($dispatcher->url(
							$request,
							ROUTE_PAGE,
							null,
							'navigationMenu',
							'view',
							$navigationMenuItem->getPath()
						));
					}
					break;
			}
		}

		\HookRegistry::call('NavigationMenus::displaySettings', array($navigationMenuItem, $navigationMenu));

		$templateMgr->assign('navigationMenuItem', $navigationMenuItem);
	}

	/**
	 * Get a tree of NavigationMenuItems assigned to this menu
	 * @param $navigationMenu \NavigationMenu
	 *
	 * @return array Hierarchical array of menu items
	 */
	public function getMenuTree(&$navigationMenu) {
		$navigationMenuItemDao = \DAORegistry::getDAO('NavigationMenuItemDAO');
		$items = $navigationMenuItemDao->getByMenuId($navigationMenu->getId())->toArray();


		$navigationMenuItemAssignmentDao = \DAORegistry::getDAO('NavigationMenuItemAssignmentDAO');
		$assignments = $navigationMenuItemAssignmentDao->getByMenuId($navigationMenu->getId())
				->toArray();

		foreach ($assignments as $assignment) {
			foreach($items as $item) {
				if ($item->getId() === $assignment->getMenuItemId()) {
					$assignment->setMenuItem($item);
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

		foreach($items as $item) {
			$this->getDisplayStatus($item, $navigationMenu);
		}
	}

	/**
	 * Transform an item title if the title includes a {$variable}
	 * @param $templateMgr \TemplateManager
	 * @param $navigationMenu \NavigationMenu
	 */
	public function transformNavMenuItemTitle($templateMgr, &$navigationMenuItem) {
		$title = $navigationMenuItem->getLocalizedTitle();
		$prefix = '{$';
		$postfix = '}';

		$prefixPos = strpos($title, $prefix);
		$postfixPos = strpos($title, $postfix);

		if ($prefixPos !== false && $postfixPos !== false && ($postfixPos - $prefixPos) > 0){
			$titleRepl = substr($title, $prefixPos + strlen($prefix), $postfixPos - $prefixPos - strlen($prefix));

			$templateReplaceTitle = $templateMgr->get_template_vars($titleRepl);
			if ($templateReplaceTitle) {
				$navigationMenuItem->setTitle($templateReplaceTitle, \AppLocale::getLocale());
			}
		}
	}

	/**
	 * Populate the navigationMenuItem and the children properties of the NMIAssignment object
	 * @param $nmiAssignment \NavigationMenuItemAssignment The NMIAssignment object passed by reference
	 */
	public function populateNMIAssignmentContainedObjects(&$nmiAssignment) {
		// Set NMI
		$navigationMenuItemDao = \DAORegistry::getDAO('NavigationMenuItemDAO');
		$nmiAssignment->setMenuItem($navigationMenuItemDao->getById($nmiAssignment->getMenuItemId()));

		// Set Children
		$navigationMenuItemAssignmentDao = \DAORegistry::getDAO('NavigationMenuItemAssignmentDAO');
		$nmiAssignment->children = $navigationMenuItemAssignmentDao->getByMenuIdAndParentId($nmiAssignment->getMenuId(), $nmiAssignment->getId())
			->toArray();

		// Recursive call to populate NMI and children properties of NMIAssignment's children
		foreach ($nmiAssignment->children as $assignmentChild) {
			$this->populateNMIAssignmentContainedObjects($assignmentChild);
		}
	}

	/**
	 * Returns whether a NM's NMI has a child of a certain NMIType
	 * @param $navigationMenu \NavigationMenu The NM to be searched
	 * @param $navigationMenuItem \NavigationMenuItem The NMI to check its children for NMIType
	 * @param $nmiType string The NMIType
	 * @param $isDisplayed boolean optional. If true the function checks if the found NMI of type $nmiType is displayed.
	 * @return boolean Returns true if a NMI of type $nmiType has been found as child of the given $navigationMenuItem.
	 */
	private function _hasNMTreeNMIAssignmentWithChildOfNMIType($navigationMenu, $navigationMenuItem, $nmiType, $isDisplayed = true) {
		foreach($navigationMenu->menuTree as $nmiAssignment) {
			$nmi = $nmiAssignment->getMenuItem();
			if(isset($nmi) && $nmi->getId() == $navigationMenuItem->getId()) {
				foreach($nmiAssignment->children as $childNmiAssignment){
					$childNmi = $childNmiAssignment->getMenuItem();
					if (isset($nmi) && $childNmi->getType() == $nmiType) {
						if($isDisplayed) {
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
}
