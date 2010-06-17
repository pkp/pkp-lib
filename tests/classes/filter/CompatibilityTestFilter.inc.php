<?php

/**
 * @file tests/classes/filter/CompatibilityTestFilter.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CompatibilityTestFilter
 * @ingroup tests_classes_filter
 *
 * @brief Test class to be used to check FilterDAO's getCompatibleFilters method.
 */

class CompatibilityTestFilter extends Filter {
	/**
	 * Constructor
	 */
	function CompatibilityTestFilter($displayName = null, $inputType = null, $outputType = null) {
		$this->setDisplayName($displayName);
		// Bypass the superclass' constructor
		$this->_typeDescriptionFactory = new TypeDescriptionFactory();
		if (!is_null($inputType)) $this->setData('configuredInputType', $inputType);
		if (!is_null($outputType)) $this->setData('configuredOutputType', $outputType);
	}


	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::getSettingNames()
	 */
	function getSettingNames() {
		return array('configuredInputType', 'configuredOutputType');
	}

	/**
	 * @see Filter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.tests.classes.filter.CompatibilityTestFilter';
	}


	//
	// Overridden methods from DataObject
	//
	/**
	 * @see DataObject::setData()
	 */
	function setData($key, $value, $locale = null) {
		// Intercept calls to setData() so that we can
		// configure the input/output type even when
		// instantiated from the database.
		switch ($key) {
			case 'configuredInputType':
				$this->setInputType($value);
				break;

			case 'configuredOutputType':
				$this->setOutputType($value);
				break;
		}
		parent::setData($key, $value, $locale);
	}
}
?>