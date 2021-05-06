<?php

/**
 * @file plugins/importexport/native/filter/PreprintNativeXmlFilter.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PreprintNativeXmlFilter
 * @ingroup plugins_importexport_native
 *
 * @brief Class that converts a Preprint to a Native XML document.
 */

import('lib.pkp.plugins.importexport.native.filter.SubmissionNativeXmlFilter');

class PreprintNativeXmlFilter extends SubmissionNativeXmlFilter {
	//
	// Implement template methods from PersistableFilter
	//
	/**
	 * @copydoc PersistableFilter::getClassName()
	 */
	function getClassName() {
		return 'plugins.importexport.native.filter.PreprintNativeXmlFilter';
	}

	//
	// Submission conversion functions
	//
	/**
	 * Create and return a submission node.
	 * @param $doc DOMDocument
	 * @param $submission Submission
	 * @return DOMElement
	 */
	function createSubmissionNode($doc, $submission) {
		$deployment = $this->getDeployment();
		$submissionNode = parent::createSubmissionNode($doc, $submission);

		return $submissionNode;
	}

}
