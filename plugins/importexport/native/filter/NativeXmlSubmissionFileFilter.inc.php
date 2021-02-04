<?php

/**
 * @file plugins/importexport/native/filter/NativeXmlSubmissionFileFilter.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NativeXmlSubmissionFileFilter
 * @ingroup plugins_importexport_native
 *
 * @brief Base class that converts a Native XML document to a submission file
 */
use Illuminate\Database\Capsule\Manager as Capsule;

import('lib.pkp.plugins.importexport.native.filter.NativeImportFilter');

class NativeXmlSubmissionFileFilter extends NativeImportFilter {
	/**
	 * Constructor
	 * @param $filterGroup FilterGroup
	 */
	function __construct($filterGroup) {
		$this->setDisplayName('Native XML submission file import');
		parent::__construct($filterGroup);
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
	 * @return SubmissionFile|null Null if skipping this file
	 */
	function handleElement($node) {
		$deployment = $this->getDeployment();
		$submission = $deployment->getSubmission();
		$context = $deployment->getContext();
		$stageName = $node->getAttribute('stage');
		$submissionFileId = $node->getAttribute('id');
		$stageNameIdMapping = $deployment->getStageNameStageIdMapping();
		assert(isset($stageNameIdMapping[$stageName]));
		$stageId = $stageNameIdMapping[$stageName];
		$request = Application::get()->getRequest();
		$errorOccured = false;

		$genreId = null;
		$genreName = $node->getAttribute('genre');
		// Build a cached list of genres by context ID by name
		if ($genreName) {
			if (!isset($genresByContextId[$context->getId()])) {
				$genreDao = DAORegistry::getDAO('GenreDAO'); /* @var $genreDao GenreDAO */
				$genres = $genreDao->getByContextId($context->getId());
				while ($genre = $genres->next()) {
					foreach ($genre->getName(null) as $locale => $name) {
						$genresByContextId[$context->getId()][$name] = $genre;
					}
				}
			}
			if (!isset($genresByContextId[$context->getId()][$genreName])) {
				$deployment->addError(ASSOC_TYPE_SUBMISSION, $submission->getId(), __('plugins.importexport.common.error.unknownGenre', array('param' => $genreName)));
				$errorOccured = true;
			} else {
				$genre = $genresByContextId[$context->getId()][$genreName];
				$genreId = $genre->getId();
			}
		}

		$uploaderUsername = $node->getAttribute('uploader');
		$uploaderUserId = null;
		if (!$uploaderUsername) {
			$user = $deployment->getUser();
		} else {
			// Determine the user based on the username
			$userDao = DAORegistry::getDAO('UserDAO'); /* @var $userDao UserDAO */
			$user = $userDao->getByUsername($uploaderUsername);
		}
		$uploaderUserId = $user
			? (int) $user->getId()
			: Application::get()->getRequest()->getUser()->getId();

		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */
		$submissionFile = $submissionFileDao->newDataObject();
		$submissionFile->setData('submissionId', $submission->getId());
		$submissionFile->setData('locale', $submission->getLocale());
		$submissionFile->setData('fileStage', $stageId);
		$submissionFile->setData('createdAt', Core::getCurrentDate());
		$submissionFile->setData('updatedAt', Core::getCurrentDate());
		$submissionFile->setData('dateCreated', $node->getAttribute('date_created'));
		$submissionFile->setData('language', $node->getAttribute('language'));

		if ($caption = $node->getAttribute('caption')) {
			$submissionFile->setData('caption', $caption);
		}
		if ($copyrightOwner = $node->getAttribute('copyright_owner')) {
			$submissionFile->setData('copyrightOwner', $copyrightOwner);
		}
		if ($credit = $node->getAttribute('credit')) {
			$submissionFile->setData('credit', $credit);
		}
		if ($directSalesPrice = $node->getAttribute('direct_sales_price')) {
			$submissionFile->setData('directSalesPrice', $directSalesPrice);
		}
		if ($genreId) {
			$submissionFile->setData('genreId', $genreId);
		}
		if ($salesType = $node->getAttribute('sales_type')) {
			$submissionFile->setData('salesType', $salesType);
		}
		if ($sourceSubmissionFileId = $node->getAttribute('source_submission_file_id')) {
			$submissionFile->setData('sourceSubmissionFileId', $sourceSubmissionFileId);
		}
		if ($terms = $node->getAttribute('terms')) {
			$submissionFile->setData('terms', $terms);
		}
		if ($uploaderUserId) {
			$submissionFile->setData('uploaderUserId', $uploaderUserId);
		}
		if ($node->getAttribute('viewable') == 'true') {
			$submissionFile->setViewable(true);
		}

		// Handle metadata in subelements
		$allRevisionIds = [];
		for ($childNode = $node->firstChild; $childNode !== null; $childNode=$childNode->nextSibling) {
			if (is_a($childNode, 'DOMElement')) {
				switch ($childNode->tagName) {
					case 'creator':
					case 'description':
					case 'name':
					case 'publisher':
					case 'source':
					case 'sponsor':
					case 'subject':
						list($locale, $value) = $this->parseLocalizedContent($childNode);
						$submissionFile->setData($childNode->tagName, $value, $locale);
						break;
					case 'submission_file_ref':
						if ($submissionFile->getData('fileStage') == SUBMISSION_FILE_DEPENDENT) {
							$oldAssocId = $childNode->getAttribute('id');
							$newAssocId = $deployment->getSubmissionFileDBId($oldAssocId);
							if ($newAssocId) {
								$submissionFile->setData('assocType', ASSOC_TYPE_SUBMISSION_FILE);
								$submissionFile->setData('assocId', $newAssocId);
							}
						}
						break;
					case 'file':
						// File has already been imported so update file id
						if ($deployment->getFileDBId($childNode->getAttribute('id'))) {
							$newFileId = $deployment->getFileDBId($childNode->getAttribute('id'));
						} else {
							$newFileId = $this->handleRevisionElement($childNode);
						}
						if ($newFileId) {
							$allRevisionIds[] = $newFileId;
						}
						// If this is the current file revision, set the submission file id
						if ($childNode->getAttribute('id') == $node->getAttribute('file_id')) {
							$submissionFile->setData('fileId', $newFileId);
						}
						unset($newFileId);

						break;
					default:
						$deployment->addWarning(ASSOC_TYPE_SUBMISSION, $submission->getId(), __('plugins.importexport.common.error.unknownElement', array('param' => $node->tagName)));
				}
			}
		}

		if ($errorOccured) {
			return null;
		}

		// Add and edit the submission file revisions one-by-one so that a useful activity
		// log is built and past revisions can be accessed
		if (count($allRevisionIds) < 2) {
			$submissionFile = Services::get('submissionFile')->add($submissionFile, $request);
		} else {
			$currentFileId = $submissionFile->getData('fileId');
			$allRevisionIds = array_filter($allRevisionIds, function($fileId) use ($currentFileId) {
				return $fileId !== $currentFileId;
			});
			$allRevisionIds = array_values($allRevisionIds);
			foreach ($allRevisionIds as $i => $fileId) {
				if ($i === 0) {
					$submissionFile->setData('fileId', $fileId);
					$submissionFile = Services::get('submissionFile')->add($submissionFile, $request);
				} else {
					$submissionFile = Services::get('submissionFile')->edit($submissionFile, ['fileId' => $fileId], $request);
				}
			}
			$submissionFile = Services::get('submissionFile')->edit($submissionFile, ['fileId' => $currentFileId], $request);
		}

		$deployment->setSubmissionFileDBId($node->getAttribute('id'), $submissionFile->getId());

		return $submissionFile;
	}

	/**
	 * Handle a revision element
	 * @param $node DOMElement
	 * @return int|null The new file id if successful
	 */
	function handleRevisionElement($node) {
		$deployment = $this->getDeployment();
		$submission = $deployment->getSubmission();

		for ($childNode = $node->firstChild; $childNode !== null; $childNode=$childNode->nextSibling) {
			if (is_a($childNode, 'DOMElement')) {
				switch ($childNode->tagName) {
					case 'href':
						import('lib.pkp.classes.file.TemporaryFileManager');
						$temporaryFileManager = new TemporaryFileManager();
						$temporaryFilename = tempnam($temporaryFileManager->getBasePath(), 'src');
						$filesrc = $childNode->getAttribute('src');
						$errorFlag = false;
						if (preg_match('|\w+://.+|', $filesrc)) {
							// process as a URL
							$client = Application::get()->getHttpClient();
							$response = $client->request('GET', $filesrc);
							file_put_contents($temporaryFilename, $response->getBody());
							if (!filesize($temporaryFilename)) {
								$errorFlag = true;
							}
						} elseif (substr($filesrc, 0, 1) === '/') {
							// local file (absolute path)
							if (!copy($filesrc, $temporaryFilename)) {
								$errorFlag = true;
							}
						} elseif (is_readable($deployment->getImportPath() . '/' . $filesrc)) {
							// local file (relative path)
							$filesrc = $deployment->getImportPath() . '/' . $filesrc;
							if(!copy($filesrc, $temporaryFilename)) {
								$errorFlag = true;
							}
						} else {
							// unhandled file path
							$errorFlag = true;
						}
						if ($errorFlag) {
							$deployment->addError(ASSOC_TYPE_SUBMISSION, $submission->getId(), __('plugins.importexport.common.error.temporaryFileFailed', array('dest' => $temporaryFilename, 'source' => $filesrc)));
							$fileManager = new FileManager();
							$fileManager->deleteByPath($temporaryFilename);
							$temporaryFilename = '';
						}
						break;
					case 'embed':
						import('lib.pkp.classes.file.TemporaryFileManager');
						$temporaryFileManager = new TemporaryFileManager();
						$temporaryFilename = tempnam($temporaryFileManager->getBasePath(), 'embed');
						if (($e = $childNode->getAttribute('encoding')) != 'base64') {
							$deployment->addError(ASSOC_TYPE_SUBMISSION, $submission->getId(), __('plugins.importexport.common.error.unknownEncoding', array('param' => $e)));
						} else {
							$content = base64_decode($childNode->textContent, true);
							$errorFlag = false;
							if (!$content) {
								$deployment->addError(ASSOC_TYPE_SUBMISSION, $submission->getId(), __('plugins.importexport.common.error.encodingError', array('param' => $e)));
								$errorFlag = true;
							} elseif (!file_put_contents($temporaryFilename, $content)) {
								$deployment->addError(ASSOC_TYPE_SUBMISSION, $submission->getId(), __('plugins.importexport.common.error.temporaryFileFailed', array('dest' => $temporaryFilename, 'source' => 'embed')));
								$errorFlag = true;
							}
							if ($errorFlag) {
								$fileManager = new FileManager();
								$fileManager->deleteByPath($temporaryFilename);
								$temporaryFilename = '';
							}
						}
						break;
				}
			}
		}

		if ($temporaryFilename) {
			$fileSizeOnDisk = filesize($temporaryFilename);
			$expectedFileSize = $node->getAttribute('filesize');
			if ($fileSizeOnDisk != $expectedFileSize) {
				$deployment->addWarning(ASSOC_TYPE_SUBMISSION, $submission->getId(), __('plugins.importexport.common.error.filesizeMismatch', array('expected' => $expectedFileSize, 'actual' => $fileSizeOnDisk)));
			} else {
				clearstatcache(true, $temporaryFilename);
				import('lib.pkp.classes.file.FileManager');
				$fileManager = new FileManager();
				$submissionDir = Services::get('submissionFile')->getSubmissionDir($submission->getData('contextId'), $submission->getId());
				$newFileId = Services::get('file')->add(
					$temporaryFilename,
					$submissionDir . '/' . uniqid() . '.' . $node->getAttribute('extension')
				);
				$deployment->setFileDBId($node->getAttribute('id'), $newFileId);
			}
		}

		if ($newFileId) {
			return $newFileId;
		}
	}

	/**
	 * Parse an identifier node and set up the representation object accordingly
	 * @param $element DOMElement
	 * @param $submissionFile SubmissionFile
	 */
	function parseIdentifier($element, $submissionFile) {
		$deployment = $this->getDeployment();
		$advice = $element->getAttribute('advice');
		switch ($element->getAttribute('type')) {
			case 'internal':
				// "update" advice not supported yet.
				assert(!$advice || $advice == 'ignore');
				break;
			case 'public':
				if ($advice == 'update') {
					$submissionFile->setStoredPubId('publisher-id', $element->textContent);
				}
				break;
			default:
				if ($advice == 'update') {
					// Load pub id plugins
					$pubIdPlugins = PluginRegistry::loadCategory('pubIds', true, $deployment->getContext()->getId());
					$submissionFile->setStoredPubId($element->getAttribute('type'), $element->textContent);
				}
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


