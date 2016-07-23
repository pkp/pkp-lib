<?php

/**
 * @file classes/form/validation/FormValidatorCSRF.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2000-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorCSRF
 * @ingroup form_validation
 *
 * @brief Form validation check to make sure the CSRF token is correct.
 */

import ('lib.pkp.classes.form.validation.FormValidator');

class FormValidatorCSRF extends FormValidator {
	/**
	 * Constructor.
	 * @param $form Form
	 * @param $message string the locale key to use (optional)
	 */
	function FormValidatorCSRF(&$form, $message = 'form.csrfInvalid') {
		parent::FormValidator($form, 'dummy', FORM_VALIDATOR_REQUIRED_VALUE, $message);
	}


	//
	// Public methods
	//
	/**
	 * Check if the CSRF token is correct.
	 * overrides FormValidator::isValid()
	 * @return boolean
	 */
	function isValid() {
		$request = PKPApplication::getRequest();
		return $request->checkCSRF();
	}
}

?>
