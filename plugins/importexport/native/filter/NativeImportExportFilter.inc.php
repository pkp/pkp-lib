<?php

/**
 * @file plugins/importexport/native/filter/NativeXmlSubmissionFilter.inc.php
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NativeXmlSubmissionFilter
 * @ingroup plugins_importexport_native
 *
 * @brief Base class that converts a Native XML document to a set of submissions
 */

import('lib.pkp.classes.filter.PersistableFilter');

class NativeImportExportFilter extends PersistableFilter {
	/** @var NativeImportExportDeployment */
	var $_deployment;

	/**
	 * Constructor
	 * $filterGroup FilterGroup
	 */
	function NativeXmlSubmissionFilter($filterGroup) {
		parent::PersistableFilter($filterGroup);
	}

	/**
	 * Set the import/export deployment
	 * @param $deployment NativeImportExportDeployment
	 */
	function setDeployment($deployment) {
		$this->_deployment = $deployment;
	}

	/**
	 * Get the import/export deployment
	 * @return NativeImportExportDeployment
	 */
	function getDeployment() {
		return $this->_deployment;
	}
}

?>
