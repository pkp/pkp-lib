<?php
/**
 * @defgroup classes_plugins_importexport import/export deployment
 */

/**
 * @file classes/plugins/importexport/PKPImportExportDeployment.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPImportExportDeployment
 * @ingroup plugins_importexport
 *
 * @brief Base class configuring the import/export process to an
 * application's specifics.
 */

use Illuminate\Database\Capsule\Manager as Capsule;

import('lib.pkp.classes.plugins.importexport.PKPImportExportFilter');

class PKPImportExportDeployment {
/**
	 * Array of possible validation errors
	 * @var array
	 */
	var $xmlValidationErrors = array();

	/**
	 * Indicator that the import/export process has failed
	 * @var bool
	 */
	var $processFailed = false;

	/**
	 * The import/export process result
	 * @var mixed
	 */
	var $processResult = null;

	/** @var Context The current import/export context */
	var $_context;

	/** @var User The current import/export user */
	var $_user;

	/** @var Submission The current import/export submission */
	var $_submission;

	/** @var PKPPublication The current import/export publication */
	var $_publication;

	/** @var array The processed import objects IDs */
	var $_processedObjectsIds = array();

	/** @var array Warnings keyed by object IDs */
	var $_processedObjectsErrors = array();

	/** @var array Errors keyed by object IDs */
	var $_processedObjectsWarnings = array();

	/** @var array Connection between the file from the XML import file and the new IDs after they are imported */
	var $_fileDBIds;

	/** @var array Connection between the submission file IDs from the XML import file and the new IDs after they are imported */
	var $_submissionFileDBIds;

	/** @var array Connection between the author id from the XML import file and the DB file IDs */
	var $_authorDBIds;

	/** @var string Base path for the import source */
	var $_baseImportPath = '';

	/** @var array A list of imported root elements to display to the user after the import is complete */
	var $_importedRootEntities;

	/** @var array A list of exported root elements to display to the user after the export is complete */
	var $_exportRootEntities;

	/**
	 * Constructor
	 * @param $context Context
	 * @param $user User optional
	 */
	function __construct($context, $user=null) {
		$this->setContext($context);
		$this->setUser($user);
		$this->setSubmission(null);
		$this->setPublication(null);
		$this->setFileDBIds(array());
		$this->setSubmissionFileDBIds(array());
		$this->_processedObjectsIds = array();
		$this->_importedRootEntities = array();
	}

	//
	// Deployment items for subclasses to override
	//
	/**
	 * Get the submission node name
	 * @return string
	 */
	function getSubmissionNodeName() {
		assert(false);
	}

	/**
	 * Get the submissions node name
	 * @return string
	 */
	function getSubmissionsNodeName() {
		assert(false);
	}

	/**
	 * Get the representation node name
	 */
	function getRepresentationNodeName() {
		assert(false);
	}

	/**
	 * Get the namespace URN
	 * @return string
	 */
	function getNamespace() {
		assert(false);
	}

	/**
	 * Get the schema filename.
	 * @return string
	 */
	function getSchemaFilename() {
		assert(false);
	}


	//
	// Getter/setters
	//
	/**
	 * Set the import/export context.
	 * @param $context Context
	 */
	function setContext($context) {
		$this->_context = $context;
	}

	/**
	 * Get the import/export context.
	 * @return Context
	 */
	function getContext() {
		return $this->_context;
	}

	/**
	 * Set the import/export submission.
	 * @param $submission Submission
	 */
	function setSubmission($submission) {
		$this->_submission = $submission;
		if ($submission) $this->addProcessedObjectId(ASSOC_TYPE_SUBMISSION, $submission->getId());
	}

	/**
	 * Get the import/export submission.
	 * @return Submission
	 */
	function getSubmission() {
		return $this->_submission;
	}

	/**
	 * Set the import/export publication.
	 * @param $publication PKPPublication
	 */
	function setPublication($publication) {
		$this->_publication = $publication;
		if ($publication) $this->addProcessedObjectId(ASSOC_TYPE_PUBLICATION, $publication->getId());
	}

