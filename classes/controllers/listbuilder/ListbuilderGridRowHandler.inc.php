<?php

/**
 * @file controllers/grid/sponsor/ListbuilderGridRowHandler.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ListbuilderGridRowHandler
 * @ingroup controllers_listbuilder
 *
 * @brief Handle sponsor grid row requests.
 */

import('controllers.grid.GridRowHandler');

class ListbuilderGridRowHandler extends GridRowHandler {
	/** @var boolean internal state variable, true if cell handler has been instantiated */
	var $_cellHandlerInstantiated = false;

	/**
	 * Constructor
	 */
	function ListbuilderGridRowHandler() {
		parent::GridRowHandler();
	}

	//
	// Overridden template methods
	//
	/*
	 * Configure the grid row
	 * @param PKPRequest $request
	 */
	function initialize(&$request) {
		// Only initialize once
		if ($this->getInitialized()) return;

		// add Grid Row Actions
		$this->setTemplate('controllers/listbuilder/listbuilderGridRow.tpl');		

		parent::initialize($request);
	}

	/**
	 * Get the row template - override base
	 * implementation to provide a sensible default.
	 * @return string
	 */
	function getTemplate() {
		if (is_null(parent::getTemplate())) {
			$this->setTemplate('controllers/listbuilder/listbuilderGridRow.tpl');
		}

		return parent::getTemplate();
	}
}