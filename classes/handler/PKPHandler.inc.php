<?php

/**
 * @file classes/core/PKPHandler.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @package core
 * @class PKPHandler
 *
 * Base request handler abstract class.
 *
 */

// FIXME: remove these import statements - handler validators are deprecated.
import('lib.pkp.classes.handler.validation.HandlerValidator');
import('lib.pkp.classes.handler.validation.HandlerValidatorRoles');
import('lib.pkp.classes.handler.validation.HandlerValidatorCustom');

class PKPHandler {
	/**
	 * @var string identifier of the controller instance - must be unique
	 *  among all instances of a given controller type.
	 */
	var $_id;

	/** @var Dispatcher, mainly needed for cross-router url construction */
	var $_dispatcher;

	/** @var array validation checks for this page - deprecated! */
	var $_checks = array();

	/**
	 * @var array
	 *  The value of this variable should look like this:
	 *  array(
	 *    ROLE_ID_... => array(...allowed handler operations...),
	 *    ...
	 *  )
	 */
	var $_roleAssignments = array();

	/** @var AuthorizationDecisionManager authorization decision manager for this handler */
	var $_authorizationDecisionManager;

	/**
	 * Constructor
	 */
	function PKPHandler() {
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
	 *
	 * NB: deprecated!
	 *
	 * @param $handlerValidator HandlerValidator
	 */
	function addCheck(&$handlerValidator) {
		// FIXME: Add a deprecation warning once we've refactored
		// all HandlerValidator occurrences.
		$this->_checks[] =& $handlerValidator;
	}

	/**
	 * Add an authorization policy for this handler which will
	 * be applied in the authorize() method.
	 *
	 * Policies must be added in the class constructor or in the
	 * subclasses' authorize() method before the parent::authorize()
	 * call so that PKPHandler::authorize() will be able to enforce
	 * them.
	 *
	 * @param $authorizationPolicy AuthorizationPolicy
	 * @param $addToTop boolean whether to insert the new policy
	 *  to the top of the list.
	 */
	function addPolicy(&$authorizationPolicy, $addToTop = false) {
		if (is_null($this->_authorizationDecisionManager)) {
			// Instantiate the authorization decision manager
			import('lib.pkp.classes.security.authorization.AuthorizationDecisionManager');
			$this->_authorizationDecisionManager = new AuthorizationDecisionManager();
		}

		// Add authorization policies to the authorization decision manager.
		$this->_authorizationDecisionManager->addPolicy($authorizationPolicy, $addToTop);
	}

	/**
	 * Retrieve authorized context objects from the
	 * decision manager.
	 * @param $assocType integer any of the ASSOC_TYPE_* constants
	 * @return mixed
	 */
	function &getAuthorizedContextObject($assocType) {
		assert(is_a($this->_authorizationDecisionManager, 'AuthorizationDecisionManager'));
		return $this->_authorizationDecisionManager->getAuthorizedContextObject($assocType);
	}

	/**
	 * Get the authorized context.
	 *
	 * NB: You should avoid accessing the authorized context
	 * directly to avoid accidentally overwriting an object
	 * in the context. Try to use getAuthorizedContextObject()
	 * instead where possible.
	 *
	 * @return array
	 */
	function &getAuthorizedContext() {
		assert(is_a($this->_authorizationDecisionManager, 'AuthorizationDecisionManager'));
		return $this->_authorizationDecisionManager->getAuthorizedContext();
	}

	/**
	 * Retrieve the last authorization message from the
	 * decision manager.
	 * @return string
	 */
	function getLastAuthorizationMessage() {
		assert(is_a($this->_authorizationDecisionManager, 'AuthorizationDecisionManager'));
		$authorizationMessages =& $this->_authorizationDecisionManager->getAuthorizationMessages();
		return end($authorizationMessages);
	}

	/**
	 * Add role - operation assignments to the handler.
	 *
	 * @param $roleIds integer|array one or more of the ROLE_ID_*
	 *  constants
	 * @param $operations string|array a single method name or
	 *  an array of method names to be assigned.
	 */
	function addRoleAssignment($roleIds, $operations) {
		// Allow single operations to be passed in as scalars.
		if (!is_array($operations)) $operations = array($operations);

		// Allow single roles to be passed in as scalars.
		if (!is_array($roleIds)) $roleIds = array($roleIds);

		// Add the given operations to all roles.
		foreach($roleIds as $roleId) {
			// Create an empty assignment array if no operations
			// have been assigned to the given role before.
			if (!isset($this->_roleAssignments[$roleId])) {
				$this->_roleAssignments[$roleId] = array();
			}

			// Merge the new operations with the already assigned
			// ones for the given role.
			$this->_roleAssignments[$roleId] = array_merge(
				$this->_roleAssignments[$roleId],
				$operations
			);
		}
	}

	/**
	 * This method returns an assignment of operation names for the
	 * given role.
	 *
	 * @return array assignment for the given role.
	 */
	function getRoleAssignment($roleId) {
		if (!is_null($roleId)) {
			if (isset($this->_roleAssignments[$roleId])) {
				return $this->_roleAssignments[$roleId];
			} else {
				return null;
			}
		}
	}

	/**
	 * This method returns an assignment of roles to operation names.
	 *
	 * @return array assignments for all roles.
	 */
	function getRoleAssignments() {
		return $this->_roleAssignments;
	}

	/**
	 * Authorize this request.
	 *
	 * Routers will call this method automatically thereby enforcing
	 * authorization. This method will be called before the
	 * validate() method and before passing control on to the
	 * handler operation.
	 *
	 * NB: This method will be called once for every request only.
	 *
	 * @param $request Request
	 * @param $args array request arguments
	 * @param $roleAssignment array the operation role assignment,
	 *  see getRoleAssignment() for more details.
	 * @return boolean
	 */
	function authorize(&$request, &$args, $roleAssignments) {
		// Enforce restricted site access.
		import('lib.pkp.classes.security.authorization.RestrictedSiteAccessPolicy');
		$this->addPolicy(new RestrictedSiteAccessPolicy($request), true);

		// Enforce SSL site-wide.
		if ($this->requireSSL()) {
			import('lib.pkp.classes.security.authorization.HttpsPolicy');
			$this->addPolicy(new HttpsPolicy($request), true);
		}

		if (!defined('SESSION_DISABLE_INIT')) {
			// Add user roles in authorized context.
			$user = $request->getUser();
			if (is_a($user, 'User')) {
				import('lib.pkp.classes.security.authorization.UserRolesRequiredPolicy');
				$this->addPolicy(new UserRolesRequiredPolicy($request), true);
			}
		}

		// Make sure that we have a valid decision manager instance.
		assert(is_a($this->_authorizationDecisionManager, 'AuthorizationDecisionManager'));

		$router =& $request->getRouter();
		if (is_a($router, 'PKPPageRouter')) {
			// We have to apply a blacklist approach for page
			// controllers to maintain backwards compatibility:
			// Requests are implicitly authorized if no policy
			// explicitly denies access.
			$this->_authorizationDecisionManager->setDecisionIfNoPolicyApplies(AUTHORIZATION_PERMIT);
		} else {
			// We implement a strict whitelist approach for
			// all other components: Requests will only be
			// authorized if at least one policy explicitly
			// grants access and none denies access.
			$this->_authorizationDecisionManager->setDecisionIfNoPolicyApplies(AUTHORIZATION_DENY);
		}

		// Let the authorization decision manager take a decision.
		$decision = $this->_authorizationDecisionManager->decide();
		if ($decision == AUTHORIZATION_PERMIT) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Perform data integrity checks.
	 *
	 * This method will be called once for every request only.
	 *
	 * NB: Any kind of authorization check is now deprecated
	 * within this method. This method is purely meant for data
	 * integrity checks that do not lead to denial of access
	 * to resources (e.g. via redirect) like handler operations
	 * or data objects.
	 *
	 * @param $requiredContexts array
	 * @param $request Request
	 */
	function validate($requiredContexts = null, $request = null) {
		// FIXME: for backwards compatibility only - remove when request/router refactoring complete
		if (!isset($request)) {
			// FIXME: Trigger a deprecation warning when enough instances of this
			// call have been fixed to not clutter the error log.
			$request =& Registry::get('request');
		}

		foreach ($this->_checks as $check) {
			// Using authorization checks in the validate() method is deprecated
			// FIXME: Trigger a deprecation warning.

			// WARNING: This line is for PHP4 compatibility when
			// instantiating handlers without reference. Should not
			// be removed or otherwise used.
			// See <http://pkp.sfu.ca/wiki/index.php/Information_for_Developers#Use_of_.24this_in_the_constructor>
			// for a similar problem.
			$check->_setHandler($this);

			// check should redirect on fail and continue on pass
			// default action is to redirect to the index page on fail
			if ( !$check->isValid() ) {
				if ( $check->redirectToLogin ) {
					Validation::redirectLogin();
				} else {
					// An unauthorized page request will be re-routed
					// to the index page.
					$request->redirect(null, 'index');
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
	 * @param $args array
	 */
	function initialize(&$request, $args = null) {
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

		import('lib.pkp.classes.db.DBResultRange');

		if (isset($count)) $returner = new DBResultRange($count, $pageNum);
		else $returner = new DBResultRange(-1, -1);

		return $returner;
	}

	function setupTemplate() {
		AppLocale::requireComponents(
			LOCALE_COMPONENT_PKP_COMMON,
			LOCALE_COMPONENT_PKP_USER
		);
		if (defined('LOCALE_COMPONENT_APPLICATION_COMMON')) {
			AppLocale::requireComponents(LOCALE_COMPONENT_APPLICATION_COMMON);
		}

		$templateMgr =& TemplateManager::getManager();
		$templateMgr->assign('userRoles', $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES));
		$accessibleWorkflowStages = $this->getAuthorizedContextObject(ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES);
		if ($accessibleWorkflowStages) $templateMgr->assign('accessibleWorkflowStages', $accessibleWorkflowStages);
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
	 * FIXME: Delete this method when authorization re-factoring is complete.
	 * @return array
	 */
	function getLoginExemptions() {
		import('lib.pkp.classes.security.authorization.RestrictedSiteAccessPolicy');
		return RestrictedSiteAccessPolicy::_getLoginExemptions();
	}

	/**
	 * Assume SSL is required for all handlers, unless overridden in subclasses.
	 * @return boolean
	 */
	function requireSSL() {
		return true;
	}
}

?>