	/**
	 * Get the import/export publication.
	 * @return PKPPublication
	 */
	function getPublication() {
		return $this->_publication;
	}

	/**
	 * Add the processed object ID.
	 * @param $assocType integer ASSOC_TYPE_...
	 * @param $assocId integer
	 */
	function addProcessedObjectId($assocType, $assocId) {
		$this->_processedObjectsIds[$assocType][] = $assocId;
	}

	/**
	 * Add the error message to the processed object ID.
	 * @param $assocType integer ASSOC_TYPE_...
	 * @param $assocId integer
	 * @param $errorMsg string
	 */
	function addError($assocType, $assocId, $errorMsg) {
		$this->_processedObjectsErrors[$assocType][$assocId][] = $errorMsg;
	}

	/**
	 * Add the warning message to the processed object ID.
	 * @param $assocType integer ASSOC_TYPE_...
	 * @param $assocId integer
	 * @param $warningMsg string
	 */
	function addWarning($assocType, $assocId, $warningMsg) {
		$this->_processedObjectsWarnings[$assocType][$assocId][] = $warningMsg;
	}

	/**
	 * Get the processed objects IDs.
	 * @param $assocType integer ASSOC_TYPE_...
	 * @return array
	 */
	function getProcessedObjectsIds($assocType) {
		if (array_key_exists($assocType, $this->_processedObjectsIds)) {
			return $this->_processedObjectsIds[$assocType];
		}
		return null;
	}

	/**
	 * Get the processed objects errors.
	 * @param $assocType integer ASSOC_TYPE_...
	 * @return array
	 */
	function getProcessedObjectsErrors($assocType) {
		if (array_key_exists($assocType, $this->_processedObjectsErrors)) {
			return $this->_processedObjectsErrors[$assocType];
		}
		return null;
	}
	/**
	 * Get the processed objects errors.
	 * @param $assocType integer ASSOC_TYPE_...
	 * @return array
	 */

	function getProcessedObjectsWarnings($assocType) {
		if (array_key_exists($assocType, $this->_processedObjectsWarnings)) {
			return $this->_processedObjectsWarnings[$assocType];
		}
		return null;
	}

	/**
	 * Remove the processed objects.
	 * @param $assocType integer ASSOC_TYPE_...
	 */
	function removeImportedObjects($assocType) {
		switch ($assocType) {
			case ASSOC_TYPE_SUBMISSION:
				$processedSubmisssionsIds = $this->getProcessedObjectsIds(ASSOC_TYPE_SUBMISSION);
				if (!empty($processedSubmisssionsIds)) {
					$submissionDao = DAORegistry::getDAO('SubmissionDAO'); /* @var $submissionDao SubmissionDAO */
					foreach ($processedSubmisssionsIds as $submissionId) {
						if ($submissionId) {
							$submissionDao->deleteById($submissionId);
						}
					}
				}
				break;
		}
	}

	/**
	 * Set the import/export user.
	 * @param $user User
	 */
	function setUser($user) {
		$registeredUser = Registry::get('user', true, null);
		if (!isset($registeredUser)) {
			/**
			 * This is used in order to reconcile with possible $request->getUser()
			 * used inside import processes, when the import is done by CLI tool.
			 */
			Registry::set('user', $user);
		}

		$this->_user = $user;
	}

	/**
	 * Get the import/export user.
	 * @return User
	 */
	function getUser() {
		return $this->_user;
	}

	/**
	 * Get the array of the inserted file DB Ids.
	 * @return array
	 */
	function getFileDBIds() {
		return $this->_fileDBIds;
	}

	/**
	 * Set the array of the inserted file DB Ids.
	 * @param $fileDBIds array
	 */
	function setFileDBIds($fileDBIds) {
		return $this->_fileDBIds = $fileDBIds;
	}

	/**
	 * Get the file DB Id.
	 * @param $fileId integer The old file id
	 * @return integer The new file id
	 */
	function getFileDBId($fileId) {
		if (array_key_exists($fileId, $this->_fileDBIds)) {
			return $this->_fileDBIds[$fileId];
		}
		return null;
	}

