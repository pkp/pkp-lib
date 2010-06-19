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

import('lib.pkp.classes.filter.GenericFilter');
import('lib.pkp.classes.xslt.XSLTransformer');

class XSLTransformationFilter extends GenericFilter {
	/** @var DOMDocument|string either an XSL string or an XSL DOMDocument */
	var $_xsl;

	/** @var integer */
	var $_xslType;

	/** @var integer */
	var $_resultType;

	/**
	 * Constructor
	 *
	 * @param $displayName string
	 * @param $transformation array the supported transformation
	 *
	 * NB: The input side of the transformation must always
	 * be an XML format. See the XMLTypeDescription class for
	 * more details how to enable XML validation.
	 */
	function XSLTransformationFilter($displayName = 'XSL Transformation', $transformation = null) {
		parent::GenericFilter($displayName, $transformation);
	}


	//
	// Overridden methods from GenericFilter
	//
	/**
	 * @see Filter::setTransformationType()
	 * @see GenericFilter::setTransformationType()
	 */
	function setTransformationType($inputType, $outputType) {
		// Intercept setTransformationType() to check that we
		// only get xml input, the output type is arbitrary.
		if (!substr($inputType, 0, 5) == 'xml::') fatalError('XSL filters need XML as input.');
		parent::setTransformationType($inputType, $outputType);
	}


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
	 * @see Filter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.classes.xslt.XSLTransformationFilter';
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
}
?>