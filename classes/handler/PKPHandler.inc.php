<?php

/**
 * @file classes/core/PKPHandler.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
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
	/**
	 * @var string identifier of the controller instance - must be unique
	 *  among all instances of a given controller type.
	 */
	var $_id;

	/** @var Dispatcher, mainly needed for cross-router url construction */
	var $_dispatcher;

	/** @var array validation checks for this page*/
	var $_checks;

	/**
	 * Constructor
	 */
	function PKPHandler() {
		$this->_checks = array();

		// enforce SSL sitewide
		$this->addCheck(new HandlerValidatorCustom($this, null, null, null, create_function('$forceSSL, $protocol', 'if ($forceSSL && $protocol != \'https\') Request::redirectSSL(); else return true;'), array(Config::getVar('security', 'force_ssl'), Request::getProtocol())));

	}

	//
	// Setters and Getters
	//
	/**
	 * Set the controller id
	 * @param $id string
	 */
	function setId($id) {
		$this->_id = $id;
	}

	/**
	 * Get the controller id
	 * @return string
	 */
	function getId() {
		return $this->_id;
	}

	/**
	 * Get the dispatcher
	 *
	 * NB: The dispatcher will only be set after
	 * handler instantiation. Calling getDispatcher()
	 * in the constructor will fail.
	 *
	 * @return Dispatcher
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

	/**
	 * Fallback method in case request handler does not implement index method.
	 */
	function index() {
		$dispatcher =& $this->getDispatcher();
		if (isset($dispatcher)) $dispatcher->handle404();
		else Dispatcher::handle404(); // For old-style handlers
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
	 *
	 * This method will be called once for every request only.
	 *
	 * NB (non-page controllers only): The component router will call
	 * this method automatically thereby enforcing validation. This
	 * method will be call directly before the initialize() method.
	 *
	 * @param $requiredContexts array
	 * @param $request Request
	 */
	function validate($requiredContexts = null, $request = null) {
		// FIXME: for backwards compatibility only - remove when request/router refactoring complete
		if (!isset($request)) {
			if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function call.');
			$request =& Registry::get('request');
		}

		foreach ($this->_checks as $check) {
			// WARNING: This line is for PHP4 compatibility when
			// instantiating handlers without reference. Should not
			// be removed or otherwise used.
			// See <http://pkp.sfu.ca/wiki/index.php/Information_for_Developers#Use_of_.24this_in_the_constructor>
			// for a similar proplem.
			$check->_setHandler($this);

			// check should redirect on fail and continue on pass
			// default action is to redirect to the index page on fail
			if ( !$check->isValid() ) {
				$router =& $request->getRouter();
				if (is_a($router, 'PKPPageRouter')) {
					if ( $check->redirectToLogin ) {
						Validation::redirectLogin();
					} else {
						// An unauthorized page request will be re-routed
						// to the index page.
						$request->redirect(null, 'index');
					}
				} else {
					// Sub-controller requests should always be sufficiently
					// authorized and valid when being called from a
					// page. Otherwise we either hit a development error
					// or somebody is trying to fake component calls.
					// In both cases raising a fatal error is appropriate.
					// NB: The check's redirection flag will be ignored
					// for sub-controller requests.
					if (!empty($check->message)) {
						fatalError($check->message);
					} else {
						fatalError('Unauthorized access!');
					}
				}
			}
		}

		return true;
	}

	/**
	 * Subclasses can override this method to configure the
	 * handler.
	 *
	 * NB: This method will be called after validation and
	 * authorization.
	 *
	 * @param $request PKPRequest
	 */
	function initialize(&$request) {
		// Set the controller id to the requested
		// page (page routing) or component name
		// (component routing) by default.
		$router =& $request->getRouter();
		if (is_a($router, 'PKPComponentRouter')) {
			$componentId = $router->getRequestedComponent($request);
			// Create a somewhat compressed but still globally unique
			// and human readable component id.
			// Example: "grid.citation.CitationGridHandler"
			// becomes "grid-citation-citationgrid"
			$componentId = str_replace('.', '-', String::strtolower(String::substr($componentId, 0, -7)));
			$this->setId($componentId);
		} else {
			assert(is_a($router, 'PKPPageRouter'));
			$this->setId($router->getRequestedPage($request));
		}
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
		AppLocale::requireComponents(array(
			 LOCALE_COMPONENT_PKP_COMMON,
			 LOCALE_COMPONENT_PKP_USER
		));
		if (defined('LOCALE_COMPONENT_APPLICATION_COMMON')) {
			AppLocale::requireComponents(array(LOCALE_COMPONENT_APPLICATION_COMMON));
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
		return array('user', 'login', 'help', 'payment');
	}

	/**
	 * This method returns all operation names that can be called from remote.
	 * FIXME: Currently only used for component handlers. Use this for page
	 *  handlers as well and remove the page-specific index.php whitelist.
	 */
	function getRemoteOperations() {
		// Whitelist approach: by default we don't
		// allow any remote access at all.
		return array();
	}
}

?>
