<?php

/**
 * @file controllers/grid/content/navigation/SocialMediaGridRow.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SocialMediaGridRow
 * @ingroup controllers_grid_content_navigation
 *
 * @brief Social Media grid row definition
 */

import('lib.pkp.classes.controllers.grid.GridRow');

class SocialMediaGridRow extends GridRow {

	/**
	 * Constructor
	 */
	function SocialMediaGridRow(&$context) {
		parent::GridRow();
	}

	//
	// Overridden methods from GridRow
	//
	/**
	 * @see GridRow::initialize()
	 * @param $request PKPRequest
	 */
	function initialize($request) {
		// Do the default initialization
		parent::initialize($request);

		// Is this a new row or an existing row?
		$socialMedia = $this->_data;
		if ($socialMedia && is_numeric($socialMedia->getId())) {

			$router = $request->getRouter();
			$actionArgs = array(
				'socialMediaId' => $socialMedia->getId()
			);

			// Add row-level actions
			import('lib.pkp.classes.linkAction.request.AjaxModal');
			$this->addAction(
				new LinkAction(
					'editMedia',
					new AjaxModal(
						$router->url($request, null, null, 'editMedia', null, $actionArgs),
						__('grid.action.edit'),
						'modal_edit'
					),
					__('grid.action.edit'),
					'edit'
				)
			);

			import('lib.pkp.classes.linkAction.request.RemoteActionConfirmationModal');
			$this->addAction(
				new LinkAction(
					'deleteMedia',
					new RemoteActionConfirmationModal(
						__('common.confirmDelete'),
						__('common.delete'),
						$router->url($request, null, null, 'deleteMedia', null, $actionArgs),
						'modal_delete'
					),
					__('grid.action.delete'),
					'delete'
				)
			);
		}
	}
}
?>
