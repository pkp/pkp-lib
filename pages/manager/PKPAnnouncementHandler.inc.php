<?php

/**
 * @file pages/manager/PKPAnnouncementHandler.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPAnnouncementHandler
 * @ingroup pages_manager
 *
 * @brief Handle requests for announcement management functions.
 */


import('pages.manager.ManagerHandler');

class PKPAnnouncementHandler extends ManagerHandler {
	function PKPAnnouncementHandler() {
		parent::ManagerHandler();
	}

	function index($args, $request) {
		$this->announcements($args, $request);
	}

	/**
	 * Display a list of announcements for the current context.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function announcements($args, $request) {
		// FIXME: Remove call to validate() when all ManagerHandler implementations
		// (across all apps) have been migrated to the authorize() authorization approach.
		$this->validate();
		$this->setupTemplate($request);

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->display('manager/announcement/announcements.tpl');
	}

	/**
	 * Display a list of announcement types for the current context.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function announcementTypes($args, $request) {
		// FIXME: Remove call to validate() when all ManagerHandler implementations
		// (across all apps) have been migrated to the authorize() authorization approach.
		$this->validate();
		$this->setupTemplate($request, true);

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->display('manager/announcement/announcementTypes.tpl');
	}
}

?>
