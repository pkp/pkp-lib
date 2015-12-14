<?php
/**
 * @file pages/dashboard/DashboardHandler.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
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
	function DashboardHandler() {
		parent::Handler();

		$this->addRoleAssignment(array(ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_AUTHOR, ROLE_ID_REVIEWER, ROLE_ID_ASSISTANT),
				array('index', 'tasks', 'submissions', 'active', 'archives'));
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
		$templateMgr = TemplateManager::getManager($request);
		$this->setupTemplate($request);
		$templateMgr->display('dashboard/index.tpl');
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
	 * View submissions tab
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function submissions($args, $request) {
		$templateMgr = TemplateManager::getManager($request);
		$this->setupTemplate($request);

		$context = $request->getContext();
		$templateMgr->assign('context', $context);

		return $templateMgr->fetchJson('dashboard/submissions.tpl');
	}

	/**
	 * View archives tab
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function active($args, $request) {
		$templateMgr = TemplateManager::getManager($request);
		$this->setupTemplate($request);
		$dispatcher = $request->getDispatcher();
		return $templateMgr->fetchAjax(
			'activeSubmissionsListGridContainer',
			$dispatcher->url($request, ROUTE_COMPONENT, null, 'grid.submissions.activeSubmissions.ActiveSubmissionsListGridHandler', 'fetchGrid')
		);
	}
	/**
	 * View archives tab
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function archives($args, $request) {
		$templateMgr = TemplateManager::getManager($request);
		$this->setupTemplate($request);

		return $templateMgr->fetchJson('dashboard/archives.tpl');
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

?>
