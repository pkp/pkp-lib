<?php
/**
 * @file classes/security/authorization/SubmissionRequiredPolicy.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionRequiredPolicy
 * @ingroup security_authorization
 *
 * @brief Abstract base class for submission policies.
 */

import('lib.pkp.classes.security.authorization.AuthorizationPolicy');

class SubmissionRequiredPolicy extends AuthorizationPolicy {
	/** @var PKPRequest */
	var $_request;

	/** @var array */
	var $_args;

	/** @var string */
	var $_submissionParameterName;

	//
	// Getters and Setters
	//
	/**
	 * Return the request.
	 * @return PKPRequest
	 */
	function &getRequest() {
		return $this->_request;
	}

	/**
	 * Return the request arguments
	 * @return array
	 */
	function &getArgs() {
		return $this->_args;
	}

	/**
	 * Constructor
	 * @param $request PKPRequest
	 * @param $args array request parameters
	 * @param $submissionParameterName string the request parameter we expect
	 *  the submission id in.
	 * @param $message string
	 */
	function SubmissionRequiredPolicy(&$request, &$args, $submissionParameterName = 'submissionId', $message) {
		parent::AuthorizationPolicy($message);
		$this->_request =& $request;
		assert(is_array($args));
		$this->_args =& $args;
		$this->_submissionParameterName = $submissionParameterName;
	}

	//
	// Implement template methods from AuthorizationPolicy
	//
	/**
	 * @see AuthorizationPolicy::effect()
	 */
	function effect() {
		// Must be implemented by sub-classes.
		assert(false);
	}

	//
	// Protected helper method
	//
	/**
	 * Identifies a submission id in the request.
	 * @return integer|false returns false if no valid submission id could be found.
	 */
	function getSubmissionId() {
		// Identify the submission id.
		$router =& $this->_request->getRouter();
		switch(true) {
			case is_a($router, 'PKPPageRouter'):
				if ( is_numeric($this->_request->getUserVar($this->_submissionParameterName)) ) {
					// We may expect a submission id in the user vars
					return (int) $this->_request->getUserVar($this->_submissionParameterName);
				} else if (isset($this->_args[0]) && is_numeric($this->_args[0])) {
					// Or the submission id can be expected as the first path in the argument list
					return (int) $this->_args[0];
				}
				break;

			case is_a($router, 'PKPComponentRouter'):
				// We expect a named submission id argument.
				if (isset($this->_args[$this->_submissionParameterName])
						&& is_numeric($this->_args[$this->_submissionParameterName])) {
					return (int) $this->_args[$this->_submissionParameterName];
				}
				break;

			default:
				assert(false);
		}

		return false;
	}
}

?>
