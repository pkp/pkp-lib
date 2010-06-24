<?php

/**
 * @file classes/filter/GenericFilter.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class GenericFilter
 * @ingroup classes_filter
 *
 * @brief Abstract base class for filters that can transform any type of data.
 */

class GenericFilter extends Filter {
	/** @var the supported transformation */
	var $_transformationType;

	/**
	 * Constructor
	 *
	 * @param $displayName string
	 * @param $transformation array
	 */
	function GenericFilter($displayName = null, $transformation = null) {
		$this->setDisplayName($displayName);
		if (!is_null($transformation)) $this->setTransformationType($transformation[0], $transformation[1]);
	}

	//
	// Overridden methods from Filter
	//
	/**
	 * @see Filter::setTransformationType()
	 */
	function setTransformationType($inputType, $outputType) {
		// Intercept setTransformationType() to make sure that
		// any transformation set here will automatically
		// be the supported transformation.
		$this->_supportedTransformation = array($inputType, $outputType);
		parent::setTransformationType($inputType, $outputType);
	}


	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::getSupportedTransformation()
	 */
	function getSupportedTransformation() {
		return $this->_supportedTransformation;
	}
}
?>