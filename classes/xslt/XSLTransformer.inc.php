<?php

/**
 * @file classes/xslt/XSLTransformer.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class XSLTransformer
 * @ingroup xslt
 *
 * @brief Wrapper class for running XSL transformations.
 */

namespace PKP\xslt;

use DOMDocument;

use PKP\config\Config;
use PKP\file\FileManager;

use XSLTProcessor;

class XSLTransformer
{
    public const XSLT_PROCESSOR_ENCODING = 'utf-8';
    public const XSL_TRANSFORMER_DOCTYPE_STRING = 1;
    public const XSL_TRANSFORMER_DOCTYPE_FILE = 2;
    public const XSL_TRANSFORMER_DOCTYPE_DOM = 3;

    /** @var string determining the XSLT processor to use for this object */
    public static $processor;

    /** @var string containing external XSLT shell command */
    public static $externalCommand;

    /** @var string containing external XSLT shell arguments for parameters */
    public static $externalParameterSnippet;

    /** @var array of parameters to pass to XSL (built-in libraries only) */
    public $parameters;

    /** @var array of PHP functions to allow in XSL */
    public $registerPHPFunctions;

    /** @var array List of error strings */
    public $errors;

    /**
     * Constructor.
     * Initialize transformer and set parser options.
     *
     * @return bool returns false if no XSLT processor could be created
     */
    public function __construct()
    {
        // Necessary to fetch configuration.
        self::checkSupport();

        $this->errors = [];
    }

    /**
     * Fetch configuration and check whether XSLT is properly supported.
     *
     * @return bool True iff XSLT support is present.
     */
    public static function checkSupport()
    {
        self::$externalCommand = Config::getVar('cli', 'xslt_command');
        self::$externalParameterSnippet = Config::getVar('cli', 'xslt_parameter_option');

        // Determine the appropriate XSLT processor for the system
        if (self::$externalCommand) {
            // check the external command to check for %xsl and %xml parameter substitution
            if (strpos(self::$externalCommand, '%xsl') === false) {
                return false;
            }
            if (strpos(self::$externalCommand, '%xml') === false) {
                return false;
            }
            self::$processor = 'External';
        } elseif (extension_loaded('xsl') && extension_loaded('dom')) {
            // XSL/DOM modules present
            self::$processor = 'PHP';
        } else {
            // no XSLT support
            return false;
        }
        return true;
    }

    //
    // Getters and Setters
    //
    /**
     * Get the processor type
     *
     * @return string
     */
    public static function getProcessor()
    {
        return self::$processor;
    }

    /**
     * Set the parameter list for internal processors.
     *
     * @param array $parameters
     */
    public function setParameters($parameters)
    {
        $this->parameters = $parameters;
    }

    /**
     * Set the registerPHPFunctions setting on or off.
     *
     * @param bool $flag
     */
    public function setRegisterPHPFunctions($flag)
    {
        $this->registerPHPFunctions = $flag;
    }
    //
    // Public methods
    //
    /**
     * Apply an XSLT transform to a given XML and XSL source files
     *
     * @param string $xmlFile absolute pathname to the XML source file
     * @param string $xslFile absolute pathname to the XSL stylesheet
     *
     * @return string containing the transformed XML output, or false on error
     */
    public function transformFiles($xmlFile, $xslFile)
    {
        return $this->transform($xmlFile, self::XSL_TRANSFORMER_DOCTYPE_FILE, $xslFile, self::XSL_TRANSFORMER_DOCTYPE_FILE, self::XSL_TRANSFORMER_DOCTYPE_STRING);
    }

    /**
     * Apply an XSLT transform to a given XML and XSL strings
     *
     * @param string $xml containing source XML
     * @param string $xsl containing source XSL
     *
     * @return string containing the transformed XML output, or false on error
     */
    public function transformStrings($xml, $xsl)
    {
        return $this->transform($xml, self::XSL_TRANSFORMER_DOCTYPE_STRING, $xsl, self::XSL_TRANSFORMER_DOCTYPE_STRING, self::XSL_TRANSFORMER_DOCTYPE_STRING);
    }

    /**
     * Apply an XSLT transform to a given XML and XSL. Both parameters
     * can be either strings, files or DOM objects.
     *
     * @param int $xmlType
     * @param int $xslType
     * @param int $resultType self::XSL_TRANSFORMER_DOCTYPE_...
     *
     * @return mixed return type depends on the $resultType parameter and can be
     *  DOMDocument or string. The method returns a boolean value of false if the
     *  transformation fails for some reason.
     */
    public function transform($xml, $xmlType, $xsl, $xslType, $resultType)
    {
        // If either XML or XSL file don't exist, then fail without trying to process XSLT
        $fileManager = new FileManager();
        if ($xmlType == self::XSL_TRANSFORMER_DOCTYPE_FILE) {
            if (!$fileManager->fileExists($xml)) {
                return false;
            }
        }
        if ($xslType == self::XSL_TRANSFORMER_DOCTYPE_FILE) {
            if (!$fileManager->fileExists($xsl)) {
                return false;
            }
        }

        // The result type can only be string or DOM
        assert($resultType != self::XSL_TRANSFORMER_DOCTYPE_FILE);

        switch (self::$processor) {
            case 'External':
                return $this->_transformExternal($xml, $xmlType, $xsl, $xslType, $resultType);

            case 'PHP':
                return $this->_transformPHP($xml, $xmlType, $xsl, $xslType, $resultType);

            default:
                // No XSLT processor available
                return false;
        }
    }

