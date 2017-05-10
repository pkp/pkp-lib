<?php

/**
 * @file controllers/grid/users/stageParticipant/StageParticipantGridCellProvider.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class DataObjectGridCellProvider
 * @ingroup controllers_grid_users_submissionContributor
 *
 * @brief Cell provider to retrieve the user's name from the stage assignment
 */

import('lib.pkp.classes.controllers.grid.DataObjectGridCellProvider');

class StageParticipantGridCellProvider extends DataObjectGridCellProvider {
	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
	}

	//
	// Template methods from GridCellProvider
	//
	/**
	 * @copydoc GridCellProvider::getTemplateVarsFromRowColumn()
	 */
	function getTemplateVarsFromRowColumn($request, $row, $column) {
		switch ($column->getId()) {
			case 'participants':
				$stageAssignment = $row->getData();
				$userDao = DAORegistry::getDAO('UserDAO'); /* @var $userDao UserDAO */
				$user = $userDao->getById($stageAssignment->getUserId());
				return array('label' => $user->getFullName());
			default:
				assert(false);
		}
	}
}

?>
