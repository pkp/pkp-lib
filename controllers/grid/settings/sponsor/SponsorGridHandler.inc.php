<?php

/**
 * @file controllers/grid/settings/sponsor/SponsorGridHandler.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SponsorGridHandler
 * @ingroup controllers_grid_settings_sponsor
 *
 * @brief Handle sponsor grid requests.
 */

import('lib.pkp.controllers.grid.settings.SetupGridHandler');
import('lib.pkp.controllers.grid.settings.sponsor.SponsorGridRow');
import('lib.pkp.classes.linkAction.request.AjaxModal');

class SponsorGridHandler extends SetupGridHandler {
	/**
	 * Constructor
	 */
	function SponsorGridHandler() {
		parent::SetupGridHandler();
		$this->addRoleAssignment(array(ROLE_ID_MANAGER),
				array('fetchGrid', 'fetchRow', 'addSponsor', 'editSponsor', 'updateSponsor', 'deleteSponsor'));
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
		$sponsors = $context->getSetting('sponsors');
		$sponsors = isset($sponsors) ? $sponsors : array();
		$this->setGridDataElements($sponsors);

		// Add grid-level actions
		$router = $request->getRouter();
		$this->addAction(
			new LinkAction(
				'addSponsor',
				new AjaxModal(
					$router->url($request, null, null, 'addSponsor', null, array('gridId' => $this->getId())),
					__('grid.action.addSponsor'),
					'modal_add_user',
					true
					),
				__('grid.action.addSponsor'),
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
	 * @return SponsorGridRow
	 */
	protected function getRowInstance() {
		return new SponsorGridRow();
	}

	//
	// Public Sponsor Grid Actions
	//
	/**
	 * An action to add a new sponsor
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function addSponsor($args, $request) {
		// Calling editSponsor with an empty row id will add
		// a new sponsor.
		return $this->editSponsor($args, $request);
	}

	/**
	 * An action to edit a sponsor
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function editSponsor($args, $request) {
		$sponsorId = isset($args['rowId'])?$args['rowId']:null;

		import('lib.pkp.controllers.grid.settings.sponsor.form.SponsorForm');
		$sponsorForm = new SponsorForm($sponsorId);

		if ($sponsorForm->isLocaleResubmit()) {
			$sponsorForm->readInputData();
		} else {
			$sponsorForm->initData($args, $request);
		}

		return new JSONMessage(true, $sponsorForm->fetch($request));
	}

	/**
	 * Update a sponsor
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function updateSponsor($args, $request) {
		// -> sponsorId must be present and valid
		// -> htmlId must be present and valid
		$sponsorId = isset($args['rowId'])?$args['rowId']:null;

		import('lib.pkp.controllers.grid.settings.sponsor.form.SponsorForm');
		$sponsorForm = new SponsorForm($sponsorId);
		$sponsorForm->readInputData();

		if ($sponsorForm->validate()) {
			$sponsorForm->execute($request);

			// prepare the grid row data
			$row = $this->getRowInstance();
			$row->setGridId($this->getId());
			$row->setId($sponsorForm->sponsorId);
			$rowData = array('institution' => $sponsorForm->getData('institution'),
							'url' => $sponsorForm->getData('url'));
			$row->setData($rowData);
			$row->initialize($request);
			return DAO::getDataChangedEvent($sponsorForm->sponsorId);
		} else {
			return new JSONMessage(false);
		}
	}

	/**
	 * Delete a sponsor
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function deleteSponsor($args, $request) {
		$sponsorId = isset($args['rowId'])?$args['rowId']:null;
		$router = $request->getRouter();
		$context = $router->getContext($request);
		$contextSettingsDao = $context->getSettingsDAO();

		// get all of the sponsors
		$sponsors = $contextSettingsDao->getSetting($context->getId(), 'sponsors');

		if (isset($sponsors[$sponsorId])) {
			unset($sponsors[$sponsorId]);
			$contextSettingsDao->updateSetting($context->getId(), 'sponsors', $sponsors, 'object');
			return DAO::getDataChangedEvent($sponsorId);
		} else {
			return new JSONMessage(false, __('manager.setup.errorDeletingItem'));
		}
	}
}

?>
