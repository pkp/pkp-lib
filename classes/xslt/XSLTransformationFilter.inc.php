<?php

/**
 * @file classes/metadata/XSLTransformationFilter.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
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

	//
	// Getters and Setters
	//
	/**
	 * Get the XSL
	 * @return DOMDocument|string
	 */
	function &getXSL() {
		return $this->_xsl;
	}

	/**
	 * Set the XSL
	 * @param $xsl DOMDocument|string
	 */
	function setXSL(&$xsl) {
		assert(is_string($xsl) || is_a($xsl, 'DOMDocument'));
		$this->_xsl =& $xsl;
	}

	//
	// Implement template methods from Filter
	//
	/**
	 * We support either an XML string or a DOMDocument
	 * @see Filter::supports()
	 * @param $input mixed
	 */
	function supports(&$input) {
		return $this->_isValidXML($input);
	}

	/**
	 * We support either an XML string or a DOMDocument
	 * @see Filter::isValid()
	 * @param $output mixed
	 */
	function isValid(&$output) {
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
		$xslTransformer = new XSLTransformer();
		if (is_a($xml, 'DOMDocument')) {
			$result =& $xslTransformer->transformDoms($xml, $this->_xsl);
		} else {
			$result =& $xslTransformer->transformStrings($xml, $this->_xsl);
		}
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
		if (is_a($xml, 'DOMDocument') && is_a($this->_xsl, 'DOMDocument')) return true;
		if (is_string($xml) && is_string($this->_xsl)) return true;
		return false;
	}
}
?>