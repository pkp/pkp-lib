<?php
/**
 * @file classes/handler/HandlerValidatorCustom.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class HandlerValidator
 * @ingroup security
 *
 * @brief Class to represent a page validation check.
 */

import('lib.pkp.classes.handler.validation.HandlerValidator');

class HandlerValidatorCustom extends HandlerValidator {
	/** additionalArguments to apss to the user function **/
	var $userFunctionArgs;

	/** If true, field is considered valid if user function returns false instead of true */
	var $complementReturn;

	/**
	 * Constructor.
	 * @param $handler Handler the associated form
	 * @param $message string the error message for validation failures (i18n key)
	 */

	function HandlerValidatorCustom(&$handler, $redirectLogin = false, $message = null, $urlArgs = array(), $userFunction, $userFunctionArgs = array(), $complementReturn = false) {
		parent::HandlerValidator($handler, $redirectLogin, $message, $urlArgs);
		$this->userFunction = $userFunction;
		$this->userFunctionArgs = $userFunctionArgs;
		$this->complementReturn = $complementReturn;
	}

	/**
	 * Check if field value is valid.
	 * Value is valid if it is empty and optional or validated by user-supplied function.
	 * @return boolean
	 */
	function isValid() {
		$ret = call_user_func_array($this->userFunction, $this->userFunctionArgs);
		return $this->complementReturn ? !$ret : $ret;
	}
}

?>
