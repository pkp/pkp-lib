<?php
/**
 * @file controllers/grid/files/submissionDocuments/form/NewLibraryFileForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FileForm
 * @ingroup controllers_grid_files_submissionDocuments_form
 *
 * @brief Form for adding/edditing a file
 * stores/retrieves from an associative array
 */

import('lib.pkp.controllers.grid.files.form.LibraryFileForm');

class NewLibraryFileForm extends LibraryFileForm {

	/** @var int */
	var $submissionId;

	/**
	 * Constructor.
	 * @param $contextId int
	 */
	function __construct($contextId, $submissionId) {
		parent::__construct('controllers/grid/files/submissionDocuments/form/newFileForm.tpl', $contextId);
		$this->submissionId = $submissionId;
		$this->addCheck(new FormValidator($this, 'temporaryFileId', 'required', 'settings.libraryFiles.fileRequired'));
	}

	/**
	 * Assign form data to user-submitted data.
	 * @copydoc Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(array('temporaryFileId', 'submissionId'));
		return parent::readInputData();
	}

	/**
	 * @copydoc LibraryFileForm::fetch()
	 */
	function fetch($request, $template = null, $display = false) {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('submissionId', $this->getSubmissionId());
		return parent::fetch($request, $template, $display);
	}

	/**
	 * @copydoc Form::execute()
	 * @return $fileId int The new library file id.
	 */
	function execute(...$functionArgs) {
		$userId = Application::get()->getRequest()->getUser()->getId();

		// Fetch the temporary file storing the uploaded library file
		$temporaryFileDao = DAORegistry::getDAO('TemporaryFileDAO'); /* @var $temporaryFileDao TemporaryFileDAO */
		$temporaryFile = $temporaryFileDao->getTemporaryFile(
			$this->getData('temporaryFileId'),
			$userId
		);
		$libraryFileDao = DAORegistry::getDAO('LibraryFileDAO'); /* @var $libraryFileDao LibraryFileDAO */
		$libraryFileManager = new LibraryFileManager($this->contextId);

		// Convert the temporary file to a library file and store
		$libraryFile =& $libraryFileManager->copyFromTemporaryFile($temporaryFile, $this->getData('fileType'));
		assert(isset($libraryFile));
		$libraryFile->setContextId($this->contextId);
		$libraryFile->setName($this->getData('libraryFileName'), null); // Localized
		$libraryFile->setData('description', $this->getData('description'), null); // Localized
		$libraryFile->setType($this->getData('fileType'));
		$libraryFile->setSubmissionId($this->getData('submissionId'));

		$fileId = $libraryFileDao->insertObject($libraryFile);

		// Clean up the temporary file
		import('lib.pkp.classes.file.TemporaryFileManager');
		$temporaryFileManager = new TemporaryFileManager();
		$temporaryFileManager->deleteById($this->getData('temporaryFileId'), $userId);

		parent::execute(...$functionArgs);

		return $fileId;
	}

	/**
	 * return the submission ID for this library file.
	 * @return int
	 */
	function getSubmissionId() {
		return $this->submissionId;
	}
}


