<?php

/**
 * @file controllers/grid/settings/preparedEmails/PreparedEmailsGridHandler.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PreparedEmailsGridHandler
 * @ingroup controllers_grid_settings_preparedEmails
 *
 * @brief Handle preparedEmails grid requests.
 */

// Import grid base classes
import('lib.pkp.classes.controllers.grid.GridHandler');

// Import classes specific to this grid handler
import('controllers.grid.settings.preparedEmails.PreparedEmailsGridRow');

class PKPPreparedEmailsGridHandler extends GridHandler {
	/**
	 * Constructor
	 */
	function PKPPreparedEmailsGridHandler() {
		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER),
			array(
				'fetchRow', 'fetchGrid', 'addPreparedEmail', 'editPreparedEmail',
				'updatePreparedEmail', 'resetEmail', 'resetAllEmails',
				'disableEmail', 'enableEmail', 'deleteCustomEmail'
			)
		);
		parent::GridHandler();
	}

	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, $args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.PkpContextAccessPolicy');
		$this->addPolicy(new PkpContextAccessPolicy($request, $roleAssignments));
		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * @see GridHandler::initialize()
	 */
	function initialize($request, $args = null) {
		parent::initialize($request, $args);
		// Basic grid configuration
		$this->setId('preparedEmailsGrid');

		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_MANAGER, LOCALE_COMPONENT_PKP_USER);

		// Set the grid title.
		$this->setTitle('grid.preparedEmails.title');

		$this->setInstructions('grid.preparedEmails.description');

		// Grid actions
		import('lib.pkp.controllers.grid.settings.preparedEmails.linkAction.EditEmailLinkAction');
		$addEmailLinkAction = new EditEmailLinkAction($request);
		$this->addAction($addEmailLinkAction);

		import('lib.pkp.classes.linkAction.LinkAction');
		import('lib.pkp.classes.linkAction.request.RemoteActionConfirmationModal');
		$router = $request->getRouter();
		$this->addAction(
			new LinkAction(
				'resetAll',
				new RemoteActionConfirmationModal(
					__('manager.emails.resetAll.message'), null,
					$router->url($request, null,
						'grid.settings.preparedEmails.PreparedEmailsGridHandler', 'resetAllEmails')
				),
				__('manager.emails.resetAll'),
				'reset_default'
			)
		);


		// Columns
		import('lib.pkp.controllers.grid.settings.preparedEmails.PreparedEmailsGridCellProvider');
		$cellProvider = new PreparedEmailsGridCellProvider();
		$this->addColumn(new GridColumn('name', 'common.name', null, 'controllers/grid/gridCell.tpl', $cellProvider, array('width' => 40)));
		$this->addColumn(new GridColumn('sender', 'email.sender', null, 'controllers/grid/gridCell.tpl', $cellProvider, array('width' => 10)));
		$this->addColumn(new GridColumn('recipient', 'email.recipient', null, 'controllers/grid/gridCell.tpl', $cellProvider));
		$this->addColumn(new GridColumn('subject', 'common.subject', null, 'controllers/grid/gridCell.tpl', $cellProvider));
		$this->addColumn(new GridColumn('enabled', 'common.enabled', null, 'controllers/grid/common/cell/selectStatusCell.tpl', $cellProvider, array('width' => 5)));
	}

	/**
	 * @see GridHandler::initFeatures()
	 */
	function initFeatures($request, $args) {
		import('lib.pkp.classes.controllers.grid.feature.PagingFeature');
		return array(new PagingFeature());
	}

	/**
	 * @see GridHandler::loadData()
	 */
	function loadData($request, $filter) {
		// Elements to be displayed in the grid
		$emailTemplateDao = DAORegistry::getDAO('EmailTemplateDAO'); /* @var $emailTemplateDao EmailTemplateDAO */
		$context = $request->getContext();
		$emailTemplates = $emailTemplateDao->getEmailTemplates(AppLocale::getLocale(), $context->getId());
		foreach ($emailTemplates as $emailTemplate) {
			$rowData[$emailTemplate->getEmailKey()] = $emailTemplate;
		}
		return $rowData;
	}


	//
	// Overridden methods from GridHandler
	//
	/**
	 * Get the row handler - override the default row handler
	 * @return PreparedEmailsGridRow
	 */
	function getRowInstance() {
		assert(false); // Should be implemented by subclasses
	}


	//
	// Public handler methods
	//
	/**
	 * Create a new prepared email
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string Serialized JSON object
	 */
	function addPreparedEmail($args, $request) {
		return $this->editPreparedEmail($args, $request);
	}

	/**
	 * Edit a prepared email
	 * Will create a new prepared email if their is no emailKey in the request
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string Serialized JSON object
	 */
	function editPreparedEmail($args, $request) {
		assert(false); // Should be implemented by subclasses
	}

	/**
	 * Save the email editing form
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string Serialized JSON object
	 */
	function updatePreparedEmail($args, $request) {
		assert(false); // Should be implemented by subclasses
	}

	/**
	 * Reset a single email
	 * @param $args array
	 * @param $request Request
	 * @return string a serialized JSON object
	 */
	function resetEmail($args, $request) {
		assert(false); // Should be implemented by subclasses
	}

	/**
	 * Reset all email to stock.
	 * @param $args array
	 * @param $request Request
	 */
	function resetAllEmails($args, $request) {
		assert(false); // Should be implemented by subclasses
	}

	/**
	 * Disables an email template.
	 * @param $args array
	 * @param $request Request
	 */
	function disableEmail($args, $request) {
		assert(false); // Should be implemented by subclasses
	}


	/**
	 * Enables an email template.
	 * @param $args array
	 * @param $request Request
	 */
	function enableEmail($args, $request) {
		assert(false); // Should be implemented by subclasses
	}

	/**
	 * Delete a custom email.
	 * @param $args array
	 * @param $request Request
	 */
	function deleteCustomEmail($args, $request) {
		assert(false); // Should be implemented by subclasses
	}
}

?>
