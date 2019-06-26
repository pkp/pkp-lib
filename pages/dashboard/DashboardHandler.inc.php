<?php
/**
 * @file pages/dashboard/DashboardHandler.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
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
		$userRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
		$apiUrl = $dispatcher->url($request, ROUTE_API, $context->getPath(), '_submissions');
		$lists = [];

		// My Queue
		$myQueueListPanel = new \APP\components\listPanels\SubmissionsListPanel(
			SUBMISSIONS_LIST_MY_QUEUE,
			__('common.queue.long.myAssigned'),
			[
				'apiUrl' => $apiUrl,
				'getParams' => [
					'status' => STATUS_QUEUED,
					'assignedTo' => (int) $request->getUser()->getId(),
				],
			]
		);
		$myQueueListPanel->set([
			'items' => $myQueueListPanel->getItems($request),
			'itemsMax' => $myQueueListPanel->getItemsMax()
		]);
		$lists[$myQueueListPanel->id] = $myQueueListPanel->getConfig();

		if (!empty(array_intersect(array(ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER), $userRoles))) {

			// Unassigned
			$unassignedListPanel = new \APP\components\listPanels\SubmissionsListPanel(
				SUBMISSIONS_LIST_UNASSIGNED,
				__('common.queue.long.submissionsUnassigned'),
				[
					'apiUrl' => $apiUrl,
					'getParams' => [
						'status' => STATUS_QUEUED,
						'assignedTo' => -1,
					],
					'lazyLoad' => true,
				]
			);
			$lists[$unassignedListPanel->id] = $unassignedListPanel->getConfig();

			// Active
			$activeListPanel = new \APP\components\listPanels\SubmissionsListPanel(
				SUBMISSIONS_LIST_ACTIVE,
				__('common.queue.long.active'),
				[
					'apiUrl' => $apiUrl,
					'getParams' => [
						'status' => STATUS_QUEUED,
					],
					'lazyLoad' => true,
				]
			);
			$lists[$activeListPanel->id] = $activeListPanel->getConfig();
		}

		// Archived
		$params = [
			'status' => [STATUS_DECLINED, STATUS_PUBLISHED, STATUS_SCHEDULED],
		];
		if (empty(array_intersect([ROLE_ID_MANAGER, ROLE_ID_SITE_ADMIN], $userRoles))) {
			$params['assignedTo'] = (int) $currentUser->getId();
		}
		$archivedListPanel = new \APP\components\listPanels\SubmissionsListPanel(
			SUBMISSIONS_LIST_ARCHIVE,
			__('common.queue.long.submissionsArchived'),
			[
				'apiUrl' => $apiUrl,
				'getParams' => $params,
				'lazyLoad' => true,
			]
		);
		$lists[$archivedListPanel->id] = $archivedListPanel->getConfig();

		$templateMgr->assign('containerData', ['components' => $lists]);

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
