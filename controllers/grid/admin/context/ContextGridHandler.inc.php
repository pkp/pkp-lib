<?php

/**
 * @file controllers/grid/admin/context/ContextGridHandler.inc.php
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ContextGridHandler
 * @ingroup controllers_grid_admin_context
 *
 * @brief Handle context grid requests.
 */

import('lib.pkp.classes.controllers.grid.GridHandler');
import('lib.pkp.controllers.grid.admin.context.ContextGridRow');

class ContextGridHandler extends GridHandler {
	/**
	 * Constructor
	 */
	function ContextGridHandler() {
		parent::GridHandler();
		$this->addRoleAssignment(array(
			ROLE_ID_SITE_ADMIN),
			array('fetchGrid', 'fetchRow', 'createContext', 'editContext', 'updateContext',
				'deleteContext', 'saveSequence')
		);
	}


	//
	// Implement template methods from PKPHandler.
	//
	/**
	 * @see PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.PolicySet');
		$rolePolicy = new PolicySet(COMBINING_PERMIT_OVERRIDES);

		import('lib.pkp.classes.security.authorization.RoleBasedHandlerOperationPolicy');
		foreach($roleAssignments as $role => $operations) {
			$rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
		}
		$this->addPolicy($rolePolicy);

		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * @see PKPHandler::initialize()
	 */
	function initialize($request) {
		parent::initialize($request);

		// Load user-related translations.
		AppLocale::requireComponents(
			LOCALE_COMPONENT_PKP_USER
		);

		// Grid actions.
		$router = $request->getRouter();

		import('lib.pkp.classes.linkAction.request.AjaxModal');
		$this->addAction(
			new LinkAction(
				'createContext',
				new AjaxModal(
					$router->url($request, null, null, 'createContext', null, null),
					__($this->_getAddContextKey()),
					'modal_add_item',
					true
					),
				__($this->_getAddContextKey()),
				'add_item')
		);

		//
		// Grid columns.
		//
		import('lib.pkp.controllers.grid.admin.context.ContextGridCellProvider');
		$contextGridCellProvider = new ContextGridCellProvider();

		// Context name.
		$this->addColumn(
			new GridColumn(
				'name',
				$this->_getContextNameKey(),
				null,
				'controllers/grid/gridCell.tpl',
				$contextGridCellProvider
			)
		);

		// Context path.
		$this->addColumn(
			new GridColumn(
				'path',
				'context.path',
				null,
				'controllers/grid/gridCell.tpl',
				$contextGridCellProvider
			)
		);
	}


	//
	// Implement methods from GridHandler.
	//
	/**
	 * @see GridHandler::getRowInstance()
	 * @return UserGridRow
	 */
	function getRowInstance() {
		return new ContextGridRow();
	}

	/**
	 * @see GridHandler::loadData()
	 * @param $request PKPRequest
	 * @return array Grid data.
	 */
	function loadData($request) {
		// Get all contexts.
		$contextDao = Application::getContextDAO();
		$contexts = $contextDao->getAll();

		return $contexts->toAssociativeArray();
	}

	/**
	 * @see lib/pkp/classes/controllers/grid/GridHandler::setDataElementSequence()
	 */
	function setDataElementSequence($request, $rowId, $context, $newSequence) {
		$contextDao = Application::getContextDAO();
		$context->setSequence($newSequence);
		$contextDao->updateObject($context);
	}

	/**
	 * @see lib/pkp/classes/controllers/grid/GridHandler::getDataElementSequence()
	 */
	function getDataElementSequence($context) {
		return $context->getSequence();
	}

	/**
	 * @see GridHandler::addFeatures()
	 */
	function initFeatures($request, $args) {
		import('lib.pkp.classes.controllers.grid.feature.OrderGridItemsFeature');
		return array(new OrderGridItemsFeature());
	}

	/**
	 * Get the list of "publish data changed" events.
	 * Used to update the site context switcher upon create/delete.
	 * @return array
	 */
	function getPublishChangeEvents() {
		return array('updateHeader');
	}


	//
	// Public grid actions.
	//
	/**
	 * Add a new context.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function createContext($args, $request) {
		// Calling editContext with an empty row id will add a new context.
		return $this->editContext($args, $request);
	}


	//
	// Protected helper methods.
	//
	/**
	 * Return a redirect event.
	 * @param $request Request
	 * @param $newContextPath string
	 * @param $openWizard boolean
	 */
	protected function _getRedirectEvent($request, $newContextPath, $openWizard) {
		$dispatcher = $request->getDispatcher();

		$url = $dispatcher->url($request, ROUTE_PAGE, $newContextPath, 'admin', 'contexts', null, array('openWizard' => $openWizard));
		return $request->redirectUrlJson($url);
	}

	/**
	 * Get the "add context" locale key
	 * @return string
	 */
	protected function _getAddContextKey() {
		assert(false); // Should be overridden by subclasses
	}

	/**
	 * Get the context name locale key
	 * @return string
	 */
	protected function _getContextNameKey() {
		assert(false); // Should be overridden by subclasses
	}
}

?>
