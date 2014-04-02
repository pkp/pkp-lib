<?php
/**
 * @file classes/handler/validation/HandlerValidator.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class HandlerValidator
 * @ingroup security
 *
 * @brief Class to represent a page validation check.
 */

class HandlerValidator {

	/** The Handler associated with the check */
	var $handler;

	/** bool flag for redirecting **/
	var $redirectToLogin;

	/** message for login screen **/
	var $message;

	/** additional Args to pass in the URL **/
	var $additionalArgs;

	/**
	 * Constructor.
	 * @param $handler Handler the associated form
	 * @param $message string the error message for validation failures (i18n key)
	 */
	function HandlerValidator(&$handler, $redirectToLogin = false, $message = null, $additionalArgs = array()) {
		// All handler validators are deprecated and
		// only exist for backwards compatibility.
		// FIXME: Switch warning message on when handler validator re-factoring is complete:
		// if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');

		$this->handler =& $handler;
		$this->redirectToLogin = $redirectToLogin;
		$this->message = $message;
		$this->additionalArgs = $additionalArgs;
	}

	/**
	 * Check if field value is valid.
	 * Default check is that field is either optional or not empty.
	 * @return boolean
	 */
	function isValid() {
		return true;
	}

	/**
	 * Set the handler associated with this check. Used only for PHP4
	 * compatibility when instantiating without =& (which is deprecated).
	 * SHOULD NOT BE USED otherwise.
	 */
	function _setHandler(&$handler) {
		$this->handler =& $handler;
	}
}

?>