    //
    // Private helper methods
    //
    /**
     * Use external programs to do the XSL transformation
     *
     * @param int $xmlType
     * @param int $xslType
     * @param int $resultType self::XSL_TRANSFORMER_DOCTYPE_...
     *
     * @return mixed return type depends on the $resultType parameter and can be
     *  DOMDocument or string. Returns boolean "false" on error.
     */
    public function _transformExternal($xml, $xmlType, $xsl, $xslType, $resultType)
    {

        // External transformation can only be done on files
        if ($xmlType != self::XSL_TRANSFORMER_DOCTYPE_FILE || $xslType != self::XSL_TRANSFORMER_DOCTYPE_FILE) {
            return false;
        }

        // check the external command to check for %xsl and %xml parameter substitution
        if (strpos(self::$externalCommand, '%xsl') === false) {
            return false;
        }
        if (strpos(self::$externalCommand, '%xml') === false) {
            return false;
        }

        // Assemble the parameters to be supplied to the stylesheet
        $parameterString = '';
        foreach ($this->parameters as $name => $value) {
            $parameterString .= str_replace(['%n', '%v'], [$name, $value], self::$externalParameterSnippet);
        }

        // perform %xsl and %xml replacements for fully-qualified shell command
        $xsltCommand = str_replace(['%xsl', '%xml', '%params'], [$xsl, $xml, $parameterString], self::$externalCommand);

        // check for safe mode and escape the shell command
        if (!ini_get('safe_mode')) {
            $xsltCommand = escapeshellcmd($xsltCommand);
        }

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

        $resultXML = implode("\n", $contents);

        switch ($resultType) {
            case self::XSL_TRANSFORMER_DOCTYPE_STRING:
                // Directly return the XML string
                return $resultXML;

            case self::XSL_TRANSFORMER_DOCTYPE_DOM:
                // Instantiate and configure the result DOM
                $resultDOM = new DOMDocument('1.0', static::XSLT_PROCESSOR_ENCODING);
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
     * Use DOMDocument and XSLTProcessor to do the transformation
     *
     * @param int $xmlType
     * @param int $xslType
     * @param int $resultType self::XSL_TRANSFORMER_DOCTYPE_...
     *
     * @return mixed return type depends on the $resultType parameter and can be
     *  DOMDocument or string. Returns boolean "false" on error.
     */
    public function _transformPHP($xml, $xmlType, $xsl, $xslType, $resultType)
    {
        // Prepare the XML DOM
        if ($xmlType == self::XSL_TRANSFORMER_DOCTYPE_DOM) {
            // We already have a DOM document, no need to create one
            assert($xml instanceof DOMDocument);
            $xmlDOM = $xml;
        } else {
            // Instantiate and configure the XML DOM document
            $xmlDOM = new DOMDocument('1.0', static::XSLT_PROCESSOR_ENCODING);

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
                case self::XSL_TRANSFORMER_DOCTYPE_FILE:
                    $xmlDOM->load($xml);
                    break;

                case self::XSL_TRANSFORMER_DOCTYPE_STRING:
                    $xmlDOM->loadXML($xml);
                    break;

                default:
                    assert(false);
            }
        }

        // Prepare the XSL DOM
        if ($xslType == self::XSL_TRANSFORMER_DOCTYPE_DOM) {
            // We already have a DOM document, no need to create one
            assert($xsl instanceof DOMDocument);
            $xslDOM = $xsl;
        } else {
            // Instantiate the XSL DOM document
            $xslDOM = new DOMDocument('1.0', static::XSLT_PROCESSOR_ENCODING);

            // Load the XSL based on its type
            switch ($xslType) {
                case self::XSL_TRANSFORMER_DOCTYPE_FILE:
                    $xslDOM->load($xsl);
                    break;

                case self::XSL_TRANSFORMER_DOCTYPE_STRING:
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
        switch ($resultType) {
            case self::XSL_TRANSFORMER_DOCTYPE_STRING:
                $resultXML = $processor->transformToXML($xmlDOM);
                return $resultXML;

            case self::XSL_TRANSFORMER_DOCTYPE_DOM:
                $resultDOM = $processor->transformToDoc($xmlDOM);
                return $resultDOM;

            default:
                assert(false);
        }
    }

    /**
     * Add an error to the current error list
     *
     * @param string $error
     */
    public function addError($error)
    {
        array_push($this->errors, $error);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\xslt\XSLTransformer', '\XSLTransformer');
    foreach (['XSL_TRANSFORMER_DOCTYPE_STRING', 'XSL_TRANSFORMER_DOCTYPE_FILE', 'XSL_TRANSFORMER_DOCTYPE_DOM'] as $constantName) {
        define($constantName, constant('\XSLTransformer::' . $constantName));
    }
}
