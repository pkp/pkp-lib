<?php

/**
 * @file controllers/grid/settings/library/form/EditLibraryFileForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditLibraryFileForm
 * @ingroup controllers_grid_file_form
 *
 * @brief Form for editing a library file
 */

import('lib.pkp.controllers.grid.files.form.LibraryFileForm');

class EditLibraryFileForm extends LibraryFileForm {
	/** the file being edited, or null for new */
	var $libraryFile;

	/** the id of the context this library file is attached to */
	var $contextId;

	/**
	 * Constructor.
	 * @param $contextId int
	 * @param $fileType int LIBRARY_FILE_TYPE_...
	 * @param $fileId int optional
	 */
	function __construct($contextId, $fileId) {
		parent::__construct('controllers/grid/settings/library/form/editFileForm.tpl', $contextId);
		$libraryFileDao = DAORegistry::getDAO('LibraryFileDAO'); /* @var $libraryFileDao LibraryFileDAO */
		$this->libraryFile = $libraryFileDao->getById($fileId);

		if (!$this->libraryFile || $this->libraryFile->getContextId() != $this->contextId) {
			fatalError('Invalid library file!');
		}
	}

	/**
	 * Assign form data to user-submitted data.
	 * @see Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(array('temporaryFileId'));
		return parent::readInputData();
	}

	/**
	 * Initialize form data from current settings.
	 */
	function initData() {
		$this->_data = array(
			'libraryFileName' => $this->libraryFile->getName(null), // Localized
			'libraryFile' => $this->libraryFile, // For read-only info
			'publicAccess' => $this->libraryFile->getPublicAccess() ? true : false,
			'temporaryFileId' => null,
		);
	}

	/**
	 * @copydoc Form::execute()
	 */
	function execute(...$functionArgs) {
		$userId = Application::get()->getRequest()->getUser()->getId();

		// Fetch the temporary file storing the uploaded library file
		$temporaryFileDao = DAORegistry::getDAO('TemporaryFileDAO'); /* @var $temporaryFileDao TemporaryFileDAO */
		$temporaryFile = $temporaryFileDao->getTemporaryFile(
			$this->getData('temporaryFileId'),
			$userId
		);
		if ($temporaryFile) {
			$libraryFileDao = DAORegistry::getDAO('LibraryFileDAO'); /* @var $libraryFileDao LibraryFileDAO */
			$libraryFileManager = new LibraryFileManager($this->contextId);

			// Convert the temporary file to a library file and store
			$this->libraryFile = $libraryFileManager->replaceFromTemporaryFile($temporaryFile, $this->getData('fileType'), $this->libraryFile);
			// Clean up the temporary file
			import('lib.pkp.classes.file.TemporaryFileManager');
			$temporaryFileManager = new TemporaryFileManager();
			$temporaryFileManager->deleteById($this->getData('temporaryFileId'), $userId);
		}

		$this->libraryFile->setName($this->getData('libraryFileName'), null); // Localized
		$this->libraryFile->setType($this->getData('fileType'));
		$this->libraryFile->setPublicAccess($this->getData('publicAccess'));

		$libraryFileDao = DAORegistry::getDAO('LibraryFileDAO'); /* @var $libraryFileDao LibraryFileDAO */
		$libraryFileDao->updateObject($this->libraryFile);
		parent::execute(...$functionArgs);
	}
}
