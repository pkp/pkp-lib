<?php

/**
 * @file pages/sidebar/SidebarHandler.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SidebarHandler
 * @ingroup pages_sidebar
 *
 * @brief Handle site sidebar requests.
 */


import('classes.handler.Handler');

class SidebarHandler extends Handler {
	/**
	 * Constructor
	 */
	function SidebarHandler() {
		parent::Handler();
	}


	//
	// Public handler operations
	//
	/**
	 * Display the sidebar.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function index($args, &$request) {
		$this->setupTemplate($request);
		$templateMgr = TemplateManager::getManager($request);
		return $templateMgr->fetchJson('sidebar/sidebar.tpl');
	}
}

?>
