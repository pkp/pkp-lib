<?php

/**
 * @file pages/announcement/PKPAnnouncementHandler.inc.php
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPAnnouncementHandler
 * @ingroup pages_announcement
 *
 * @brief Handle requests for public announcement functions.
 */

import('classes.handler.Handler');

class PKPAnnouncementHandler extends Handler {
	function PKPAnnouncementHandler() {
		parent::Handler();
	}

	//
	// Implement methods from Handler.
	//
	function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.ContextRequiredPolicy');
		$this->addPolicy(new ContextRequiredPolicy($request));

		return parent::authorize($request, $args, $roleAssignments);
	}


	//
	// Public handler methods.
	//
	/**
	 * Show public announcements page.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string
	 */
	function index($args, $request) {
		$this->setupTemplate($request);

		$context = $request->getContext();
		$announcementsIntro = $context->getLocalizedSetting('announcementsIntroduction');

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('announcementsIntroduction', $announcementsIntro);

		$templateMgr->display('announcements/index.tpl');
	}
}

?>
