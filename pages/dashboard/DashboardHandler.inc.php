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

define('SUBMISSIONS_LIST_ACTIVE', 'active');
define('SUBMISSIONS_LIST_ARCHIVE', 'archive');
define('SUBMISSIONS_LIST_MY_QUEUE', 'myQueue');
define('SUBMISSIONS_LIST_UNASSIGNED', 'unassigned');

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
		$context = $request->getContext();
		$dispatcher = $request->getDispatcher();

		if (!$context) {
			$request->redirect(null, 'user');
		}

		$templateMgr = TemplateManager::getManager($request);
		$this->setupTemplate($request);

		$currentUser = $request->getUser();

		$apiUrl = $dispatcher->url($request, ROUTE_API, $context->getPath(), '_submissions');

		import('classes.components.listPanels.submissions.SubmissionsListPanel');

		$lists = [];

		// My Queue
		$myQueueListPanel = new SubmissionsListPanel(array(
			'id' => SUBMISSIONS_LIST_MY_QUEUE,
			'title' => __('common.queue.long.myAssigned'),
			'apiUrl' => $apiUrl,
			'getParams' => array(
				'status' => STATUS_QUEUED,
				'assignedTo' => $request->getUser()->getId(),
			),
		));
		$lists[$myQueueListPanel->getId()] = $myQueueListPanel->getConfig();

		$userRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
		if (!empty(array_intersect(array(ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER), $userRoles))) {

			// Unassigned
			$unassignedListPanel = new SubmissionsListPanel(array(
				'id' => SUBMISSIONS_LIST_UNASSIGNED,
				'title' => __('common.queue.long.submissionsUnassigned'),
				'apiUrl' => $apiUrl,
				'getParams' => array(
					'status' => STATUS_QUEUED,
					'assignedTo' => -1,
				),
				'lazyLoad' => true,
			));
			$lists[$unassignedListPanel->getId()] = $unassignedListPanel->getConfig();

			// Active
			$activeListPanel = new SubmissionsListPanel(array(
				'id' => SUBMISSIONS_LIST_ACTIVE,
				'title' => __('common.queue.long.active'),
				'apiUrl' => $apiUrl,
				'getParams' => array(
					'status' => STATUS_QUEUED,
				),
				'lazyLoad' => true,
			));
			$lists[$activeListPanel->getId()] = $activeListPanel->getConfig();
		}

		// Archived
		$params = array(
			'id' => SUBMISSIONS_LIST_ARCHIVE,
			'title' => __('common.queue.long.submissionsArchived'),
			'apiUrl' => $apiUrl,
			'getParams' => array(
				'status' => array(STATUS_DECLINED, STATUS_PUBLISHED),
			),
			'lazyLoad' => true,
		);
		if (!$currentUser->hasRole(array(ROLE_ID_MANAGER), $request->getContext()->getId()) && !$currentUser->hasRole(array(ROLE_ID_SITE_ADMIN), CONTEXT_SITE)) {
			$params['getParams']['assignedTo'] = $currentUser->getId();
		}
		$archivedListPanel = new SubmissionsListPanel($params);
		$lists[$archivedListPanel->getId()] = $archivedListPanel->getConfig();

		$templateMgr->assign('containerData', [
			'components' => $lists,
		]);

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
