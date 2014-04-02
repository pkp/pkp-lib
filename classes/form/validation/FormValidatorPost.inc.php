<?php

/**
 * @file classes/form/validation/FormValidatorPost.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorPost
 * @ingroup form_validation
 *
 * @brief Form validation check to make sure the form is POSTed.
 */

import ('lib.pkp.classes.form.validation.FormValidator');

class FormValidatorPost extends FormValidator {
	/**
	 * Constructor.
	 * @param $form Form
	 * @param $message string the locale key to use (optional)
	 */
	function FormValidatorPost(&$form, $message = 'form.postRequired') {
		parent::FormValidator($form, 'dummy', FORM_VALIDATOR_REQUIRED_VALUE, $message);
	}


	//
	// Public methods
	//
	/**
	 * Check if form was posted.
	 * overrides FormValidator::isValid()
	 * @return boolean
	 */
	function isValid() {
		return Request::isPost();
	}
}

?>
