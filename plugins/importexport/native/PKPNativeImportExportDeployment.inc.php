<?php

/**
 * @file plugins/importexport/native/PKPNativeImportExportDeployment.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPNativeImportExportDeployment
 * @ingroup plugins_importexport_native
 *
 * @brief Base class configuring the native import/export process to an
 * application's specifics.
 */

use Illuminate\Database\Capsule\Manager as Capsule;

import('lib.pkp.classes.plugins.importexport.PKPImportExportDeployment');
import('lib.pkp.plugins.importexport.native.filter.NativeImportExportFilter');

class PKPNativeImportExportDeployment extends PKPImportExportDeployment {

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

	/**
	 * Constructor
	 * @param $context Context
	 * @param $user User
	 */
	function __construct($context, $user) {
		parent::__construct($context, $user);
	}

	//
	// Deployment items for subclasses to override
	//
	/**
	 * Get the submission node name
	 * @return string
	 */
	function getSubmissionNodeName() {
		return 'submission';
	}

	/**
	 * Get the submissions node name
	 * @return string
	 */
	function getSubmissionsNodeName() {
		return 'submissions';
	}

	/**
	 * Get the namespace URN
	 * @return string
	 */
	function getNamespace() {
		return 'http://pkp.sfu.ca';
	}

	/**
	 * Get the schema filename.
	 * @return string
	 */
	function getSchemaFilename() {
		return 'pkp-native.xsd';
	}

	/**
	 * Get the mapping between stage names in XML and their numeric consts
	 * @return array
	 */
	function getStageNameStageIdMapping() {
		import('lib.pkp.classes.submission.SubmissionFile'); // Get file constants
		return array(
			'submission' => SUBMISSION_FILE_SUBMISSION,
			'note' => SUBMISSION_FILE_NOTE,
			'review_file' => SUBMISSION_FILE_REVIEW_FILE,
			'review_attachment' => SUBMISSION_FILE_REVIEW_ATTACHMENT,
			'final' => SUBMISSION_FILE_FINAL,
			'copyedit' => SUBMISSION_FILE_COPYEDIT,
			'proof' => SUBMISSION_FILE_PROOF,
			'production_ready' => SUBMISSION_FILE_PRODUCTION_READY,
			'attachment' => SUBMISSION_FILE_ATTACHMENT,
			'review_revision' => SUBMISSION_FILE_REVIEW_REVISION,
			'dependent' => SUBMISSION_FILE_DEPENDENT,
			'query' => SUBMISSION_FILE_QUERY,
		);
	}

	/**
	 * Wraps the import process
	 * @param $rootFilter string
	 * @param $importXml string
	 */
	function import($rootFilter, $importXml) {
		$dbConnection = Capsule::connection();
		try {
			$currentFilter = NativeImportExportFilter::getFilter($rootFilter, $this);

			$dbConnection->beginTransaction();

			libxml_use_internal_errors(true);

			$result = $currentFilter->execute($importXml);

			$this->xmlValidationErrors = array_filter(libxml_get_errors(), function($a) {
				return $a->level == LIBXML_ERR_ERROR || $a->level == LIBXML_ERR_FATAL;
			});

			libxml_clear_errors();

			$dbConnection->commit();

			$this->processResult = $result;
		} catch (\Error $e) {
			$this->addError(ASSOC_TYPE_NONE, 0, $e->getMessage());
			$dbConnection->rollBack();

			$this->processFailed = true;
		} catch (\Exception $e) {
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
	function export($rootFilter, $exportObjects, $opts = null) {
		try {
			$this->setExportRootEntities($exportObjects);

			$currentFilter = NativeImportExportFilter::getFilter($rootFilter, $this, $opts);

			$currentFilter->setOpts($opts);

			libxml_use_internal_errors(true);
			$result = $currentFilter->execute($exportObjects, true);

			$this->xmlValidationErrors = array_filter(libxml_get_errors(), function($a) {
				return $a->level == LIBXML_ERR_ERROR || $a->level == LIBXML_ERR_FATAL;
			});

			libxml_clear_errors();

			$this->processResult = $result;
		} catch (\Error $e) {
			$this->addError(ASSOC_TYPE_NONE, 0, $e->getMessage());

			$this->processFailed = true;
		} catch (Exception $e) {
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
	protected function getObjectTypesArray() {
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
		$objectTypes = $this->getObjectTypesArray();

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
		$objectTypes = $this->getObjectTypesArray();
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
		$objectTypes = $this->getObjectTypesArray();
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


