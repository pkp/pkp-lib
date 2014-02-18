<?php
/**
 * @file controllers/grid/files/SignoffOnSignoffGridColumn.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SignoffOnSignoffGridColumn
 * @ingroup controllers_grid_files
 *
 * @brief Implements a grid column that displays the signoff status of a file.
 *
 */

import('lib.pkp.controllers.grid.files.BaseSignoffStatusColumn');

class SignoffOnSignoffGridColumn extends BaseSignoffStatusColumn {
	/**
	 * Constructor
	 * @param $title The title for the column
	 * @param $requestArgs array Parameters f5or cell actions.
	 */
	function SignoffOnSignoffGridColumn($title = null, $userIds = array(), $requestArgs, $flags = array()) {
		parent::BaseSignoffStatusColumn('considered', $title, null, $userIds, $requestArgs, $flags);
	}

	//
	// Overridden methods from GridColumn
	//
	/**
	 * @copydoc GridColumn::getCellActions()
	 */
	function getCellActions($request, $row) {
		$status = $this->_getSignoffStatus($row);
		$signoff = $row->getData();
		$user = $request->getUser();

		// Assemble the request arguments for the signoff action.
		$actionArgs = $this->getRequestArgs();
		$actionArgs['signoffId'] = $signoff->getId();

		$router = $request->getRouter();
		$actions = array();

		switch ($status) {
			case 'accepted':
			case 'new':
				// Instantiate the signoff action.
				import('lib.pkp.classes.linkAction.request.AjaxAction');
				$signoffAction = new LinkAction(
					'fileSignoff',
					new AjaxAction(
						$router->url(
							$request, null, null, 'signOffsignOff',
							null, $actionArgs
						)
					),
					__('common.signoff'),
					$status
				);
				$actions[] = $signoffAction;
				break;
			case 'completed':
				// Instantiate the delete signoff action.
				import('lib.pkp.classes.linkAction.request.AjaxAction');
				$signoffAction = new LinkAction(
					'fileUnconsider',
					new AjaxAction(
						$router->url(
							$request, null, null, 'deleteSignOffSignOff',
							null, $actionArgs
						)
					),
					__('common.signoff'),
					$status
				);
				$actions[] = $signoffAction;
				break;
		}

		return $actions;
	}


	//
	// Private helper methods
	//
	/**
	 * Identify the signoff status of a row.
	 * @param $row GridRow
	 * @return string
	 */
	function _getSignoffStatus($row) {
		$signoffInQuestion =& $row->getData();

		// Disabled status until the signoff is completed.
		if (!$signoffInQuestion->getDateCompleted()) {
			return 'unfinished';
		}

		$signoffDao = DAORegistry::getDAO('SignoffDAO'); /* @var $signoff SignoffDAO */
		$viewsDao = DAORegistry::getDAO('ViewsDAO'); /* @var $viewsDao ViewsDAO */
		$viewed = false;
		$fileIdAndRevision = $signoffInQuestion->getFileId() . '-' . $signoffInQuestion->getFileRevision();
		foreach ($this->getUserIds() as $userId) {
			$signoff = $signoffDao->getBySymbolic(
				'SIGNOFF_SIGNOFF',
				ASSOC_TYPE_SIGNOFF, $signoffInQuestion->getId(),
				$userId
			);
			// somebody in one of the user groups signed off on the file
			if ($signoff && $signoff->getDateCompleted()) {
				return 'completed';
			} else {
				// Find out whether someone in the user group already downloaded
				// (=viewed) the file.
				$viewed = $viewed || $viewsDao->getLastViewDate(ASSOC_TYPE_SUBMISSION_FILE, $fileIdAndRevision, $userId);
			}
		}

		// Any view means we can mark green.
		if($viewed) {
			return 'accepted';
		} else {
			return 'new';
		}
	}
}


?>
