<?php

/**
 * @file classes/form/validation/FormValidatorAlphaNum.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorAlphaNum
 * @ingroup form_validation
 * @see FormValidator
 *
 * @brief Form validation check for alphanumeric (plus interior dash/underscore) characters only.
 */

import('lib.pkp.classes.form.validation.FormValidator');

class FormValidatorAlphaNum extends FormValidator {
	/**
	 * Constructor.
	 * @param $form Form the associated form
	 * @param $field string the name of the associated field
	 * @param $type string the type of check, either "required" or "optional"
	 * @param $message string the error message for validation failures (i18n key)
	 */
	function FormValidatorAlphaNum(&$form, $field, $type, $message) {
		import('lib.pkp.classes.validation.ValidatorRegExp');
		$validator = new ValidatorRegExp('/^[A-Z0-9]+([\-_][A-Z0-9]+)*$/i');
		parent::FormValidator($form, $field, $type, $message, $validator);
	}
}

?>
