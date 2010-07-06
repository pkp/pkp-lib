<?php
/**
 * @file classes/security/authorization/RoleAuthorizationContextHandler.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class RoleAuthorizationContextHandler
 * @ingroup security_authorization
 *
 * @brief An authorization context handler implementation that is able to
 *  check whether the currently logged in user has a given role.
 */

import('lib.pkp.classes.security.authorization.AuthorizationContextHandler');

class RoleAuthorizationContextHandler extends AuthorizationContextHandler {
	/** @var array */
	var $_roleContext = array();

	/** @var integer */
	var $_contextDepth;

	/** @var PKPUser */
	var $_user;

	/**
	 * Constructor
	 */
	function RoleAuthorizationContextHandler() {
		parent::AuthorizationContextHandler();

		// Initialize the context handler.
		$application =& PKPApplication::getApplication();
		$request =& $application->getRequest();

		$this->_user =& $request->getUser();
		if ($this->_user) {
			$router =& $request->getRouter();

			// Prepare the method call arguments for a
			// RoleDAO::roleExists() call, i.e. the context
			// ids plus the user id.
			$this->_contextDepth = $application->getContextDepth();
			for ($contextLevel = 1; $contextLevel <= $this->_contextDepth; $contextLevel++) {
				$context =& $router->getContext($request, $contextLevel);
				$this->_roleContext[] = ($context)?$context->getId():0;
				unset($context);
			}
			$this->_roleContext[] = $this->_user->getId();
		}
	}

	/**
	 * @see AuthorizationContextHandler::checkAttribute()
	 */
	function checkAttribute(&$value) {
		// The attribute value represents a role id.
		$roleId = (integer)$value;

		// Check the cache first.
		$cachedResponse = $this->retrieveCachedResponse($roleId);
		if (!is_null($cachedResponse)) return $cachedResponse;

		// Only continue if we don't have a cache hit.
		if ( !$this->_user ) return false;

		// Prepare the method arguments for a call to
		// RoleDAO::roleExists().
		$roleExistsArguments = $this->_roleContext;
		$roleExistsArguments[] = $roleId;

		$roleDao =& DAORegistry::getDAO('RoleDAO');
		if ($this->_contextDepth > 0) {
			// Correct context for site level or manager roles.
			if ( $roleId == ROLE_ID_SITE_ADMIN ) {
				// site level role
				for ($contextLevel = 1; $contextLevel <= $this->_contextDepth; $contextLevel++) {
					$roleExistsArguments[$contextLevel-1] = 0;
				}
			} elseif ( $roleId == $roleDao->getRoleIdFromPath('manager') && $this->_contextDepth == 2) {
				// main context managerial role (i.e. conference-level)
				$roleExistsArguments[1] = 0;
			}
		}

		// Call the role DAO.
		$response = (boolean)call_user_func_array(array($roleDao, 'roleExists'), $roleExistsArguments);

		// Cache the response then return.
		$this->cacheResponse($roleId, $response);
		return $response;
	}

	/**
	 * @see AuthorizationContextHandler::getAttribute()
	 */
	function getAttributeValues() {
		// Not implemented as long as we don't need this.
		assert(false);
	}
}

?>