	/**
	 * Set the file DB Id.
	 * @param $fileId integer The old file id
	 * @param $DBId integer The new file id
	 */
	function setFileDBId($fileId, $DBId) {
		return $this->_fileDBIds[$fileId] = $DBId;
	}

	/**
	 * Get the array of the inserted submission file DB Ids.
	 * @return array
	 */
	function getSubmissionFileDBIds() {
		return $this->_submissionFileDBIds;
	}

	/**
	 * Set the array of the inserted submission file DB Ids.
	 * @param $submissionFileDBIds array
	 */
	function setSubmissionFileDBIds($submissionFileDBIds) {
		return $this->_submissionFileDBIds = $submissionFileDBIds;
	}

	/**
	 * Get the submission file DB Id.
	 * @param $fileId integer The old submission file id
	 * @return integer The new submission file id
	 */
	function getSubmissionFileDBId($submissionFileDBId) {
		if (array_key_exists($submissionFileDBId, $this->_submissionFileDBIds)) {
			return $this->_submissionFileDBIds[$submissionFileDBId];
		}
		return null;
	}

	/**
	 * Set the submission file DB Id.
	 * @param $submissionFileDBId integer The old submission file id
	 * @param $DBId integer The new submission file id
	 */
	function setSubmissionFileDBId($submissionFileDBId, $DBId) {
		return $this->_submissionFileDBIds[$submissionFileDBId] = $DBId;
	}

	/**
	 * Set the array of the inserted author DB Ids.
	 * @param $authorDBIds array
	 */
	function setAuthorDBIds($authorDBIds) {
		return $this->_authorDBIds = $authorDBIds;
	}

	/**
	 * Get the array of the inserted author DB Ids.
	 * @return array
	 */
	function getAuthorDBIds() {
		return $this->_authorDBIds;
	}

	/**
	 * Get the author DB Id.
	 * @param $authorId integer
	 * @return integer?
	 */
	function getAuthorDBId($authorId) {
		if (array_key_exists($authorId, $this->_authorDBIds)) {
			return $this->_authorDBIds[$authorId];
		}

		return null;
	}

	/**
	 * Set the author DB Id.
	 * @param $authorId integer
	 * @param $DBId integer
	 */
	function setAuthorDBId($authorId, $DBId) {
		return $this->_authorDBIds[$authorId] = $DBId;
	}

	/**
	 * Set the directory location for the import source
	 * @param $path string
	 */
	function setImportPath($path) {
		$this->_baseImportPath = $path;
	}

	/**
	 * Get the directory location for the import source
	 * @return string
	 */
	function getImportPath() {
		return $this->_baseImportPath;
	}

	/**
	 * Add the imported root entities.
	 * @param $assocType integer ASSOC_TYPE_...
	 * @param $assocId integer
	 */
	function addImportedRootEntity($assocType, $entity) {
		$this->_importedRootEntities[$assocType][] = $entity;
	}

	/**
	 * Get the imported root entities.
	 * @param $assocType integer ASSOC_TYPE_...
	 */
	function getImportedRootEntities($assocType) {
		if (array_key_exists($assocType, $this->_importedRootEntities)) {
			return $this->_importedRootEntities[$assocType];
		}

		return null;
	}

	/**
	 * Set export root entities
	 * @param $exportRootEntities array
	 */
	function setExportRootEntities($exportRootEntities) {
		$this->_exportRootEntities = $exportRootEntities;
	}

	/**
	 * Get export root entities
	 * @return array
	 */
	function getExportRootEntities() {
		return $this->_exportRootEntities;
	}

