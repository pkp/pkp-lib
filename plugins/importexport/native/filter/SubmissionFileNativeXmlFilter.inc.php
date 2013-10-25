<?php

/**
 * @file plugins/importexport/native/filter/SubmissionFileNativeXmlFilter.inc.php
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFileNativeXmlFilter
 * @ingroup plugins_importexport_native
 *
 * @brief Base class that converts a submissionFile to a Native XML document
 */

import('lib.pkp.plugins.importexport.native.filter.NativeExportFilter');

class SubmissionFileNativeXmlFilter extends NativeExportFilter {
	/**
	 * Constructor
	 * @param $filterGroup FilterGroup
	 */
	function SubmissionFileNativeXmlFilter($filterGroup) {
		$this->setDisplayName('Native XML submissionFile export');
		parent::NativeExportFilter($filterGroup);
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
		$doc = new DOMDocument('1.0');
		$deployment = $this->getDeployment();
		$rootNode = $this->createSubmissionFileNode($doc, $submissionFile);
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

		// Create the submission_file node and set metadata
		$submissionFileNode = $doc->createElementNS($deployment->getNamespace(), 'submission_file');

		$stageToName = array_flip($deployment->getStageNameStageIdMapping());
		$submissionFileNode->setAttribute('stage', $stageToName[$submissionFile->getFileStage()]);

		// Create the revision node and set metadata
		$revisionNode = $doc->createElementNS($deployment->getNamespace(), 'revision');
		$revisionNode->setAttribute('number', $submissionFile->getRevision());
		if ($sourceFileId = $submissionFile->getSourceFileId()) {
			$revisionNode->setAttribute('source', $sourceFileId . '-' . $submissionFile->getSourceRevision());
		}
		$revisionNode->setAttribute('name', $submissionFile->getOriginalFileName());
		$revisionNode->setAttribute('viewable', $submissionFile->getViewable()?'true':'false');
		$revisionNode->setAttribute('date_uploaded', strftime('%F', strtotime($submissionFile->getDateUploaded())));
		$revisionNode->setAttribute('date_modified', strftime('%F', strtotime($submissionFile->getDateModified())));

		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		$userGroup = $userGroupDao->getById($submissionFile->getUserGroupId());
		assert($userGroup);
		$revisionNode->setAttribute('user_group', $userGroup->getName($context->getPrimaryLocale()));

		$userDao = DAORegistry::getDAO('UserDAO');
		$uploaderUser = $userDao->getById($submissionFile->getUploaderUserId());
		assert($uploaderUser);
		$revisionNode->setAttribute('uploader', $uploaderUser->getUsername());

		$submissionFileNode->appendChild($revisionNode);

		// Embed the file contents
		$embedNode = $doc->createElementNS($deployment->getNamespace(), 'embed', 'FIXME CONTENT GOES HERE');
		$embedNode->setAttribute('encoding', 'base64');
		$revisionNode->appendChild($embedNode);

		return $submissionFileNode;
	}
}

?>
