<?php

/**
 * @file controllers/grid/files/dependent/DependentFilesGridHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DependentFilesGridHandler
 * @ingroup controllers_grid_files_dependent
 *
 * @brief Handle dependent files that are associated with a submissions's display
 *  (galleys or production formats, for example).
 * The submission author and all context/editor roles have access to this grid.
 */

import('lib.pkp.controllers.grid.files.fileList.FileListGridHandler');

class DependentFilesGridHandler extends FileListGridHandler {
	/**
	 * Constructor
	 */
	function __construct() {
		// import app-specific grid data provider for access policies.
		$request = Application::get()->getRequest();
		$submissionFileId = $request->getUserVar('submissionFileId'); // authorized in authorize() method.

		import('lib.pkp.controllers.grid.files.dependent.DependentFilesGridDataProvider');
		parent::__construct(
			new DependentFilesGridDataProvider($submissionFileId),
			$request->getUserVar('stageId')
		);

		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_AUTHOR),
			array('fetchGrid', 'fetchRow')
		);

		$this->setTitle('submission.submit.dependentFiles');
	}

	/**
	 * Get the authorized publication.
	 * @return Publication
	 */
	function getPublication() {
		return $this->getAuthorizedContextObject(ASSOC_TYPE_PUBLICATION);
	}

	/**
	 * @copydoc SubmissionFilesGridHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.SubmissionFileAccessPolicy');
		$this->addPolicy(new SubmissionFileAccessPolicy($request, $args, $roleAssignments, SUBMISSION_FILE_ACCESS_MODIFY, (int) $args['submissionFileId']));

		$publicationId = $request->getUserVar('publicationId'); // authorized in authorize() method.
		if ($publicationId) {
			import('lib.pkp.classes.security.authorization.PublicationAccessPolicy');
			$this->addPolicy(new PublicationAccessPolicy($request, $args, $roleAssignments));
		}

		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * @copydoc GridHandler::getRequestArgs()
	 */
	function getRequestArgs() {
		$submissionFile = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION_FILE);
		return array_merge(
			parent::getRequestArgs(),
			array('submissionFileId' => $submissionFile->getId())
		);
	}

	function initialize($request, $args = null) {
		$capabilities = FILE_GRID_ADD | FILE_GRID_DELETE | FILE_GRID_VIEW_NOTES | FILE_GRID_EDIT;

		$publication = $this->getPublication();
		
		if ($publication) {
			if ($publication->getData('status') == STATUS_PUBLISHED) {
				$capabilities = FILE_GRID_VIEW_NOTES;
			} 
		}

		$this->setCapabilities(new FilesGridCapabilities($capabilities));

		parent::initialize($request, $args);
	}
}


