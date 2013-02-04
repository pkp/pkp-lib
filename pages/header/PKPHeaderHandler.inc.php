<?php

/**
 * @file pages/header/PKPHeaderHandler.inc.php
 *
 * Copyright (c) 2003-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPHeaderHandler
 * @ingroup pages_header
 *
 * @brief Handle site header requests.
 */


import('classes.handler.Handler');

class PKPHeaderHandler extends Handler {
	/**
	 * Constructor
	 */
	function PKPHeaderHandler() {
		parent::Handler();
	}


	//
	// Public handler operations
	//
	/**
	 * Display the header.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function index($args, &$request) {
		$this->setupTemplate($request);
		$templateMgr = TemplateManager::getManager($request);

		$workingContexts = $this->_getWorkingContexts($request);

		$multipleContexts = false;
		if ($workingContexts->getCount() > 1) {
			$templateMgr->assign('multipleContexts', true);
			$multipleContexts = true;
		} else {
			if ($workingContexts->getCount() == 0) { // no presses configured
				$templateMgr->assign('noContextsConfigured', true);
			}
		}

		if ($multipleContexts) {
			$dispatcher = $request->getDispatcher();
			$contextsNameAndUrl = array();
			while ($workingContext = $workingContexts->next()) {
				$contextUrl = $dispatcher->url($request, ROUTE_PAGE, $workingContext->getPath());
				$contextsNameAndUrl[$contextUrl] = $workingContext->getLocalizedName();
			}

			// Get the current context switcher value. We don´t need to worry about the
			// value when there is no current context, because then the switcher will not
			// be visible.
			$currentContextUrl = null;
			if ($currentContext = $request->getContext()) {
				$currentContextUrl = $dispatcher->url($request, ROUTE_PAGE, $currentContext->getPath());
			} else {
				$contextsNameAndUrl = array(__('press.select')) + $contextsNameAndUrl;
			}

			$templateMgr->assign('currentContextUrl', $currentContextUrl);
			$templateMgr->assign('contextsNameAndUrl', $contextsNameAndUrl);
		}

		return $templateMgr->fetchJson('header/index.tpl');
	}

	//
	// Private methods
	//
	/**
	 * Get the iterator of working contexts.
	 * @param $request PKPRequest
	 * @return ItemIterator
	 */
	function _getWorkingContexts($request) {
		assert(false); // Must be implemented by subclasses
	}
}

?>
