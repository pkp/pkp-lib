<?php
/**
 * @file classes/security/authorization/internal/NavigationMenuRequiredPolicy.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionRequiredPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Policy that ensures that the request contains a valid submission.
 */

import('lib.pkp.classes.security.authorization.DataObjectRequiredPolicy');

class NavigationMenuRequiredPolicy extends DataObjectRequiredPolicy {
	/**
	 * Constructor
	 * @param $request PKPRequest
	 * @param $args array request parameters
	 * @param $navigationMenuParameterName string the request parameter we expect
	 *  the navigationMenu id in.
	 */
	function __construct($request, &$args, $navigationMenuParameterName = 'navigationMenuId', $operations = null) {
		parent::__construct($request, $args, $navigationMenuParameterName, 'user.authorization.invalidNavigationMenu', $operations);

		$callOnDeny = array($request->getDispatcher(), 'handle404', array());
		$this->setAdvice(
			AUTHORIZATION_ADVICE_CALL_ON_DENY,
			$callOnDeny
		);
	}

	//
	// Implement template methods from AuthorizationPolicy
	//
	/**
	 * @see DataObjectRequiredPolicy::dataObjectEffect()
	 */
	function dataObjectEffect() {
		// Get the submission id.
		$navigationMenuId = $this->getDataObjectId();
		if ($navigationMenuId === false) return AUTHORIZATION_DENY;

		// Validate the submission id.
		$navigationMenusDao = DAORegistry::getDAO('NavigationMenuDAO');
		$navigationMenu = $navigationMenusDao->getById($navigationMenuId);

		if (!is_a($navigationMenu, 'NavigationMenu')) return AUTHORIZATION_DENY;

		// Validate that this navigationMenu belongs to the current context.
		$context = $this->_request->getContext();
		if ($context->getId() !== $navigationMenu->getContextId()) return AUTHORIZATION_DENY;

		// Save the submission to the authorization context.
		$this->addAuthorizedContextObject(ASSOC_TYPE_NAVIGATION_MENU, $navigationMenu);
		return AUTHORIZATION_PERMIT;
	}
}

?>
