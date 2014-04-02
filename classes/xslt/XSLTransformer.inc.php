<?php

/**
 * @file classes/xslt/XSLTransformer.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class XSLTransformer
 * @ingroup xslt
 *
 * @brief Wrapper class for running XSL transformations using PHP 4.x or 5.x
 */


// The default character encoding
define('XSLT_PROCESSOR_ENCODING', Config::getVar('i18n', 'client_charset'));

define('XSL_TRANSFORMER_DOCTYPE_STRING', 0x01);
define('XSL_TRANSFORMER_DOCTYPE_FILE', 0x02);
define('XSL_TRANSFORMER_DOCTYPE_DOM', 0x03);

class XSLTransformer {

	/** @var $processor string determining the XSLT processor to use for this object */
	var $processor;

	/** @var $externalCommand string containing external XSLT shell command */
	var $externalCommand;

	/** @var $externalParameterSnippet string containing external XSLT shell arguments for parameters */
	var $externalParameterSnippet;

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
		$this->externalCommand = Config::getVar('cli', 'xslt_command');
		$this->externalParameterSnippet = Config::getVar('cli', 'xslt_parameter_option');

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

	//
	// Getters and Setters
	//
	/**
	 * Get the processor type
	 * @return string
	 */
	function getProcessor() {
		return $this->processor;
	}

	/**
	 * Set the parameter list for internal processors.
	 * @param array $parameters
	 */
	function setParameters($parameters) {
		$this->parameters =& $parameters;
	}

	/**
	 * Set the registerPHPFunctions setting on or off.
	 * @param boolean $flag
	 */
	function setRegisterPHPFunctions($flag) {
		$this->registerPHPFunctions = $flag;
	}
	//
	// Public methods
	//
	/**
	 * Apply an XSLT transform to a given XML and XSL source files
	 * @param $xmlFile absolute pathname to the XML source file
	 * @param $xslFile absolute pathname to the XSL stylesheet
	 * @return string containing the transformed XML output, or false on error
	 */
	function transformFiles($xmlFile, $xslFile) {
		return $this->transform($xmlFile, XSL_TRANSFORMER_DOCTYPE_FILE, $xslFile, XSL_TRANSFORMER_DOCTYPE_FILE, XSL_TRANSFORMER_DOCTYPE_STRING);
	}

	/**
	 * Apply an XSLT transform to a given XML and XSL strings
	 * @param $xml string containing source XML
	 * @param $xsl string containing source XSL
	 * @return string containing the transformed XML output, or false on error
	 */
	function transformStrings($xml, $xsl) {
		return $this->transform($xml, XSL_TRANSFORMER_DOCTYPE_STRING, $xsl, XSL_TRANSFORMER_DOCTYPE_STRING, XSL_TRANSFORMER_DOCTYPE_STRING);
	}

	/**
	 * Apply an XSLT transform to a given XML and XSL. Both parameters
	 * can be either strings, files or DOM objects.
	 * @param $xml mixed
	 * @param $xmlType integer
	 * @param $xsl mixed
	 * @param $xslType integer
	 * @param $resultType integer
	 * @return mixed return type depends on the $returnType parameter and can be
	 *  DOMDocument or string. The method returns a boolean value of false if the
	 *  transformation fails for some reason.
	 */
	function &transform(&$xml, $xmlType, &$xsl, $xslType, $resultType) {
		$falseVar = false;
		// If either XML or XSL file don't exist, then fail without trying to process XSLT
		$fileManager = new FileManager();
		if ($xmlType == XSL_TRANSFORMER_DOCTYPE_FILE) {
			if (!$fileManager->fileExists($xml)) return $falseVar;
		}
		if ($xslType == XSL_TRANSFORMER_DOCTYPE_FILE) {
			if (!$fileManager->fileExists($xsl)) return $falseVar;
		}

		// The result type can only be string or DOM
		assert($resultType != XSL_TRANSFORMER_DOCTYPE_FILE);

		switch ($this->processor) {
			case 'External':
				return $this->_transformExternal($xml, $xmlType, $xsl, $xslType, $resultType);

			case 'PHP4':
				return $this->_transformPHP4($xml, $xmlType, $xsl, $xslType, $resultType);

			case 'PHP5':
				return $this->_transformPHP5($xml, $xmlType, $xsl, $xslType, $resultType);

			default:
				// No XSLT processor available
				return $falseVar;
		}
	}

