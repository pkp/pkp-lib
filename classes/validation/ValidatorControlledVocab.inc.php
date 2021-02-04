<?php

/**
 * @file classes/validation/ValidatorControlledVocab.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ValidatorControlledVocab
 * @ingroup validation
 *
 * @brief Validation check that checks if value is within a certain set retrieved
 *  from the database.
 */

import('lib.pkp.classes.validation.Validator');

class ValidatorControlledVocab extends Validator {
	/** @var array */
	var $_acceptedValues;

	/**
	 * Constructor.
	 * @param $symbolic string
	 * @param $assocType int
	 * @param $assocId int
	 */
	function __construct($symbolic, $assocType, $assocId) {
		$controlledVocabDao = DAORegistry::getDAO('ControlledVocabDAO'); /* @var $controlledVocabDao ControlledVocabDAO */
		$controlledVocab = $controlledVocabDao->getBySymbolic($symbolic, $assocType, $assocId);
		if ($controlledVocab) $this->_acceptedValues = array_keys($controlledVocab->enumerate());
		else $this->_acceptedValues = array();
	}


	//
	// Implement abstract methods from Validator
	//
	/**
	 * @see Validator::isValid()
	 * Value is valid if it is empty and optional or is in the set of accepted values.
	 * @param $value mixed
	 * @return boolean
	 */
	function isValid($value) {
		return in_array($value, $this->_acceptedValues);
	}
}
