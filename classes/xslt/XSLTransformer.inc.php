<?php

/**
 * @file classes/xslt/XSLTransformer.inc.php
 *
 * Copyright (c) 2000-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class XSLTransformer
 * @ingroup xslt
 *
 * @brief Wrapper class for running XSL transformations using PHP 4.x or 5.x
 */

// $Id$

// The default character encoding
define('XSLT_PROCESSOR_ENCODING', Config::getVar('i18n', 'client_charset'));

class XSLTransformer {

	/** @var $processor string determining the XSLT processor to use for this object */
	var $processor;

	/** @var $externalCommand string containing external XSLT shell command */
	var $externalCommand;

	/** @var $parameters array of parameters to pass to XSL (built-in libraries only) */
	var $parameters;

	/** @var $registerPHPFunctions array of PHP functions to allow in XSL (PHP5 built-in only) */
	var $registerPHPFunctions;

	/** @var $errors array List of error strings */
	var $errors;

	/**
	 * Constructor.
	 * Initialize transformer and set parser options.
	 * @return boolean returns false if no XSLT processor could be created
	 */
	function XSLTransformer() {
		$this->externalCommand = Config::getVar('general', 'xslt_command');

		// Determine the appropriate XSLT processor for the system
		if ($this->externalCommand) {
			// check the external command to check for %xsl and %xml parameter substitution
			if ( strpos($this->externalCommand, '%xsl') === false ) return false;
			if ( strpos($this->externalCommand, '%xml') === false ) return false;
			$this->processor = 'External';

		} elseif ( checkPhpVersion('5.0.0') && extension_loaded('xsl') && extension_loaded('dom') ) {
			// PHP5.x with XSL/DOM modules present
			$this->processor = 'PHP5';

		} elseif ( checkPhpVersion('4.1.0') && extension_loaded('xslt') ) {
			// PHP4.x with XSLT module present
			$this->processor = 'PHP4';

		} else {
			// no XSLT support
			return false;
		}

		$this->errors = array();
	}

	/**
	 * Apply an XSLT transform to a given XML and XSL source files
	 * @param $xmlFile absolute pathname to the XML source file
	 * @param $xslFile absolute pathname to the XSL stylesheet
	 * @return string containing the transformed XML output, or false on error
	 */
	function transformFiles($xmlFile, $xslFile) {
		// if either XML or XSL file don't exist, then fail without trying to process XSLT
		if (!FileManager::fileExists($xmlFile) || !FileManager::fileExists($xslFile)) return false;

		switch ($this->processor) {
			case 'External':
				return $this->_transformExternal($xmlFile, $xslFile);
			case 'PHP4':
				return $this->_transformFilePHP4($xmlFile, $xslFile);
			case 'PHP5':
				return $this->_transformFilePHP5($xmlFile, $xslFile);
		}
		// No XSLT processor available
		return false;
	}

	/**
	 * Apply an XSLT transform to a given XML and XSL strings
	 * @param $xml string containing source XML
	 * @param $xsl string containing source XSL
	 * @return string containing the transformed XML output, or false on error
	 */
	function transformStrings($xml, $xsl) {
		switch ($this->processor) {
			// TODO: External requires saving strings to temporary files
			case 'PHP4':
				return $this->_transformStringPHP4($xml, $xsl);
			case 'PHP5':
				return $this->_transformStringPHP5($xml, $xsl);
		}
		// No XSLT processor available
		return false;
	}

	function _transformExternal($xmlFile, $xslFile) {
		// check the external command to check for %xsl and %xml parameter substitution
		if ( strpos($this->externalCommand, '%xsl') === false ) return false;
		if ( strpos($this->externalCommand, '%xml') === false ) return false;

		// perform %xsl and %xml replacements for fully-qualified shell command
		$xsltCommand = str_replace(array('%xsl', '%xml'), array($xslFile, $xmlFile), $this->externalCommand);

		// check for safe mode and escape the shell command
		if( !ini_get('safe_mode') ) $xsltCommand = escapeshellcmd($xsltCommand);

		// run the shell command and get the results
		exec($xsltCommand . ' 2>&1', $contents, $status);

		// if there is an error state, copy result to error property
		if ($status != false) {
			if ($contents) {
				$this->addError(implode("\n", $contents));
			}
			// completed with errors
			return false;
		}

		return implode("\n", $contents);
	}

