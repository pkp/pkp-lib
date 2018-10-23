<?php
/**
 * @file pages/dashboard/DashboardHandler.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class DashboardHandler
 * @ingroup pages_dashboard
 *
 * @brief Handle requests for user's dashboard.
 */

import('classes.handler.Handler');

class DashboardHandler extends Handler {
	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();

		$this->addRoleAssignment(array(ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_AUTHOR, ROLE_ID_REVIEWER, ROLE_ID_ASSISTANT),
				array('index', 'tasks', 'myQueue', 'unassigned', 'active', 'archives'));
	}

	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.PKPSiteAccessPolicy');
		$this->addPolicy(new PKPSiteAccessPolicy($request, null, $roleAssignments));
		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * Display about index page.
	 * @param $request PKPRequest
	 * @param $args array
	 */
	function index($args, $request) {
		if (!$request->getContext()) {
			$request->redirect(null, 'user');
		}

		$templateMgr = TemplateManager::getManager($request);
		$this->setupTemplate($request);

		$currentUser = $request->getUser();

		import('components.listPanels.submissions.SubmissionsListPanel');

		// My Queue
		$myQueueListPanel = new SubmissionsListPanel(array(
			'title' => 'common.queue.long.myAssigned',
			'getParams' => array(
				'status' => STATUS_QUEUED,
				'assignedTo' => $request->getUser()->getId(),
			),
		));
		$templateMgr->assign('myQueueListData', $myQueueListPanel->getConfig());

		$userRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
		if (!empty(array_intersect(array(ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER), $userRoles))) {

			// Unassigned
			$unassignedListPanel = new SubmissionsListPanel(array(
				'title' => 'common.queue.long.submissionsUnassigned',
				'getParams' => array(
					'status' => STATUS_QUEUED,
					'assignedTo' => -1,
				),
				'lazyLoad' => true,
			));
			$templateMgr->assign('unassignedListData', $unassignedListPanel->getConfig());

			// Active
			$activeListPanel = new SubmissionsListPanel(array(
				'title' => 'common.queue.long.active',
				'getParams' => array(
					'status' => STATUS_QUEUED,
				),
				'lazyLoad' => true,
			));
			$templateMgr->assign('activeListData', $activeListPanel->getConfig());
		}

		// Archived
		$params = array(
			'title' => 'common.queue.long.submissionsArchived',
			'getParams' => array(
				'status' => array(STATUS_DECLINED, STATUS_PUBLISHED),
			),
			'lazyLoad' => true,
		);
		if (!$currentUser->hasRole(array(ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER), $request->getContext()->getId())) {
			$params['getParams']['assignedTo'] = $currentUser->getId();
		}
		$archivedListPanel = new SubmissionsListPanel($params);
		$templateMgr->assign('archivedListData', $archivedListPanel->getConfig());

		return $templateMgr->display('dashboard/index.tpl');
	}

	/**
	 * View tasks tab
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function tasks($args, $request) {
		$templateMgr = TemplateManager::getManager($request);
		$this->setupTemplate($request);

		return $templateMgr->fetchJson('dashboard/tasks.tpl');
	}

	/**
	 * Setup common template variables.
	 * @param $request PKPRequest
	 */
	function setupTemplate($request = null) {
		AppLocale::requireComponents(LOCALE_COMPONENT_APP_MANAGER, LOCALE_COMPONENT_PKP_MANAGER, LOCALE_COMPONENT_APP_SUBMISSION, LOCALE_COMPONENT_PKP_SUBMISSION);
		parent::setupTemplate($request);
	}
}
