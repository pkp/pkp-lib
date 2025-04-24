<?php

/**
 * @file plugins/importexport/native/filter/SubmissionFileNativeXmlFilter.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFileNativeXmlFilter
 * @ingroup plugins_importexport_native
 *
 * @brief Base class that converts a submissionFile to a Native XML document
 */

use function PHP81_BC\strftime;

import('lib.pkp.plugins.importexport.native.filter.NativeExportFilter');

class SubmissionFileNativeXmlFilter extends NativeExportFilter {
	/**
	 * Constructor
	 * @param $filterGroup FilterGroup
	 */
	function __construct($filterGroup) {
		$this->setDisplayName('Native XML submission file export');
		parent::__construct($filterGroup);
	}


	//
	// Implement template methods from PersistableFilter
	//
	/**
	 * @copydoc PersistableFilter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.plugins.importexport.native.filter.SubmissionFileNativeXmlFilter';
	}

	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::process()
	 * @param $submissionFile SubmissionFile
	 * @return DOMDocument
	 */
	function &process(&$submissionFile) {
		// Create the XML document
		$doc = new DOMDocument('1.0', 'utf-8');
		$doc->preserveWhiteSpace = false;
		$doc->formatOutput = true;
		$deployment = $this->getDeployment();
		$rootNode = $this->createSubmissionFileNode($doc, $submissionFile);

		if (!$rootNode) {
			return $rootNode;
		}
		$doc->appendChild($rootNode);
		$rootNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
		$rootNode->setAttribute('xsi:schemaLocation', $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename());

		return $doc;
	}

