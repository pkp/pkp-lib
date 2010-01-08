<?php

/**
 * @file classes/form/validation/FormValidatorRegExp.inc.php
 *
 * Copyright (c) 2000-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorRegExp
 * @ingroup form_validation
 *
 * @brief Form validation check using a regular expression.
 */

// $Id$


import ('form.validation.FormValidator');

class FormValidatorRegExp extends FormValidator {

	/** @var The regular expression to match against the field value */
	var $_regExp;

	/** @var The matches for further (optional) processing by subclasses */
	var $_matches;

	/**
	 * Constructor.
	 * @see FormValidator::FormValidator()
	 * @param $regExp string the regular expression (PCRE form)
	 */
	function FormValidatorRegExp(&$form, $field, $type, $message, $regExp) {
		parent::FormValidator($form, $field, $type, $message);
		$this->_regExp = $regExp;
	}

	/**
	 * Check if field value is valid.
	 * Value is valid if it is empty and optional or matches regular expression.
	 * @return boolean
	 */
	function isValid() {
		return $this->isEmptyAndOptional() || String::regexp_match_get($this->_regExp, $this->form->getData($this->field), $this->_matches);
	}

	//
	// Protected methods for use by sub-classes
	//
	/**
	 * Returns the reg-ex matches (if any) after isValid() was called.
	 */
	function getMatches() {
		return $this->_matches;
	}
}

?>
