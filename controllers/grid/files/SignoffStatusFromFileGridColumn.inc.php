<?php
/**
 * @file controllers/grid/files/SignoffStatusFromFileGridColumn.inc.php
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SignoffStatusFromFileGridColumn
 * @ingroup controllers_grid_files
 *
 * @brief Implements a grid column that displays the signoff status
 *  of a file.
 */

import('lib.pkp.controllers.grid.files.BaseSignoffStatusColumn');

class SignoffStatusFromFileGridColumn extends BaseSignoffStatusColumn {
	/* @var string */
	var $_symbolic;

	/* @var boolean */
	var $_allowSignoffs;

	/**
	 * Constructor
	 * @param $id string Column ID
	 * @param $title string Column title locale key
	 * @param $titleTranslated string Column title, translated
	 * @param $symbolic string Column symbolic name
	 * @param $userIds array List of user IDs
	 * @param $requestArgs array List of request parameters to include in URLs
	 * @param $allowSignoffs boolean Whether or not to allow the user to sign off this column
	 * @param $flags array Optional list of column flags
	 */
	function SignoffStatusFromFileGridColumn($id, $title, $titleTranslated, $symbolic, $userIds, $requestArgs, $allowSignoffs = false, $flags = array()) {
		$this->_symbolic = $symbolic;
		$this->_allowSignoffs = $allowSignoffs;

		parent::BaseSignoffStatusColumn(
			$id,
			$title,
			$titleTranslated,
			$userIds,
			$requestArgs,
			$flags
		);
	}

	//
	// Setters and Getters
	//
	function getSymbolic() {
		return $this->_symbolic;
	}

	//
	// Overridden methods from GridColumn
	//
	/**
	 * @copydoc GridColumn::getCellActions()
	 */
	function getCellActions($request, $row) {
		$status = $this->_getSignoffStatus($row);
		$actions = array();
		if (in_array($status, array('accepted', 'new')) && $this->_allowSignoffs) {
			// Retrieve the submission file.
			$submissionFile = $this->getSubmissionFile($row);

			// Assemble the request arguments for the signoff action.
			$actionArgs = $this->getRequestArgs();
			$actionArgs['fileId'] = $submissionFile->getFileId();

			// Instantiate the signoff action.
			$router = $request->getRouter();
			import('lib.pkp.classes.linkAction.request.AjaxAction');
			$signoffAction = new LinkAction(
				'fileSignoff',
				new AjaxAction(
					$router->url(
						$request, null, null, 'signOffFile',
						null, $actionArgs
					)
				),
				__('common.signoff'),
				'task '.$status
			);
			$actions[] = $signoffAction;
		}
		return $actions;
	}


	//
	// Protected helper methods
	//
	/**
	 * Get the submission file from the row.
	 * @param $row GridRow
	 * @return SubmissionFile
	 */
	function getSubmissionFile($row) {
		$submissionFileData =& $row->getData();
		assert(isset($submissionFileData['submissionFile']));
		$submissionFile = $submissionFileData['submissionFile']; /* @var $submissionFile SubmissionFile */
		assert(is_a($submissionFile, 'SubmissionFile'));
		return $submissionFile;
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
		$submissionFile = $this->getSubmissionFile($row);

		$userIds = $this->getUserIds();
		if (in_array($submissionFile->getUploaderUserId(), $userIds)) {
			return 'uploaded';

		} else {
			// The current user has to sign off the file
			$signoffDao = DAORegistry::getDAO('SignoffDAO'); /* @var $signoffDao SignoffDAO */
			$viewsDao = DAORegistry::getDAO('ViewsDAO'); /* @var $viewsDao ViewsDAO */
			$lastViewed = false;
			foreach ($userIds as $userId) {
				$signoffs = $signoffDao->getAllBySymbolic(
					$this->getSymbolic(),
					ASSOC_TYPE_SUBMISSION_FILE, $submissionFile->getFileId(),
					$userId
				);

				// Check if any of the signoffs signed off.
				while($signoff = $signoffs->next()) {
					if ($signoff->getDateCompleted()) {
						return 'completed';
					}
				}

				if (!$lastViewed) {
					// Find out whether someone in the user group already downloaded
					// (=viewed) the file.
					// no users means a blank column (should not happen).

					$lastViewed = $viewsDao->getLastViewDate(
						ASSOC_TYPE_SUBMISSION_FILE, $submissionFile->getFileIdAndRevision(),
						$userId
					);
				}
			}
			// At least one user viewed the file
			if($lastViewed) {
				return 'accepted';
			}

			// No views means a white square.
			return 'new';
		}
	}
}

?>
