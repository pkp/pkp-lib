<?php

/**
 * @file classes/filter/GenericFilter.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class GenericFilter
 * @ingroup classes_filter
 *
 * @brief Abstract base class for filters that can transform any type of data.
 */

class GenericFilter extends Filter {
	/** @var the supported transformation */
	var $_genericTransformationType;

	/**
	 * Constructor
	 *
	 * @param $displayName string
	 * @param $transformation array
	 */
	function GenericFilter($displayName = null, $transformation = null) {
		$this->setDisplayName($displayName);
		if (!is_null($transformation)) $this->_genericTransformationType =& $transformation;
		parent::Filter();
	}

	//
	// Overridden methods from Filter
	//
	/**
	 * @see Filter::setTransformationType()
	 */
	function setTransformationType(&$inputType, &$outputType) {
		// Intercept setTransformationType() to make sure that
		// any transformation set here for the first time will
		// automatically be the supported transformation.
		if (!is_array($this->_genericTransformationType)) {
			if (is_string($inputType)) {
				$inputTypeString = $inputType;
			} else {
				assert(is_a($inputType, 'TypeDescription'));
				$inputTypeString = $inputType->getTypeDescription();
			}
			if (is_string($outputType)) {
				$outputTypeString = $outputType;
			} else {
				assert(is_a($outputType, 'TypeDescription'));
				$outputTypeString = $outputType->getTypeDescription();
			}
			$this->_genericTransformationType = array($inputTypeString, $outputTypeString);
		}

		parent::setTransformationType($inputType, $outputType);
	}


	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::getSupportedTransformation()
	 */
	function getSupportedTransformation() {
		return $this->_genericTransformationType;
	}
}
?>