	/**
	 * Wraps the import process
	 * @param $rootFilter string
	 * @param $importXml string
	 */
	function import($rootFilter, $importXml) {
		$dbConnection = Capsule::connection();
		try {
			$currentFilter = PKPImportExportFilter::getFilter($rootFilter, $this);

			$dbConnection->beginTransaction();

			libxml_use_internal_errors(true);

			$result = $currentFilter->execute($importXml);

			$this->xmlValidationErrors = array_filter(libxml_get_errors(), function($a) {
				return $a->level == LIBXML_ERR_ERROR || $a->level == LIBXML_ERR_FATAL;
			});

			libxml_clear_errors();

			$dbConnection->commit();

			$this->processResult = $result;
		} catch (Error | Exception $e) {
			$this->addError(ASSOC_TYPE_NONE, 0, $e->getMessage());
			$dbConnection->rollBack();

			$this->processFailed = true;
		}
	}

	/**
	 * Wraps the export process
	 * @param $rootFilter string
	 * @param $exportObjects array
	 * @param $opts array
	 */
	function export($rootFilter, $exportObjects, $opts = array()) {
		try {
			$this->setExportRootEntities($exportObjects);

			$currentFilter = PKPImportExportFilter::getFilter($rootFilter, $this, $opts);

			libxml_use_internal_errors(true);
			$result = $currentFilter->execute($exportObjects, true);

			$this->xmlValidationErrors = array_filter(libxml_get_errors(), function($a) {
				return $a->level == LIBXML_ERR_ERROR || $a->level == LIBXML_ERR_FATAL;
			});

			libxml_clear_errors();

			if (!$result) {
				$this->addError(ASSOC_TYPE_NONE, 0, 'Export result is empty.');
				$this->processFailed = true;
			}

			$this->processResult = $result;
		} catch (Error | Exception $e) {
			$this->addError(ASSOC_TYPE_NONE, 0, $e->getMessage());

			$this->processFailed = true;
		}
	}

	/**
	 * Getter method for XMLValidation Errors
	 * @return array
	 */
	function getXMLValidationErrors() {
		return $this->xmlValidationErrors;
	}

	/**
	 * Get all public objects, with their
	 * respective names as array values.
	 * @return array
	 */
	protected function getObjectTypes() {
		AppLocale::requireComponents(LOCALE_COMPONENT_APP_EDITOR);
		$objectTypes = array(
			ASSOC_TYPE_NONE => __('plugins.importexport.native.common.any'),
			ASSOC_TYPE_SUBMISSION => __('submission.submission'),
		);

		return $objectTypes;
	}

	/**
	* Get object type string.
	* @param $assocType mixed int or null (optional)
	* @return mixed string or array
	*/
	function getObjectTypeString($assocType = null) {
		$objectTypes = $this->getObjectTypes();

		if (is_null($assocType)) {
			return $objectTypes;
		} else {
			if (isset($objectTypes[$assocType])) {
				return $objectTypes[$assocType];
			} else {
				assert(false);
			}
		}
	}

	/**
	 * Get possible Warnings and Errors from the import/export process
	 * @return array
	 */
	function getWarningsAndErrors() {
		$problems = array();
		$objectTypes = $this->getObjectTypes();
		foreach ($objectTypes as $assocType => $name) {
			$foundWarnings = $this->getProcessedObjectsWarnings($assocType);
			if (!empty($foundWarnings)) {
				$problems['warnings'][$name][] = $foundWarnings;
			}

			$foundErrors = $this->getProcessedObjectsErrors($assocType);
			if (!empty($foundErrors)) {
				$problems['errors'][$name][] = $foundErrors;
			}
		}

		return $problems;
	}

	/**
	 * Get import entities with their names
	 * @return array
	 */
	function getImportedRootEntitiesWithNames() {
		$rootEntities = array();
		$objectTypes = $this->getObjectTypes();
		foreach ($objectTypes as $assocType => $name) {
			$entities = $this->getImportedRootEntities($assocType);
			if (!empty($entities)) {
				$rootEntities[$name][] = $entities;
			}
		}

		return $rootEntities;
	}

	/**
	 * Returns an indication that the import/export process has failed
	 * @return bool
	 */
	function isProcessFailed() {
		if ($this->processFailed || count($this->xmlValidationErrors) > 0) {
			return true;
		}

		return false;
	}
}


