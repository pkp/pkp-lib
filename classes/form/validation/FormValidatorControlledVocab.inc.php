<?php

/**
 * @file FormValidatorControlledVocab.inc.php
 *
 * Copyright (c) 2000-2008 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorControlledVocab
 * @ingroup form_validation
 *
 * @brief Form validation check that checks if value is within a certain set.
 */

//$Id$

import('form.validation.FormValidator');

class FormValidatorControlledVocab extends FormValidator {
	/** @var $acceptedValues array */
	var $acceptedValues;

	/**
	 * Constructor.
	 * @see FormValidator::FormValidator()
	 * @param $symbolic string
	 * @param $assocType int
	 * @param $assocId int
	 */
	function FormValidatorControlledVocab(&$form, $field, $type, $message, $symbolic, $assocType, $assocId) {
		parent::FormValidator($form, $field, $type, $message);
		$controlledVocabDao =& DAORegistry::getDAO('ControlledVocabDAO');
		$controlledVocab =& $controlledVocabDao->getBySymbolic($symbolic, $assocType, $assocId);
		if ($controlledVocab) $this->acceptedValues = array_keys($controlledVocab->enumerate());
		else $this->acceptedValues = null;
	}

	/**
	 * Check if field value is valid.
	 * Value is valid if it is empty and optional or is in the set of accepted values.
	 * @return boolean
	 */
	function isValid() {
		return $this->isEmptyAndOptional() || in_array($this->form->getData($this->field), $this->acceptedValues);
	}
}

?>
