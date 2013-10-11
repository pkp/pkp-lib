<?php
/**
 * @defgroup plugins_importexport_native Native import/export plugin
 */

/**
 * @file plugins/importexport/native/PKPNativeImportExportDeployment.inc.php
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPNativeImportExportDeployment
 * @ingroup plugins_importexport_native
 *
 * @brief Base class configuring the native import/export process to an
 * application's specifics.
 */

class PKPNativeImportExportDeployment {
	/** @var Context The current import/export context */
	var $_context;

	/**
	 * Constructor
	 * @param $context Context
	 */
	function PKPNativeImportExportDeployment($context) {
		$this->setContext($context);
	}

	//
	// Deploymenturation items for subclasses to override
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
}

?>
