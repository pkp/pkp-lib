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

import('lib.pkp.classes.filter.GenericFilter');

class CompatibilityTestFilter extends GenericFilter {
	/**
	 * Constructor
	 *
	 * @param $displayName string
	 * @param $transformation array
	 */
	function CompatibilityTestFilter($displayName = null, $transformation = null) {
		parent::GenericFilter($displayName, $transformation);
	}

	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.tests.classes.filter.CompatibilityTestFilter';
	}
}
?>