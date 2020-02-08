<?php

/**
 * @file controllers/grid/files/form/LibraryFileForm.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LibraryFileForm
 * @ingroup controllers_grid_file_form
 *
 * @brief Form for adding/editing a file
 */

import('lib.pkp.classes.form.Form');
import('classes.file.LibraryFileManager');

class LibraryFileForm extends Form {
	/** the id of the context this library file is attached to */
	var $contextId;

	/** the library file manager instantiated in this form. */
	var $libraryFileManager;

	/**
	 * Constructor.
	 * @param $template string
	 * @param $contextId int
	 */
	function __construct($template, $contextId) {
		$this->contextId = $contextId;

		parent::__construct($template);
		$this->libraryFileManager = $libraryFileManager = new LibraryFileManager($contextId);

		$this->addCheck(new FormValidatorLocale($this, 'libraryFileName', 'required', 'settings.libraryFiles.nameRequired'));
		$this->addCheck(new FormValidatorCustom(
			$this, 'fileType', 'required', 'settings.libraryFiles.typeRequired',
			function($type) use ($libraryFileManager) {
				return is_numeric($type) && $libraryFileManager->getNameFromType($type);
			}
		));

		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	/**
	 * @copydoc Form::fetch()
	 */
	function fetch($request, $template = null, $display = false) {
		AppLocale::requireComponents(LOCALE_COMPONENT_APP_MANAGER);

		// load the file types for the selector on the form.
		$templateMgr = TemplateManager::getManager($request);
		$fileTypeKeys = $this->libraryFileManager->getTypeTitleKeyMap();
		$templateMgr->assign('fileTypes', $fileTypeKeys);

		return parent::fetch($request, $template, $display);
	}

	/**
	 * Assign form data to user-submitted data.
	 * @see Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(array('libraryFileName', 'fileType', 'publicAccess'));
	}
}