	//
	// SubmissionFile conversion functions
	//
	/**
	 * Create and return a submissionFile node.
	 * @param $doc DOMDocument
	 * @param $submissionFile SubmissionFile
	 * @return DOMElement
	 */
	function createSubmissionFileNode($doc, $submissionFile) {
		$deployment = $this->getDeployment();
		$context = $deployment->getContext();
		$stageToName = array_flip($deployment->getStageNameStageIdMapping());
		// Maps the retired/removed SUBMISSION_FILE_FAIR_COPY to SUBMISSION_FILE_FINAL (see: https://github.com/pkp/pkp-lib/issues/7470)
		$stageToName[7] = $stageToName[SUBMISSION_FILE_FINAL];
		// Maps the retired/removed SUBMISSION_FILE_FAIR_COPY to SUBMISSION_FILE_COPYEDIT (see: https://github.com/pkp/pkp-lib/issues/7470)
		$stageToName[8] = $stageToName[SUBMISSION_FILE_COPYEDIT];

		// Quit if the submission file has an invalid file stage
		if (!isset($stageToName[$submissionFile->getFileStage()])) {
			$deployment->addWarning(ASSOC_TYPE_SUBMISSION_FILE, $submissionFile->getId(), __('plugins.importexport.native.error.submissionFileInvalidFileStage', ['id' => $submissionFile->getId()]));
			return null;
		}

		$genreDao = DAORegistry::getDAO('GenreDAO'); /* @var $genreDao GenreDAO */
		$genre = $genreDao->getById($submissionFile->getData('genreId'));
		$userDao = DAORegistry::getDAO('UserDAO'); /* @var $userDao UserDAO */
		// @todo The uploader should always exist, see https://github.com/pkp/pkp-lib/issues/9414
		$uploaderUser = $submissionFile->getData('uploaderUserId') ? $userDao->getById($submissionFile->getData('uploaderUserId')) : null;

		// Create the submission_file node and set metadata
		$submissionFileNode = $doc->createElementNS($deployment->getNamespace(), $this->getSubmissionFileElementName());
		$submissionFileNode->setAttribute('id', $submissionFile->getId());
		$submissionFileNode->setAttribute('created_at', strftime('%Y-%m-%d', strtotime($submissionFile->getData('createdAt'))));
		$submissionFileNode->setAttribute('date_created', $submissionFile->getData('dateCreated'));
		$submissionFileNode->setAttribute('file_id', $submissionFile->getData('fileId'));
		$submissionFileNode->setAttribute('stage', $stageToName[$submissionFile->getFileStage()]);
		$submissionFileNode->setAttribute('updated_at', strftime('%Y-%m-%d', strtotime($submissionFile->getData('updatedAt'))));
		$submissionFileNode->setAttribute('viewable', $submissionFile->getViewable()?'true':'false');
		if ($caption = $submissionFile->getData('caption')) {
			$submissionFileNode->setAttribute('caption', $caption);
		}
		if ($copyrightOwner = $submissionFile->getData('copyrightOwner')) {
			$submissionFileNode->setAttribute('copyright_owner', $copyrightOwner);
		}
		if ($credit = $submissionFile->getData('credit')) {
			$submissionFileNode->setAttribute('credit', $credit);
		}
		if ($directSalesPrice = $submissionFile->getData('directSalesPrice')) {
			$submissionFileNode->setAttribute('direct_sales_price', $directSalesPrice);
		}
		if ($genre) {
			$submissionFileNode->setAttribute('genre', $genre->getName($context->getPrimaryLocale()));
		}
		if ($language = $submissionFile->getData('language')) {
			$submissionFileNode->setAttribute('language', $language);
		}
		if ($salesType = $submissionFile->getData('salesType')) {
			$submissionFileNode->setAttribute('sales_type', $salesType);
		}
		if ($sourceSubmissionFileId = $submissionFile->getData('sourceSubmissionFileId')) {
			$submissionFileNode->setAttribute('source_submission_file_id', $sourceSubmissionFileId);
		}
		if ($terms = $submissionFile->getData('terms')) {
			$submissionFileNode->setAttribute('terms', $terms);
		}
		if ($uploaderUser) {
			$submissionFileNode->setAttribute('uploader', $uploaderUser->getUsername());
		}
		$this->createLocalizedNodes($doc, $submissionFileNode, 'creator', $submissionFile->getData('creator'));
		$this->createLocalizedNodes($doc, $submissionFileNode, 'description', $submissionFile->getData('description'));
		$this->createLocalizedNodes($doc, $submissionFileNode, 'name', $submissionFile->getData('name'));
		$this->createLocalizedNodes($doc, $submissionFileNode, 'publisher', $submissionFile->getData('publisher'));
		$this->createLocalizedNodes($doc, $submissionFileNode, 'source', $submissionFile->getData('source'));
		$this->createLocalizedNodes($doc, $submissionFileNode, 'sponsor', $submissionFile->getData('sponsor'));
		$this->createLocalizedNodes($doc, $submissionFileNode, 'subject', $submissionFile->getData('subject'));

		// If it is a dependent file, add submission_file_ref element
		if ($submissionFile->getData('fileStage') == SUBMISSION_FILE_DEPENDENT && $submissionFile->getData('assocType') == ASSOC_TYPE_SUBMISSION_FILE) {
			$fileRefNode = $doc->createElementNS($deployment->getNamespace(), 'submission_file_ref');
			$fileRefNode->setAttribute('id', $submissionFile->getData('assocId'));
			$submissionFileNode->appendChild($fileRefNode);
		}

		// Add pub-id plugins
		$this->addIdentifiers($doc, $submissionFileNode, $submissionFile);

		// Create the revision nodes
		$revisions = DAORegistry::getDAO('SubmissionFileDAO')->getRevisions($submissionFile->getId());
		$hasRevision = false;
		foreach ($revisions as $revision) {
			$localPath = rtrim(Config::getVar('files', 'files_dir'), '/') . '/' . $revision->path;
			if (!file_exists($localPath)) {
				$deployment->addWarning(ASSOC_TYPE_SUBMISSION_FILE, $submissionFile->getId(), __('plugins.importexport.native.error.submissionFileRevisionMissing', ['id' => $submissionFile->getId(), 'revision' => $revision->revision_id, 'path' => $localPath]));
				continue;
			}
			$hasRevision = true;
			$revisionNode = $doc->createElementNS($deployment->getNamespace(), 'file');
			$revisionNode->setAttribute('id', $revision->fileId);
			$revisionNode->setAttribute('filesize', filesize($localPath));
			$revisionNode->setAttribute('extension', pathinfo($revision->path, PATHINFO_EXTENSION));

			if (isset($this->opts['serializationMode'])) {
				if ($this->opts['serializationMode'] == NativeExportFilter::SERIALIZATION_MODE_EMBED) {
					$embedNode = $doc->createElementNS($deployment->getNamespace(), 'embed', base64_encode(file_get_contents($localPath)));
					$embedNode->setAttribute('encoding', 'base64');
					$revisionNode->appendChild($embedNode);
				} else {
					$stageId = Services::get('submissionFile')->getWorkflowStageId($submissionFile);
					$dispatcher = Application::get()->getDispatcher();
					$request = Application::get()->getRequest();
					$params = [
						"submissionFileId" => $submissionFile->getId(),
						"submissionId" => $submissionFile->getData('submissionId'),
						"stageId" => $stageId,
						"fileId" => $revision->fileId,
					];

					$url = ($this->opts['serializationMode'] == NativeExportFilter::SERIALIZATION_MODE_RELATIVE_PATH)
						? $dispatcher->url($request, ROUTE_COMPONENT, $context->getPath(), "api.file.FileApiHandler", "downloadFile", null, $params, null, true)
						: $dispatcher->url($request, ROUTE_COMPONENT, $context->getPath(), "api.file.FileApiHandler", "downloadFile", null, $params);

					$hrefNode = $doc->createElementNS($deployment->getNamespace(), 'href');
					$hrefNode->setAttribute('src', htmlspecialchars($url, ENT_COMPAT, 'UTF-8'));
					$hrefNode->setAttribute('mime_type', $revision->mimetype);
					$revisionNode->appendChild($hrefNode);
				}
			} else {
				// Make this default behavior if no serialization mode is set
				if (!empty($this->opts['use-file-urls'])) {
					$stageId = Services::get('submissionFile')->getWorkflowStageId($submissionFile);
					$dispatcher = Application::get()->getDispatcher();
					$request = Application::get()->getRequest();
					$params = [
						"submissionFileId" => $submissionFile->getId(),
						"submissionId" => $submissionFile->getData('submissionId'),
						"stageId" => $stageId,
						"fileId" => $revision->fileId,
					];

					if (!empty($this->opts['use-absolute-urls'])) {
						$url = $dispatcher->url($request, ROUTE_COMPONENT, $context->getPath(), "api.file.FileApiHandler", "downloadFile", null, $params);
					} else {
						$url = $dispatcher->url($request, ROUTE_COMPONENT, $context->getPath(), "api.file.FileApiHandler", "downloadFile", null, $params, null, true);
					}

					$hrefNode = $doc->createElementNS($deployment->getNamespace(), 'href');
					$hrefNode->setAttribute('src', htmlspecialchars($url, ENT_COMPAT, 'UTF-8'));
					$hrefNode->setAttribute('mime_type', $revision->mimetype);
					$revisionNode->appendChild($hrefNode);
				} else if (empty($this->opts['no-embed'])) {
					$embedNode = $doc->createElementNS($deployment->getNamespace(), 'embed', base64_encode(file_get_contents($localPath)));
					$embedNode->setAttribute('encoding', 'base64');
					$revisionNode->appendChild($embedNode);
				}
			}

			$submissionFileNode->appendChild($revisionNode);
		}

		// Report if no revision has been added
		if (!$hasRevision) {
			$deployment->addWarning(ASSOC_TYPE_SUBMISSION_FILE, $submissionFile->getId(), __('plugins.importexport.native.error.submissionFileWithoutRevision', ['id' => $submissionFile->getId()]));
			return null;
		}

		return $submissionFileNode;
	}

