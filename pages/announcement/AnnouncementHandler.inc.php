<?php

/**
 * @file pages/announcement/AnnouncementHandler.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPAnnouncementHandler
 * @ingroup pages_announcement
 *
 * @brief Handle requests for public announcement functions.
 */

import('classes.handler.Handler');

class AnnouncementHandler extends Handler {
	/**
	 * Constructor
	 */
	function AnnouncementHandler() {
		parent::Handler();
	}

	//
	// Implement methods from Handler.
	//
	/**
	 * @copydoc Handler::authorize()
	 */
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

	/**
	 * View announcement details.
	 * @param $args array first parameter is the ID of the announcement to display
	 * @param $request PKPRequest
	 */
	function view($args, $request) {
		$this->validate();
		$this->setupTemplate($request);

		$context = $request->getContext();
		$announcementId = (int) array_shift($args);
		$announcementDao = DAORegistry::getDAO('AnnouncementDAO');
		$announcement = $announcementDao->getById($announcementId);
		if ($announcement && $announcement->getAssocType() == Application::getContextAssocType() && $announcement->getAssocId() == $context->getId() && ($announcement->getDateExpire() == null || strtotime($announcement->getDateExpire()) > time())) {
			$templateMgr = TemplateManager::getManager($request);
			$templateMgr->assign('announcement', $announcement);
			$templateMgr->assign('announcementTitle', $announcement->getLocalizedTitleFull());
			return $templateMgr->display('announcements/view.tpl');
		}
		$request->redirect(null, 'announcement');
	}
}

?>
