<?php

/**
 * @file controllers/listbuilder/settings/SetupListbuilderHandler.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SetupListbuilderHandler
 * @ingroup listbuilder
 *
 * @brief Base class for setup listbuilders
 */

import('lib.pkp.classes.controllers.listbuilder.ListbuilderHandler');

class SetupListbuilderHandler extends ListbuilderHandler {
	/** @var Context */
	var $_context;

	/**
	 * Constructor
	 */
	function SetupListbuilderHandler() {
		parent::ListbuilderHandler();
		$this->addRoleAssignment(
			ROLE_ID_MANAGER,
			array('fetch', 'fetchRow', 'save')
		);
	}

	/**
	 * Set the current context
	 * @param $context Context
	 */
	function setContext($context) {
		$this->_context = $context;
	}

	/**
	 * Get the current context
	 * @return Context
	 */
	function getContext() {
		return $this->_context;
	}

	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.PkpContextAccessPolicy');
		$this->addPolicy(new PkpContextAccessPolicy($request, $roleAssignments));
		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * @copydoc ListbuilderHandler::initialize()
	 */
	function initialize($request) {
		$this->setContext($request->getContext());
		return parent::initialize($request);
	}
}

?>