	/**
	 * Create and add identifier nodes to a submission node.
	 * @param $doc DOMDocument
	 * @param $revisionNode DOMElement
	 * @param $submissionFile SubmissionFile
	 */
	function addIdentifiers($doc, $revisionNode, $submissionFile) {
		$deployment = $this->getDeployment();

		// Ommiting the internal ID here because it is in the submission_file attribute

		// Add public ID
		if ($pubId = $submissionFile->getStoredPubId('publisher-id')) {
			$revisionNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'id', htmlspecialchars($pubId, ENT_COMPAT, 'UTF-8')));
			$node->setAttribute('type', 'public');
			$node->setAttribute('advice', 'update');
		}

		// Add pub IDs by plugin
		$pubIdPlugins = PluginRegistry::loadCategory('pubIds', true, $deployment->getContext()->getId());
		foreach ($pubIdPlugins as $pubIdPlugin) {
			$this->addPubIdentifier($doc, $revisionNode, $submissionFile, $pubIdPlugin);
		}
	}

	/**
	 * Add a single pub ID element for a given plugin to the document.
	 * @param $doc DOMDocument
	 * @param $revisionNode DOMElement
	 * @param $submissionFile SubmissionFile
	 * @param $pubIdPlugin PubIdPlugin
	 * @return DOMElement|null
	 */
	function addPubIdentifier($doc, $revisionNode, $submissionFile, $pubIdPlugin) {
		$pubId = $submissionFile->getStoredPubId($pubIdPlugin->getPubIdType());
		if ($pubId) {
			$deployment = $this->getDeployment();
			$revisionNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'id', htmlspecialchars($pubId, ENT_COMPAT, 'UTF-8')));
			$node->setAttribute('type', $pubIdPlugin->getPubIdType());
			$node->setAttribute('advice', 'update');
			return $node;
		}
		return null;
	}

	/**
	 * Get the submission file element name
	 */
	function getSubmissionFileElementName() {
		return 'submission_file';
	}
}