	//
	// Private helper methods
	//
	/**
	 * Use external programs to do the XSL transformation
	 * @param $xml mixed
	 * @param $xmlType integer
	 * @param $xsl mixed
	 * @param $xslType integer
	 * @param $resultType integer
	 * @return mixed return type depends on the $returnType parameter and can be
	 *  DOMDocument or string. Returns boolean "false" on error.
	 */
	function &_transformExternal(&$xml, $xmlType, &$xsl, $xslType, $resultType) {
		$falseVar = false;

		// External transformation can only be done on files
		if ($xmlType != XSL_TRANSFORMER_DOCTYPE_FILE || $xslType != XSL_TRANSFORMER_DOCTYPE_FILE) return $falseVar;

		// check the external command to check for %xsl and %xml parameter substitution
		if ( strpos($this->externalCommand, '%xsl') === false ) return $falseVar;
		if ( strpos($this->externalCommand, '%xml') === false ) return $falseVar;

		// Assemble the parameters to be supplied to the stylesheet
		$parameterString = '';
		foreach ($this->parameters as $name => $value) {
			$parameterString .= str_replace(array('%n', '%v'), array($name, $value), $this->externalParameterSnippet);
		}

		// perform %xsl and %xml replacements for fully-qualified shell command
		$xsltCommand = str_replace(array('%xsl', '%xml', '%params'), array($xsl, $xml, $parameterString), $this->externalCommand);

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
			return $falseVar;
		}

		$resultXML = implode("\n", $contents);

