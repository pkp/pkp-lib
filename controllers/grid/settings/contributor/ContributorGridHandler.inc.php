<?php

/**
 * @file controllers/grid/settings/contributor/ContributorGridHandler.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ContributorGridHandler
 * @ingroup controllers_grid_settings_contributor
 *
 * @brief Handle contributor grid requests.
 */

// Import grid base classes
import('lib.pkp.controllers.grid.settings.SetupGridHandler');

// Import Contributor grid specific classes
import('lib.pkp.controllers.grid.settings.contributor.ContributorGridRow');
import('lib.pkp.classes.linkAction.request.AjaxModal');

class ContributorGridHandler extends SetupGridHandler {
	/**
	 * Constructor
	 */
	function ContributorGridHandler() {
		parent::SetupGridHandler();
		$this->addRoleAssignment(array(ROLE_ID_MANAGER),
				array('fetchGrid', 'addContributor', 'editContributor', 'updateContributor', 'deleteContributor'));
	}

	//
	// Overridden template methods
	//
	/*
	 * Configure the grid
	 * @param $request PKPRequest
	 */
	function initialize($request) {
		parent::initialize($request);

		// Elements to be displayed in the grid
		$router = $request->getRouter();
		$context = $router->getContext($request);
		$contributors = $context->getSetting('contributors');
		$contributors = isset($contributors) ? $contributors : array();
		$this->setGridDataElements($contributors);

		// Add grid-level actions
		$router = $request->getRouter();

		$this->addAction(
			new LinkAction(
				'addContributor',
				new AjaxModal(
					$router->url($request, null, null, 'addContributor', null, array('gridId' => $this->getId())),
					__('grid.action.addContributor'),
					'modal_add_user',
					true
					),
				__('grid.action.addContributor'),
				'add_user')
		);

		// Columns
		$this->addColumn(
			new GridColumn(
				'institution',
				'grid.columns.institution',
				null,
				null,
				null,
				array('width' => 50, 'alignment' => COLUMN_ALIGNMENT_LEFT)
			)
		);
		$this->addColumn(
			new GridColumn(
				'url',
				'grid.columns.url',
				null,
				null,
				null,
				array('width' => 50, 'alignment' => COLUMN_ALIGNMENT_LEFT)
			)
		);
	}

	//
	// Overridden methods from GridHandler
	//
	/**
	 * Get the row handler - override the default row handler
	 * @return ContributorGridRow
	 */
	function getRowInstance() {
		return new ContributorGridRow();
	}

	//
	// Public Contributor Grid Actions
	//
	/**
	 * An action to add a new contributor
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string Serialized JSON object
	 */
	function addContributor($args, $request) {
		// Calling editContributor with an empty row id will add
		// a new contributor.
		return $this->editContributor($args, $request);
	}

	/**
	 * An action to edit a contributor
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function editContributor($args, $request) {
		$contributorId = isset($args['rowId']) ? $args['rowId'] : null;
		import('lib.pkp.controllers.grid.settings.contributor.form.ContributorForm');
		$contributorForm = new ContributorForm($contributorId);

		if ($contributorForm->isLocaleResubmit()) {
			$contributorForm->readInputData();
		} else {
			$contributorForm->initData($args, $request);
		}

		return new JSONMessage(true, $contributorForm->fetch($request));
	}

	/**
	 * Update a contributor
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function updateContributor($args, $request) {
		// -> contributorId must be present and valid
		// -> htmlId must be present and valid
		$contributorId = isset($args['rowId']) ? $args['rowId'] : null;
		import('lib.pkp.controllers.grid.settings.contributor.form.ContributorForm');
		$contributorForm = new ContributorForm($contributorId);
		$contributorForm->readInputData();

		if ($contributorForm->validate()) {
			$contributorForm->execute($request);

			// prepare the grid row data
			$row = $this->getRowInstance();
			$row->setGridId($this->getId());
			$row->setId($contributorForm->contributorId);
			$rowData = array('institution' => $contributorForm->getData('institution'),
							'url' => $contributorForm->getData('url'));
			$row->setData($rowData);
			$row->initialize($request);

			return DAO::getDataChangedEvent();
		} else {
			return new JSONMessage(false);
		}
	}

	/**
	 * Delete a contributor
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function deleteContributor($args, $request) {
		$contributorId = isset($args['rowId']) ? $args['rowId'] : null;
		$router = $request->getRouter();
		$context = $router->getContext($request);
		$contextSettingsDao = $context->getSettingsDAO();

		// get all of the contributors
		$contributors = $contextSettingsDao->getSetting($context->getId(), 'contributors');

		if ( isset($contributors[$contributorId]) ) {
			unset($contributors[$contributorId]);
			$contextSettingsDao->updateSetting($context->getId(), 'contributors', $contributors, 'object');
			return DAO::getDataChangedEvent();
		} else {
			return new JSONMessage(false, __('manager.setup.errorDeletingItem'));
		}
	}
}

?>