	function _transformFilePHP4($xmlFile, $xslFile) {
		$processor = xslt_create();
		xslt_set_encoding($processor, XSLT_PROCESSOR_ENCODING);

		$contents = xslt_process($processor, $xmlFile, $xslFile, null, null, $this->parameters);

		if (!$contents) {
			$this->addError("Cannot process XSLT document [%d]: %s", xslt_errno($processor), xslt_error($processor));
			return false;
		}
		return $contents;
	}

	function _transformStringPHP4($xml, $xsl) {
		$arguments = array('/_xml' => $xml, '/_xsl' => $xsl);

		$processor = xslt_create();
		xslt_set_encoding($processor, XSLT_PROCESSOR_ENCODING);

		$contents = xslt_process($processor, 'arg:/_xml', 'arg:/_xsl', null, $arguments, $this->parameters);

		if (!$contents) {
			$this->addError("Cannot process XSLT document [%d]: %s", xslt_errno($processor), xslt_error($processor));
			return false;
		}
		return $contents;
	}

	function _transformFilePHP5($xmlFile, $xslFile) {
		$processor = new XSLTProcessor();

		// NB: this can open potential security issues; see FAQ/README
		if ($this->registerPHPFunctions) {
			$processor->registerPHPFunctions($this->registerPHPFunctions);
		}

		if (!empty($this->parameters) && is_array($this->parameters)) {
			foreach ($this->parameters as $param => $value) {
				$processor->setParameter(null, $param, $value);
			}
		}

		// load the XML file as a domdocument
		$xmlDOM = new DOMDocument('1.0', XSLT_PROCESSOR_ENCODING);

		// These are required for external entity resolution (eg. &nbsp;), but can slow processing
		// substantially (20-100x), often up to 60s.  This can be solved by use of local catalogs, ie.
		// putenv("XML_CATALOG_FILES=/path/to/catalog.ent");
		//
		// see:  http://www.whump.com/moreLikeThis/link/03815
		$xmlDOM->recover = true;
		$xmlDOM->substituteEntities = true;
		$xmlDOM->resolveExternals = true;
		$xmlDOM->load($xmlFile);

		// create the processor and import the stylesheet
		$xslDOM = new DOMDocument('1.0', XSLT_PROCESSOR_ENCODING);
		$xslDOM->load($xslFile);
		$processor->importStylesheet($xslDOM);
		$contents = $processor->transformToXML($xmlDOM);

		return $contents;
	}

	function _transformStringPHP5($xml, $xsl) {
		$processor = new XSLTProcessor();

		// NB: this can open potential security issues; see FAQ/README
		if ($this->registerPHPFunctions) {
			$processor->registerPHPFunctions($this->registerPHPFunctions);
		}

		foreach ($this->parameters as $param => $value) {
			$processor->setParameter(null, $param, $value);
		}

		// load the XML file as a domdocument
		$xmlDOM = new DOMDocument('1.0', XSLT_PROCESSOR_ENCODING);
		$xmlDOM->recover = true;
		$xmlDOM->substituteEntities = true;
		$xmlDOM->resolveExternals = true;
		$xmlDOM->loadXML($xml);

		// create the processor and import the stylesheet
		$xslDOM = new DOMDocument('1.0', XSLT_PROCESSOR_ENCODING);
		$xslDOM->loadXML($xsl);
		$processor->importStylesheet($xslDOM);
		$contents = $processor->transformToXML($xmlDOM);

		return $contents;
	}

	/**
	 * Add an error to the current error list
	 * @param $error string
	 */
	function addError($error) {
		array_push($this->errors, $error);
	}

}

?>