		switch ($resultType) {
			case XSL_TRANSFORMER_DOCTYPE_STRING:
				// Directly return the XML string
				return $resultXML;

			case XSL_TRANSFORMER_DOCTYPE_DOM:
				// Instantiate and configure the result DOM
				$resultDOM = new DOMDocument('1.0', XSLT_PROCESSOR_ENCODING);
				$resultDOM->recover = true;
				$resultDOM->substituteEntities = true;
				$resultDOM->resolveExternals = true;

				// Load the XML and return the DOM
				$resultDOM->loadXML($resultXML);
				return $resultDOM;

			default:
				assert(false);
		}
	}

	/**
	 * Use PHP4's xslt extension to do the XSL transformation
	 * @param $xml mixed
	 * @param $xmlType integer
	 * @param $xsl mixed
	 * @param $xslType integer
	 * @param $resultType integer
	 * @return string return type for PHP4 is always string or "false" on error.
	 */
	function &_transformPHP4(&$xml, $xmlType, &$xsl, $xslType, $resultType) {
		$falseVar = false;

		// PHP4 doesn't support DOM
		if ($xmlType == XSL_TRANSFORMER_DOCTYPE_DOM || $xslType == XSL_TRANSFORMER_DOCTYPE_DOM
				|| $resultType == XSL_TRANSFORMER_DOCTYPE_DOM) return $falseVar;

		// Create the processor
		$processor = xslt_create();
		xslt_set_encoding($processor, XSLT_PROCESSOR_ENCODING);

		// Create arguments for string types (if any)
		$arguments = array();
		if ($xmlType == XSL_TRANSFORMER_DOCTYPE_STRING) {
			$arguments['/_xml'] = $xml;
			$xml = 'arg:/_xml';
		}
		if ($xslType == XSL_TRANSFORMER_DOCTYPE_STRING) {
			$arguments['/_xsl'] = $xsl;
			$xsl = 'arg:/_xsl';
		}
		if (empty($arguments)) $arguments = null;

		// Do the transformation
		$resultXML = xslt_process($processor, $xml, $xsl, null, $arguments, $this->parameters);

		// Error handling
		if (!$resultXML) {
			$this->addError("Cannot process XSLT document [%d]: %s", xslt_errno($processor), xslt_error($processor));
			return $falseVar;
		}

		// DOM is not supported in PHP4 so we can always directly return the string result
		return $resultXML;
	}

	/**
	 * Use PHP5's DOMDocument and XSLTProcessor to do the transformation
	 * @param $xml mixed
	 * @param $xmlType integer
	 * @param $xsl mixed
	 * @param $xslType integer
	 * @param $resultType integer
	 * @return mixed return type depends on the $returnType parameter and can be
	 *  DOMDocument or string. Returns boolean "false" on error.
	 */
	function &_transformPHP5(&$xml, $xmlType, &$xsl, $xslType, $resultType) {
		// Prepare the XML DOM
		if ($xmlType == XSL_TRANSFORMER_DOCTYPE_DOM) {
			// We already have a DOM document, no need to create one
			assert(is_a($xml, 'DOMDocument'));
			$xmlDOM =& $xml;
		} else {
			// Instantiate and configure the XML DOM document
			$xmlDOM = new DOMDocument('1.0', XSLT_PROCESSOR_ENCODING);

			// These are required for external entity resolution (eg. &nbsp;), but can slow processing
			// substantially (20-100x), often up to 60s.  This can be solved by use of local catalogs, ie.
			// putenv("XML_CATALOG_FILES=/path/to/catalog.ent");
			//
			// see:  http://www.whump.com/moreLikeThis/link/03815
			$xmlDOM->recover = true;
			$xmlDOM->substituteEntities = true;
			$xmlDOM->resolveExternals = true;

			// Load the XML based on its type
			switch ($xmlType) {
				case XSL_TRANSFORMER_DOCTYPE_FILE:
					$xmlDOM->load($xml);
					break;

				case XSL_TRANSFORMER_DOCTYPE_STRING:
					$xmlDOM->loadXML($xml);
					break;

				default:
					assert(false);
			}
		}

		// Prepare the XSL DOM
		if ($xslType == XSL_TRANSFORMER_DOCTYPE_DOM) {
			// We already have a DOM document, no need to create one
			assert(is_a($xsl, 'DOMDocument'));
			$xslDOM =& $xsl;
		} else {
			// Instantiate the XSL DOM document
			$xslDOM = new DOMDocument('1.0', XSLT_PROCESSOR_ENCODING);

			// Load the XSL based on its type
			switch ($xslType) {
				case XSL_TRANSFORMER_DOCTYPE_FILE:
					$xslDOM->load($xsl);
					break;

				case XSL_TRANSFORMER_DOCTYPE_STRING:
					$xslDOM->loadXML($xsl);
					break;

				default:
					assert(false);
			}
		}

		// Create and configure the XSL processor
		$processor = new XSLTProcessor();

		// Register PHP functions if requested.
		// NB: This can open potential security issues; see FAQ/README
		if ($this->registerPHPFunctions) {
			$processor->registerPHPFunctions();
		}

		// Set XSL parameters (if any)
		if (is_array($this->parameters)) {
			foreach ($this->parameters as $param => $value) {
				$processor->setParameter(null, $param, $value);
			}
		}

		//  Import the style sheet
		$processor->importStylesheet($xslDOM);

		// Process depending on the requested result type
		switch($resultType) {
			case XSL_TRANSFORMER_DOCTYPE_STRING:
				$resultXML = $processor->transformToXML($xmlDOM);
				return $resultXML;

			case XSL_TRANSFORMER_DOCTYPE_DOM:
				$resultDOM = $processor->transformToDoc($xmlDOM);
				return $resultDOM;

			default:
				assert(false);
		}
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
