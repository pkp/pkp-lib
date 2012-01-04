<?php

/**
 * @file classes/form/validation/FormValidatorLocale.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorLocale
 * @ingroup form_validation
 *
 * @brief Class to represent a form validation check for localized fields.
 */

class FormValidatorLocale extends FormValidator {
	//
	// Getters and Setters
	//
	/**
	 * Get the error message associated with a failed validation check.
	 * @see FormValidator::getMessage()
	 * @return string
	 */
	function getMessage() {
		$primaryLocale = AppLocale::getPrimaryLocale();
		$allLocales = AppLocale::getAllLocales();
		return parent::getMessage() . ' (' . $allLocales[$primaryLocale] . ')';
	}

	//
	// Protected helper methods
	//
	/**
	 * @see FormValidator::getFieldValue()
	 * @return mixed
	 */
	function getFieldValue() {
		$form =& $this->getForm();
		$data = $form->getData($this->getField());

		$primaryLocale = AppLocale::getPrimaryLocale();
		$fieldValue = '';
		if (is_array($data) && isset($data[$primaryLocale])) {
			$fieldValue = $data[$primaryLocale];
			if (is_scalar($fieldValue)) $fieldValue = trim((string)$fieldValue);
		}
		return $fieldValue;
	}
}

?>
