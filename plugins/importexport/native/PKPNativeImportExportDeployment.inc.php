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

	/** @var User The current import/export user */
	var $_user;

	/** @var Submission The current import/export submission */
	var $_submission;

	/**
	 * Constructor
	 * @param $context Context
	 * @param $user User
	 */
	function PKPNativeImportExportDeployment($context, $user) {
		$this->setContext($context);
		$this->setUser($user);
		$this->setSubmission(null);
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
		return 'http://pkp.sfu.ca';
	}

	/**
	 * Get the schema filename.
	 * @return string
	 */
	function getSchemaFilename() {
		return 'pkp-native.xsd';
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
	}

	/**
	 * Get the import/export submission.
	 * @return Submission
	 */
	function getSubmission() {
		return $this->_submission;
	}

	/**
	 * Set the import/export user.
	 * @param $user User
	 */
	function setUser($user) {
		$this->_user = $user;
	}

	/**
	 * Get the import/export user.
	 * @return User
	 */
	function getUser() {
		return $this->_user;
	}
}

?>
