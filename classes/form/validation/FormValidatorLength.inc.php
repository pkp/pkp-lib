<?php

/**
 * @file classes/form/validation/FormValidatorLength.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorLength
 * @ingroup form_validation
 *
 * @brief Form validation check that checks if a field's length meets certain requirements.
 */

import ('lib.pkp.classes.form.validation.FormValidator');

class FormValidatorLength extends FormValidator {

	/** @var string comparator to use (== | != | < | > | <= | >= ) */
	var $_comparator;

	/** @var int length to compare with */
	var $_length;

	/**
	 * Constructor.
	 * @param $form Form the associated form
	 * @param $field string the name of the associated field
	 * @param $type string the type of check, either "required" or "optional"
	 * @param $message string the error message for validation failures (i18n key)
	 * @param $comparator
	 * @param $length
	 */
	function __construct(&$form, $field, $type, $message, $comparator, $length) {
		parent::__construct($form, $field, $type, $message);
		$this->_comparator = $comparator;
		$this->_length = $length;
	}


	//
	// Setters and Getters
	//
	/**
	 * @see FormValidator::getMessage()
	 * @return string
	 */
	function getMessage() {
		return __($this->_message, array('length' => $this->_length));
	}


	//
	// Public methods
	//
	/**
	 * @see FormValidator::isValid()
	 * Value is valid if it is empty and optional or meets the specified length requirements.
	 * @return boolean
	 */
	function isValid() {
		if ($this->isEmptyAndOptional()) {
			return true;

		} else {
			$length = PKPString::strlen($this->getFieldValue());
			switch ($this->_comparator) {
				case '==':
					return $length == $this->_length;
				case '!=':
					return $length != $this->_length;
				case '<':
					return $length < $this->_length;
				case '>':
					return $length > $this->_length;
				case '<=':
					return $length <= $this->_length;
				case '>=':
					return $length >= $this->_length;
			}
			return false;
		}
	}
}


