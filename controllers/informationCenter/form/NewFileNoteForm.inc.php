<?php

/**
 * @file controllers/informationCenter/form/NewFileNoteForm.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NewFileNoteForm
 * @ingroup informationCenter_form
 *
 * @brief Form to display and post notes on a file
 */


import('lib.pkp.controllers.informationCenter.form.NewNoteForm');

class NewFileNoteForm extends NewNoteForm {
	/** @var int The ID of the submission file to attach the note to */
	var $fileId;

	/**
	 * Constructor.
	 */
	function NewFileNoteForm($fileId) {
		parent::NewNoteForm();

		$this->fileId = $fileId;
	}

	/**
	 * Return the assoc type for this note.
	 * @return int
	 */
	function getAssocType() {
		return ASSOC_TYPE_SUBMISSION_FILE;
	}

	/**
	 * Return the submit note button locale key.
	 * Can be overriden by subclasses.
	 * @return string
	 */
	function getSubmitNoteLocaleKey() {
		return 'informationCenter.addFileNote';
	}

	/**
	 * Return the assoc ID for this note.
	 * @return int
	 */
	function getAssocId() {
		return $this->fileId;
	}
}

?>
