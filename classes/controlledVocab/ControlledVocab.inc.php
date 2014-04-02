<?php

/**
 * @defgroup controlled_vocab
 */

/**
 * @file classes/controlledVocab/ControlledVocab.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ControlledVocab
 * @ingroup controlled_vocab
 * @see ControlledVocabDAO
 *
 * @brief Basic class describing an controlled vocab.
 */

class ControlledVocab extends DataObject {
	/**
	 * Constructor
	 */
	function ControlledVocab() {
		parent::DataObject();
	}

	//
	// Get/set methods
	//

	/**
	 * get assoc id
	 * @return int
	 */
	function getAssocId() {
		return $this->getData('assocId');
	}

	/**
	 * set assoc id
	 * @param $assocId int
	 */
	function setAssocId($assocId) {
		return $this->setData('assocId', $assocId);
	}

	/**
	 * Get associated type.
	 * @return int
	 */
	function getAssocType() {
		return $this->getData('assocType');
	}

	/**
	 * Set associated type.
	 * @param $assocType int
	 */
	function setAssocType($assocType) {
		return $this->setData('assocType', $assocType);
	}

	/**
	 * Get symbolic name.
	 * @return string
	 */
	function getSymbolic() {
		return $this->getData('symbolic');
	}

	/**
	 * Set symbolic name.
	 * @param $symbolic string
	 */
	function setSymbolic($symbolic) {
		return $this->setData('symbolic', $symbolic);
	}

	/**
	 * Get a list of controlled vocabulary options.
	 * @param $settingName string optional
	 * @return array $controlledVocabEntryId => name
	 */
	function enumerate($settingName = 'name') {
		$controlledVocabDao =& DAORegistry::getDAO('ControlledVocabDAO');
		return $controlledVocabDao->enumerate($this->getId(), $settingName);
	}
}

?>
