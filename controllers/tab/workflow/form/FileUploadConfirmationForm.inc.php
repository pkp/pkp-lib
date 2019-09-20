<?php
/**
 * @file controllers/tab/workflow/form/FileUploadConfirmationForm.inc.php.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class XmlFileUploadConfirmation
 *
 * @brief  Confirm uploaded XML file
 */

import('lib.pkp.classes.form.Form');

class FileUploadConfirmationForm extends Form {
	private $fileStage;

	/**
	 * XmlFileUploadConfirmation constructor.
	 * @param $request
	 * @param $uploadedFile
	 * @param $revision
	 * @param $submissionId
	 * @param $stageId
	 * @param $fileStage
	 * @param $csrfToken
	 */
	function __construct($request, $uploadedFile, $revision, $submissionId, $stageId, $fileStage, $csrfToken) {
		parent::__construct('controllers/tab/workflow/form/fileSubmissionComplete.tpl');

		$this->setData('fileId', (int)$uploadedFile);
		$this->setData('revision', (int)$revision);
		$this->setData('submissionId', (int)$submissionId);
		$this->setData('stageId', (int)$stageId);
		$this->setData('fileStage', (int)$fileStage);
		$this->setData('csrfToken', $csrfToken);

	}


}
