<?php

/**
 * @file controllers/grid/representations/RepresentationsGridCategoryRow.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2000-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class RepresentationsGridCategoryRow
 * @ingroup controllers_grid_representations
 *
 * @brief Representations grid row definition
 */

import('lib.pkp.classes.controllers.grid.GridCategoryRow');

class RepresentationsGridCategoryRow extends GridCategoryRow {

	/** @var Submission **/
	var $_submission;

	/**
	 * Constructor
	 * @param $submission Submission
	 * @param $cellProvider GridCellProvider
	 */
	function RepresentationsGridCategoryRow($submission, $cellProvider) {
		$this->_submission = $submission;
		parent::GridCategoryRow();
		$this->setCellProvider($cellProvider);
	}

	//
	// Overridden methods from GridCategoryRow
	//
	/**
	 * @copydoc GridCategoryRow::getCategoryLabel()
	 */
	function getCategoryLabel() {
		return $this->getData()->getLocalizedName();
	}


	//
	// Overridden methods from GridRow
	//
	/**
	 * @copydoc GridRow::initialize()
	 */
	function initialize($request, $template = null) {
		// Do the default initialization
		parent::initialize($request, $template);

		// Retrieve the submission from the request
		$submission = $this->getSubmission();

		// Is this a new row or an existing row?
		$representation = $this->getData();
		if ($representation && is_numeric($representation->getId())) {
			$router = $request->getRouter();
			$actionArgs = array(
				'submissionId' => $submission->getId(),
				'representationId' => $representation->getId()
			);

			// Add row-level actions
			import('lib.pkp.classes.linkAction.request.AjaxModal');
			$this->addAction(
				new LinkAction(
					'editFormat',
					new AjaxModal(
						$router->url($request, null, null, 'editFormat', null, $actionArgs),
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
					'deleteFormat',
					new RemoteActionConfirmationModal(
						__('common.confirmDelete'),
						__('common.delete'),
						$router->url($request, null, null, 'deleteFormat', null, $actionArgs),
						'modal_delete'
					),
					__('grid.action.delete'),
					'delete'
				)
			);
		}
	}

	/**
	 * Get the submission for this row (already authorized)
	 * @return Submission
	 */
	function getSubmission() {
		return $this->_submission;
	}
}
?>
