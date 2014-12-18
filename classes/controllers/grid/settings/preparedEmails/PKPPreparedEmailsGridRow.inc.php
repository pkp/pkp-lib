<?php

/**
 * @file classes/controllers/grid/settings/preparedEmails/PKPPreparedEmailsGridRow.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPPreparedEmailsGridRow
 * @ingroup controllers_grid_settings_PreparedEmails
 *
 * @brief Handle PreparedEmails grid row requests.
 */

import('lib.pkp.classes.controllers.grid.GridRow');

class PKPPreparedEmailsGridRow extends GridRow {
	/**
	 * Constructor
	 */
	function PKPPreparedEmailsGridRow() {
		parent::GridRow();
	}

	//
	// Overridden template methods
	//
	/**
	 * @copydoc GridRow::initialize()
	 */
	function initialize($request, $template = null) {
		parent::initialize($request, $template);

		// add Grid Row Actions
		$rowId = $this->getId();
		if (isset($rowId) && is_string($rowId)) {
			$contextId = $this->getContextId($request);
			$router = $request->getRouter();

			// Row action to edit the email template
			import('lib.pkp.controllers.grid.settings.preparedEmails.linkAction.EditEmailLinkAction');
			$this->addAction(new EditEmailLinkAction($request, $rowId));

			// Row action to disable/delete the email template
			$emailTemplateDao = DAORegistry::getDAO('EmailTemplateDAO'); /* @var $emailTemplateDao EmailTemplateDAO */
			$emailTemplate = $emailTemplateDao->getLocaleEmailTemplate($rowId, $contextId);
			if (isset($emailTemplate) && $emailTemplate->isCustomTemplate()) {
				$this->addAction(
					new LinkAction(
						'deleteEmail',
						new RemoteActionConfirmationModal(
							__('manager.emails.confirmDelete'), __('common.delete'),
							$router->url($request, null, 'grid.settings.preparedEmails.PreparedEmailsGridHandler',
								'deleteCustomEmail', null, array('emailKey' => $rowId)), 'modal_delete'
						),
						__('common.delete'),
						'disable'
					)
				);
			}

			// Row action to reset the email template to stock
			if (isset($emailTemplate) && !$emailTemplate->isCustomTemplate()) {
				$this->addAction(
					new LinkAction(
						'resetEmail',
						new RemoteActionConfirmationModal(
							__('manager.emails.reset.message'), null,
							$router->url($request, null, 'grid.settings.preparedEmails.PreparedEmailsGridHandler',
								'resetEmail', null, array('emailKey' => $rowId)), 'modal_delete'
						),
						__('manager.emails.reset'),
						'delete'
					)
				);
			}
		}
	}

	/**
	 * Return the context ID.
	 * @param $request PKPRequest
	 * @return int Context ID.
	 */
	function getContextId($request) {
		assert(false); // Needs to be implemented by subclasses
	}
}

?>
