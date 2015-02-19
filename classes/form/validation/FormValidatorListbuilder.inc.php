<?php

/**
 * @file classes/form/validation/FormValidatorListbuilder.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorListbuilder
 * @ingroup form_validation
 *
 * @brief Form validation check that checks if the JSON value submitted unpacks into something that
 * contains at least one valid user id.
 */

import('lib.pkp.classes.form.validation.FormValidator');

class FormValidatorListbuilder extends FormValidator {

	/* outcome of validation after callbacks */
	var $_valid = false;


	/**
	 * Constructor.
	 * @param $form Form the associated form
	 * @param $field string the name of the associated field
	 * @param $message string the error message for validation failures (i18n key)
	 */
	function FormValidatorListbuilder(&$form, $field, $message) {
		parent::FormValidator($form, $field, FORM_VALIDATOR_OPTIONAL_VALUE, $message);
	}

	//
	// Public methods
	//
	/**
	 * Check the number of lisbuilder rows. If it's equal to 0, return false.
	 *
	 * @see FormValidator::isValid()
	 * @return boolean
	 */
	function isValid() {
		$value = $this->getFieldValue();
		import('lib.pkp.classes.controllers.listbuilder.ListbuilderHandler');
		ListbuilderHandler::unpack($request, $value);
		if ($this->_valid) {
			return true;
		} else {
			return false;
		}
	}

	function deleteEntry(&$request, $rowId, $numberOfRows) {
		if ($numberOfRows > 0) {
			$this->_valid = true;
		} else {
			$this->_valid = false;
		}

		return true;
	}

	function insertEntry(&$request, $rowId) {
		return true;
	}
}

?>
