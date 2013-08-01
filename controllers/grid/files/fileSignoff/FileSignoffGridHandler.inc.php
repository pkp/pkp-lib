<?php
/**
 * @defgroup controllers_grid_files_fileSignoff
 */

/**
 * @file controllers/grid/files/fileSignoff/FileSignoffGridHandler.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FileSignoffGridHandler
 * @ingroup controllers_grid_files_fileSignoff
 *
 * @brief Base grid for file lists that allow for file signoff. This grid shows
 *  signoff columns in addition to the file name.
 */

import('lib.pkp.controllers.grid.files.SubmissionFilesGridHandler');
import('lib.pkp.controllers.grid.files.SignoffStatusFromFileGridColumn');
import('lib.pkp.controllers.grid.files.UploaderUserGroupGridColumn');

class FileSignoffGridHandler extends SubmissionFilesGridHandler {
	/** @var integer */
	var $_symbolic;

	/**
	 * Constructor
	 * @param $dataProvider GridDataProvider
	 * @param $stageId integer One of the WORKFLOW_STAGE_ID_* constants.
	 * @param $capabilities integer A bit map with zero or more
	 *  FILE_GRID_* capabilities set.
	 */
	function FileSignoffGridHandler($dataProvider, $stageId, $symbolic, $capabilities) {
		$this->_symbolic = $symbolic;
		parent::SubmissionFilesGridHandler($dataProvider, $stageId, $capabilities);
	}

	//
	// Implement template methods from PKPHandler
	//
	/**
	 * @copydoc PKPHandler::initialize()
	 */
	function initialize($request) {
		parent::initialize($request);
		$currentUser = $request->getUser();
		$submission = $this->getSubmission();

		$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /* @var $stageAssignmentDao StageAssignmentDAO */
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */

		// Set up the roles we may include as columns
		$roles = array(
			ROLE_ID_MANAGER => 'user.role.manager',
			ROLE_ID_SUB_EDITOR => 'user.role.seriesEditor',
			ROLE_ID_ASSISTANT => 'user.role.assistant'
		);

		// Get all the uploader user group id's
		$uploaderUserGroupIds = array();
		$dataElements = $this->getGridDataElements($request);
		foreach ($dataElements as $id => $rowElement) {
			$submissionFile = $rowElement['submissionFile'];
			$uploaderUserGroupIds[] = $submissionFile->getUserGroupId();
		}
		$uploaderUserGroupIds = array_unique($uploaderUserGroupIds);

		$userGroupIds = array();
		foreach ($roles as $roleId => $roleName) {
			$userIds = array();
			$assignments = $stageAssignmentDao->getBySubmissionAndRoleId($submission->getId(), $roleId, $this->getStageId());

			// Only include a role column if there is at least one user assigned from that role to this stage.
			if (!$assignments->wasEmpty()) {
				while ($assignment = $assignments->next()) {
					$userIds[] = $assignment->getUserId();
					$userGroupIds[] = $assignment->getUserGroupId();
				}

				$userIds = array_unique($userIds);
				$this->addColumn(
					new SignoffStatusFromFileGridColumn(
						'role-' . $roleId,
						$roleName,
						null,
						$this->getSymbolic(),
						$userIds,
						$this->getRequestArgs(),
						in_array($currentUser->getId(), $userIds),
						array('hoverTitle', true)
					)
				);
			}
			unset($assignments);
		}

		// Add a column for uploader User Groups not present in the previous groups
		$uploaderUserGroupIds = array_diff($uploaderUserGroupIds, array_unique($userGroupIds));
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		foreach ($uploaderUserGroupIds as $userGroupId) {
			$userGroup = $userGroupDao->getById($userGroupId);
			assert(is_a($userGroup, 'UserGroup'));
			$this->addColumn(new UploaderUserGroupGridColumn($userGroup));
		}
	}

	//
	// Getter/Setters
	//
	/**
	 * Get the signoff's symbolic
	 * @return integer
	 */
	function getSymbolic() {
		return $this->_symbolic;
	}


	//
	// Public Methods
	//
	/**
	 * Sign off the given file revision.
	 * @param $args array
	 * @param $request Request
	 */
	function signOffFile($args, $request) {
		// Retrieve the file to be signed off.
		$fileId = (int)$request->getUserVar('fileId');

		// Make sure that the file revision is in the grid.
		$submissionFiles = $this->getGridDataElements($request);
		if (!isset($submissionFiles[$fileId])) fatalError('Invalid file id!');
		assert(isset($submissionFiles[$fileId]['submissionFile']));
		$submissionFile = $submissionFiles[$fileId]['submissionFile'];
		assert(is_a($submissionFile, 'SubmissionFile'));

		// Retrieve the user.
		$user = $request->getUser();

		// Insert or update the sign off corresponding
		// to this file revision.
		$signoffDao = DAORegistry::getDAO('SignoffDAO'); /* @var $signoffDao SignoffDAO */
		$signoff = $signoffDao->build(
			$this->getSymbolic(), ASSOC_TYPE_SUBMISSION_FILE, $submissionFile->getFileId(), $user->getId()
		);
		$signoff->setDateCompleted(Core::getCurrentDate());
		$signoffDao->updateObject($signoff);

		$this->setupTemplate($request);
		NotificationManager::createTrivialNotification($user->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => __('notification.signedFile')));

		return DAO::getDataChangedEvent($fileId);
	}
}

?>
