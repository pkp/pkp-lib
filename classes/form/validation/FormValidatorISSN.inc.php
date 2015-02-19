<?php

/**
 * @file classes/form/validation/FormValidatorISSN.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorISSN
 * @ingroup form_validation
 *
 * @brief Form validation check for ISSNs.
 */

import('lib.pkp.classes.form.validation.FormValidator');

class FormValidatorISSN extends FormValidator {
	/**
	 * Constructor.
	 * @param $form Form the associated form
	 * @param $field string the name of the associated field
	 * @param $type string the type of check, either "required" or "optional"
	 * @param $message string the error message for validation failures (i18n key)
	 */
	function FormValidatorISSN($form, $field, $type, $message) {
		import('lib.pkp.classes.validation.ValidatorISSN');
		$validator = new ValidatorISSN();
		parent::FormValidator($form, $field, $type, $message, $validator);
	}
}

?>
