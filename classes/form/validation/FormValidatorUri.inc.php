<?php

/**
 * @file classes/form/validation/FormValidatorUri.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorUri
 * @ingroup form_validation
 * @see FormValidator
 *
 * @brief Form validation check for URIs.
 */


import('form.validation.FormValidator');

class FormValidatorUri extends FormValidator {
	/**
	 * Constructor.
	 * @param $form Form the associated form
	 * @param $field string the name of the associated field
	 * @param $type string the type of check, either "required" or "optional"
	 * @param $message string the error message for validation failures (i18n key)
	 * @param $allowedSchemes array the allowed URI schemes
	 */
	function FormValidatorUri(&$form, $field, $type, $message, $allowedSchemes = null) {
		import('validation.ValidatorUri');
		$validator = new ValidatorUri($allowedSchemes);
		parent::FormValidator($form, $field, $type, $message, $validator);
	}
}
?>
