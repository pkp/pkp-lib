<?php

/**
 * @file classes/core/PKPHandler.inc.php
 *
 * Copyright (c) 2000-2008 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @package core
 * @class PKPHandler
 *
 * Base request handler abstract class.
 *
 * $Id$
 */

class PKPHandler {
	/**
	 * Fallback method in case request handler does not implement index method.
	 */
	function index() {
		header('HTTP/1.0 404 Not Found');
		fatalError('404 Not Found');
	}

	/**
	 * Perform request access validation based on security settings.
	 * @param $requiredContexts array
	 */
	function validate($requiredContexts = null) {
		if (Config::getVar('security', 'force_ssl') && Request::getProtocol() != 'https') {
			// Force SSL connections site-wide
			Request::redirectSSL();
		}

		$application =& PKPApplication::getApplication();
		$contextDepth = $application->getContextDepth();

		$returner = array();
		for ($i = 1; $i <= $contextDepth; $i++ ) {  
			$context =& Request::getContext($i);
			if ( isset($requiredContexts[$i-1]) && $requiredContexts[$i-1] && !isset($context)) {
				PKPRequest::redirect(null, 'about');
			} else {
				$returner[] =& $context;
			} 
		}

		if (isset($returner[0])) { 
			$mainContext =& $returner[0];
		} else { 
			$mainContext = null;
		}

 		// FIXME: need a generic way of adding this extra check
		// Extraneous checks, just to make sure we aren't being fooled
//		if ($conference && $schedConf) {
//			if($schedConf->getConferenceId() != $conference->getConferenceId())
//				Request::redirect(null, null, 'about');
//		}

		$page = PKPRequest::getRequestedPage();
		if ( $mainContext != null &&
			!Validation::isLoggedIn() &&
			!in_array($page, PKPHandler::getLoginExemptions()) &&
			$mainContext->getSetting('restrictSiteAccess')
		) {
			PKPRequest::redirect(null, 'login');
		}

		return $returner;
	}
	
	/**
	 * Delegate request handling to another handler class
	 */
	function delegate($fullClassName) {
		import($fullClassName);

		call_user_func(
			array(
				array_pop(explode('.', $fullClassName)),
				Request::getRequestedOp()
			),
			Request::getRequestedArgs()
		);
	}

	/**
	 * Return the DBResultRange structure and misc. variables describing the current page of a set of pages.
	 * @param $rangeName string Symbolic name of range of pages; must match the Smarty {page_list ...} name.
	 * @param $contextData array If set, this should contain a set of data that are required to
	 * 	define the context of this request (for maintaining page numbers across requests).
	 *	To disable persistent page contexts, set this variable to null.
	 * @return array ($pageNum, $dbResultRange)
	 */
	function &getRangeInfo($rangeName, $contextData = null) {
		//FIXME: is there any way to get around calling a Request (instead of a PKPRequest) here?
		$context =& Request::getContext();
		$pageNum = PKPRequest::getUserVar($rangeName . 'Page');
		if (empty($pageNum)) {
			$session =& PKPRequest::getSession();
			$pageNum = 1; // Default to page 1
			if ($session && $contextData !== null) {
				// See if we can get a page number from a prior request
				$contextHash = PKPHandler::hashPageContext($contextData);

				if (PKPRequest::getUserVar('clearPageContext')) {
					// Explicitly clear the old page context
					$session->unsetSessionVar("page-$contextHash");
				} else {
					$oldPage = $session->getSessionVar("page-$contextHash");
					if (is_numeric($oldPage)) $pageNum = $oldPage;
				}
			}
		} else {
			$session =& PKPRequest::getSession();
			if ($session && $contextData !== null) {
				// Store the page number
				$contextHash = PKPHandler::hashPageContext($contextData);
				$session->setSessionVar("page-$contextHash", $pageNum);
			}
		}

		if ($context) $count = $context->getSetting('itemsPerPage');
		if (!isset($count)) $count = Config::getVar('interface', 'items_per_page');

		import('db.DBResultRange');

		if (isset($count)) $returner = new DBResultRange($count, $pageNum);
		else $returner = new DBResultRange(-1, -1);

		return $returner;
	}

	function setupTemplate() {
		Locale::requireComponents(array(
			 LOCALE_COMPONENT_PKP_COMMON,
			 LOCALE_COMPONENT_PKP_USER
		));
		if (defined('LOCALE_COMPONENT_APPLICATION_COMMON')) {
			Locale::requireComponents(array(LOCALE_COMPONENT_APPLICATION_COMMON));
		}
	}
	
	/**
	 * Generate a unique-ish hash of the page's identity, including all
	 * context that differentiates it from other similar pages (e.g. all
	 * articles vs. all articles starting with "l").
	 * @param $contextData array A set of information identifying the page
	 * @return string hash
	 */
	function hashPageContext($contextData = array()) {
		return md5(
			implode(',', Request::getRequestedContextPath()) . ',' .
			Request::getRequestedPage() . ',' .
			Request::getRequestedOp() . ',' .
			serialize($contextData)
		);
	}	
	
	/**
	 * Get a list of pages that don't require login, even if the journal
	 * does.
	 * @return array
	 */
	function getLoginExemptions() {
		return array('user', 'login', 'help');
	}	
}

?>
