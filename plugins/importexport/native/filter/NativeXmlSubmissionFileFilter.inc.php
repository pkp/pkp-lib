<?php

/**
 * @file plugins/importexport/native/filter/NativeXmlSubmissionFileFilter.inc.php
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NativeXmlSubmissionFileFilter
 * @ingroup plugins_importexport_native
 *
 * @brief Base class that converts a Native XML document to a submission file
 */

import('lib.pkp.plugins.importexport.native.filter.NativeImportFilter');

class NativeXmlSubmissionFileFilter extends NativeImportFilter {
	/**
	 * Constructor
	 * @param $filterGroup FilterGroup
	 */
	function NativeXmlSubmissionFileFilter($filterGroup) {
		$this->setDisplayName('Native XML submission file import');
		parent::NativeImportFilter($filterGroup);
	}

	//
	// Implement template methods from NativeImportFilter
	//
	/**
	 * Return the plural element name
	 * @return string
	 */
	function getPluralElementName() {
		return 'submission_files';
	}

	/**
	 * Get the singular element name
	 * @return string
	 */
	function getSingularElementName() {
		return 'submission_file';
	}

	//
	// Implement template methods from PersistableFilter
	//
	/**
	 * @copydoc PersistableFilter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.plugins.importexport.native.filter.NativeXmlSubmissionFileFilter';
	}


	/**
	 * Handle a submission file element
	 * @param $node DOMElement
	 * @return array Array of SubmissionFile objects
	 */
	function handleElement($node) {
		$deployment = $this->getDeployment();
		$stageName = $node->getAttribute('stage');
		$stageNameIdMapping = $deployment->getStageNameStageIdMapping();
		assert(isset($stageNameIdMapping[$stageName]));
		$stageId = $stageNameIdMapping[$stageName];

		$submissionFiles = array();
		// Handle metadata in subelements
		for ($n = $node->firstChild; $n !== null; $n=$n->nextSibling) {
			if (is_a($n, 'DOMElement')) {
				$this->handleChildElement($n, $stageId, $submissionFiles);
			}
		}
		return $submissionFiles;
	}

	/**
	 * Handle a child node of the submission file element; add new files, if
	 * any, to $submissionFiles
	 * @param $node DOMElement
	 * @param $stageId int SUBMISSION_FILE_...
	 * @param $submissionFiles array
	 */
	function handleChildElement($node, $stageId, &$submissionFiles) {
		switch ($node->tagName) {
			case 'revision':
				$submissionFiles[] = $this->handleRevisionElement($node, $stageId);
				break;
			default:
				fatalError('Unknown element ' . $node->tagName);
		}
	}

	/**
	 * Handle a revision element
	 * @param $node DOMElement
	 * @param $stageId int SUBMISSION_FILE_...
	 */
	function handleRevisionElement($node, $stageId) {
		static $genresByContextId = array();

		$deployment = $this->getDeployment();
		$submission = $deployment->getSubmission();
		$context = $deployment->getContext();

		$genreName = $node->getAttribute('genre');
		// Build a cached list of genres by context ID by name
		if (!isset($genresByContextId[$context->getId()])) {
			$genreDao = DAORegistry::getDAO('GenreDAO');
			$genres = $genreDao->getByContextId($context->getId());
			while ($genre = $genres->next()) {
				foreach ($genre->getName(null) as $locale => $name) {
					$genresByContextId[$context->getId()][$name] = $genre;
				}
			}
		}
		if (!isset($genresByContextId[$context->getId()][$genreName])) {
			fatalError('Unknown genre "' . $genreName . '"!');
		}
		$genre = $genresByContextId[$context->getId()][$genreName];

		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
		$submissionFile = $submissionFileDao->newDataObjectByGenreId($genre->getId());
		$submissionFile->setSubmissionId($submission->getId());
		$submissionFile->setGenreId($genre->getId());
		$submissionFile->setFileStage($stageId);
		$submissionFile->setDateUploaded(Core::getCurrentDate());
		$submissionFile->setDateModified(Core::getCurrentDate());

		$submissionFile->setOriginalFileName($filename = $node->getAttribute('filename'));
		for ($n = $node->firstChild; $n !== null; $n=$n->nextSibling) {
			if (is_a($n, 'DOMElement')) {
				$filename = $this->handleRevisionChildElement($n, $submission, $submissionFile);
			}
		}

		$uploaderUsername = $node->getAttribute('uploader');
		$uploaderUserGroup = $node->getAttribute('user_group_ref');

		// Determine the user group based on the user_group_ref element.
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		$userGroups = $userGroupDao->getByContextId($context->getId());
		while ($userGroup = $userGroups->next()) {
			if (in_array($uploaderUserGroup, $userGroup->getName(null))) {
				$submissionFile->setUserGroupId($userGroup->getId());
			}
		}

		// Do the same for the user.
		$userDao = DAORegistry::getDAO('UserDAO');
		$user = $userDao->getByUsername($uploaderUsername);
		if ($user) {
			$submissionFile->setUploaderUserId($user->getId());
		}

		$fileSize = $node->getAttribute('filesize');
		$submissionFile->setFileSize($fileSize);

		$submissionFileDao->insertObject($submissionFile, $filename, false);
		return $submissionFile;
	}

	/**
	 * Handle a child of the revision element
	 * @param $node DOMElement
	 * @param $submission Submission
	 * @param $submissionFile SubmissionFile
	 * @return string Filename for new file
	 */
	function handleRevisionChildElement($node, $submission, $submissionFile) {
		switch ($node->tagName) {
			case 'name':
				$submissionFile->setName($node->textContent, $node->getAttribute('locale'));
				break;
			case 'remote':
				fatalError('UNIMPLEMENTED');
				break;
			case 'href':
				$submissionFile->setFileType($node->getAttribute('mime_type'));
				// Allow wrappers to handle URLs
				return $node->getAttribute('src');
				break;
			case 'embed':
				$submissionFile->setFileType($node->getAttribute('mime_type'));
				if (($e = $node->getAttribute('encoding')) != 'base64') {
					fatalError('Unknown encoding "' . $e . '"!');
				}
				$temporaryFileManager = new TemporaryFileManager();
				$temporaryFilename = tempnam($temporaryFileManager->getBasePath(), 'embed');
				file_put_contents($temporaryFilename, base64_decode($node->textContent));
				return $temporaryFilename;
				break;
		}
	}

	/**
	 * Instantiate a submission file.
	 * @param $tagName string
	 * @return SubmissionFile
	 */
	function instantiateSubmissionFile($tagName) {
		assert(false); // Subclasses should override
	}
}

?>
