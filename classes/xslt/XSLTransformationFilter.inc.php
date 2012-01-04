<?php

/**
 * @file classes/metadata/XSLTransformationFilter.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class XSLTransformationFilter
 * @ingroup xslt
 *
 * @brief Class that transforms XML via XSL.
 */

// $Id$

import('filter.Filter');
import('xslt.XSLTransformer');

class XSLTransformationFilter extends Filter {
	/** @var DOMDocument|string either an XSL string or an XSL DOMDocument */
	var $_xsl;

	/** @var integer */
	var $_xslType;

	/** @var integer */
	var $_resultType;

	//
	// Getters and Setters
	//
	/**
	 * Get the XSL
	 * @return DOMDocument|string a document, xsl string or file name
	 */
	function &getXSL() {
		return $this->_xsl;
	}

	/**
	 * Get the XSL Type
	 * @return integer
	 */
	function getXSLType() {
		return $this->_xslType;
	}

	/**
	 * Set the XSL
	 * @param $xsl DOMDocument|string
	 */
	function setXSL(&$xsl) {
		// Determine the xsl type
		if (is_string($xsl)) {
			$this->_xslType = XSL_TRANSFORMER_DOCTYPE_STRING;
		} elseif (is_a($xsl, 'DOMDocument')) {
			$this->_xslType = XSL_TRANSFORMER_DOCTYPE_DOM;
		} else assert(false);

		$this->_xsl =& $xsl;
	}

	/**
	 * Set the XSL as a file name
	 * @param unknown_type $xslFile
	 */
	function setXSLFilename($xslFile) {
		$this->_xslType = XSL_TRANSFORMER_DOCTYPE_FILE;
		$this->_xsl = $xslFile;
	}

	/**
	 * Get the result type
	 * @return integer
	 */
	function getResultType() {
		return $this->_resultType;
	}

	/**
	 * Set the result type
	 * @param $resultType integer
	 */
	function setResultType($resultType) {
		$this->_resultType = $resultType;
	}


	//
	// Implement template methods from Filter
	//
	/**
	 * We support either an XML string or a DOMDocument
	 * as input and / or output.
	 * @see Filter::supports()
	 * @param $input mixed
	 * @param $output mixed
	 */
	function supports(&$input, &$output) {
		// Check input type
		if (!$this->_isValidXML($input)) return false;

		// Check output type
		if (is_null($output)) return true;
		return $this->_isValidXML($output);
	}

	/**
	 * Process the given XML with the configured XSL
	 * @see Filter::process()
	 * @param $xml DOMDocument|string
	 * @return DOMDocument|string
	 */
	function &process(&$xml) {
		// Determine the input type
		if (is_string($xml)) {
			$xmlType = XSL_TRANSFORMER_DOCTYPE_STRING;
		} elseif (is_a($xml, 'DOMDocument')) {
			$xmlType = XSL_TRANSFORMER_DOCTYPE_DOM;
		} else assert(false);

		// Determine the result type based on
		// the input type if it has not been
		// set explicitly.
		if (is_null($this->_resultType)) {
			$this->_resultType = $xmlType;
		}

		// Transform the input
		$xslTransformer = new XSLTransformer();
		$result =& $xslTransformer->transform($xml, $xmlType, $this->_xsl, $this->_xslType, $this->_resultType);
		return $result;
	}

	//
	// Private helper methods
	//
	/**
	 * Checks whether this is either a DOMDocument or a
	 * string and whether the input combines with the XSL.
	 * @param $input mixed
	 * @return boolean
	 */
	function _isValidXML(&$xml) {
		return (is_a($xml, 'DOMDocument') || is_string($xml));
	}
}
?>