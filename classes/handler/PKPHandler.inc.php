<?php

/**
 * @file classes/core/PKPHandler.inc.php
 *
 * Copyright (c) 2000-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @package core
 * @class PKPHandler
 *
 * Base request handler abstract class.
 *
 */

import('handler.validation.HandlerValidator');
import('handler.validation.HandlerValidatorCustom');

class PKPHandler {
	/** Validation checks for this page*/
	var $_checks;

	/** @var Dispatcher, mainly needed for cross-router url construction */
	var $_dispatcher;

	/**
	 * Get the dispatcher
	 *
	 * NB: The dispatcher will only be set after
	 * handler instantiation. Calling getDispatcher()
	 * in the constructor will fail.
	 *
	 * @return PKPDispatcher
	 */
	function &getDispatcher() {
		assert(!is_null($this->_dispatcher));
		return $this->_dispatcher;
	}

	/**
	 * Set the dispatcher
	 * @param $dispatcher PKPDispatcher
	 */
	function setDispatcher(&$dispatcher) {
		$this->_dispatcher =& $dispatcher;
	}

	function PKPHandler() {
		$this->_checks = array();

		// enforce SSL sitewide
		$this->addCheck(new HandlerValidatorCustom($this, null, null, null, create_function('$forceSSL, $protocol', 'if ($forceSSL && $protocol != \'https\') Request::redirectSSL(); else return true;'), array(Config::getVar('security', 'force_ssl'), Request::getProtocol())));

	}

	/**
	 * Fallback method in case request handler does not implement index method.
	 */
	function index() {
		PKPRequest::handle404();
	}

	/**
	 * Add a validation check to the handler.
	 * @param $handlerValidator HandlerValidator
	 */
	function addCheck($handlerValidator) {
		$this->_checks[] =& $handlerValidator;
	}

	/**
	 * Perform request access validation based on security settings.
	 * @param $requiredContexts array
	 */
	function validate($requiredContexts = null) {
		foreach ($this->_checks as $check) {
			// WARNING: This line is for PHP4 compatibility when
			// instantiating handlers without reference. Should not
			// be removed or otherwise used.
			$check->_setHandler($this);

			// check should redirect on fail and continue on pass
			// default action is to redirect to the index page on fail
			if ( !$check->isValid() ) {
				if ( $check->redirectToLogin ) {
					Validation::redirectLogin();
				} else {
					PKPRequest::redirect(null, 'index');
				}
			}
		}

		return true;
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
	 * Get a list of pages that don't require login, even if the system does
	 * @return array
	 */
	function getLoginExemptions() {
		return array('user', 'login', 'help');
	}
}

?>
