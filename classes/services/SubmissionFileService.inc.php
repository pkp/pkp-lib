<?php
/**
 * @file classes/services/SubmissionFileService.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFileService
 * @ingroup services
 *
 * @brief Extends the base submission file service class with app-specific
 *  requirements.
 */

namespace APP\Services;

use \DAORegistry;
use \Application;
use \HookRegistry;

class SubmissionFileService extends \PKP\Services\PKPSubmissionFileService {

	/**
	 * Initialize hooks for extending PKPSubmissionService
	 */
	public function __construct() {
		HookRegistry::register('SubmissionFile::delete::before', array($this, 'deleteSubmissionFile'));
		HookRegistry::register('SubmissionFile::assignedFileStages', [$this, 'modifyAssignedFileStages']);
	}

	/**
	 * Delete related objects when a submission file is deleted
	 *
	 * @param string $hookName
	 * @param array $args [
	 *      @option SubmissionFile
	 * ]
	 */
	public function deleteSubmissionFile($hookName, $args) {
		$submissionFile = $args[0];

		// Remove galley associations and update search index
		if ($submissionFile->getData('assocType') == ASSOC_TYPE_REPRESENTATION) {
			$galleyDao = DAORegistry::getDAO('PreprintGalleyDAO'); /* @var $galleyDao PreprintGalleyDAO */
			$galley = $galleyDao->getById($submissionFile->getData('assocId'));
			if ($galley && $galley->getData('submissionFileId') == $submissionFile->getId()) {
				$galley->_data['submissionFileId'] = null; // Work around pkp/pkp-lib#5740
				$galleyDao->updateObject($galley);
			}
			import('lib.pkp.classes.search.SubmissionSearch');
			$preprintSearchIndex = Application::getSubmissionSearchIndex();
			$preprintSearchIndex->deleteTextIndex($submissionFile->getData('submissionId'), SUBMISSION_SEARCH_GALLEY_FILE, $submissionFile->getId());
		}
	}

	/**
	 * Allow authors to upload to galley file stages
	 *
	 * @param string $hookName
	 * @param array $args [
	 * ]
	 */
	public function modifyAssignedFileStages($hookName, $args) {
		$allowedFileStages =& $args[0];
		$stageAssignments = $args[1];

		if (array_key_exists(WORKFLOW_STAGE_ID_PRODUCTION, $stageAssignments)
				&& in_array(ROLE_ID_AUTHOR, $stageAssignments[WORKFLOW_STAGE_ID_PRODUCTION])) {
			$allowedFileStages[] = SUBMISSION_FILE_PROOF;
		}
	}
}
