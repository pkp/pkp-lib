<?php

/**
 * @file classes/form/validation/FormValidatorListBuilder.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorListBuilder
 * @ingroup form_validation
 *
 * @brief Form validation check that checks if the JSON value submitted unpacks into something that
 * contains at least one valid user id.
 */

import('lib.pkp.classes.form.validation.FormValidator');

class FormValidatorListBuilder extends FormValidator {

	/* outcome of validation after callbacks */
	var $_valid = false;

	/**
	 * Constructor.
	 * @param $form Form the associated form
	 * @param $field string the name of the associated field
	 * @param $message string the error message for validation failures (i18n key)
	 */
	function FormValidatorListBuilder(&$form, $field, $message) {
		parent::FormValidator($form, $field, FORM_VALIDATOR_OPTIONAL_VALUE, $message);
	}

	//
	// Public methods
	//
	/**
	 * Value is valid if at least one of the defined unpack callback functions return true (ie, there is a user id present).
	 * @see FormValidator::isValid()
	 * @return boolean
	 */
	function isValid() {
		$value = $this->getFieldValue();
		import('lib.pkp.classes.controllers.listbuilder.ListbuilderHandler');
		ListBuilderHandler::unpack($request, $value);
		if ($this->_valid) {
			return true;
		} else {
			return false;
		}
	}

	function deleteEntry(&$request, $rowId) {
		return $this->insertEntry($request, $rowId);
	}

	function insertEntry(&$request, $rowId) {
			if (is_array($rowId)) {
			foreach ($rowId as $id) {
				if ((int) $rowId > 0) {
					$this->_valid = true;
				}
			}
		} else if ((int) $rowId > 0) {
			$this->_valid = true;
		}

		return true;
	}
}

?>
