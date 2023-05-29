<?php
/**
 * @file classes/filter/Filter.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Filter
 *
 * @ingroup filter
 *
 * @brief Class that provides the basic template for a filter. Filters are
 *  generic data processors that take in a well-specified data type
 *  and return another well-specified data type.
 *
 *  Filters enable us to re-use data transformations between applications.
 *  Generic filter implementations can sequence, (de-)multiplex or iterate
 *  over other filters. Thereby filters can be nested and combined in many
 *  different ways to form complex and easy-to-customize data processing
 *  networks or pipelines.
 *
 *  NB: This also means that filters only make sense if they accept and
 *  return standardized formats that are understood by other filters. Otherwise
 *  the extra implementation effort for a filter won't result in improved code
 *  re-use.
 *
 *  Objects from different applications (e.g. Papers and Articles) can first be
 *  transformed by an application specific filter into a common format and then
 *  be processed by application agnostic import/export filters or vice versa.
 *  Filters can be used to pre-process data before it is indexed for search.
 *  They also provide a framework to customize the processing applied in citation
 *  parsing and lookup (i.e. which parsers and lookup sources should be applied).
 *
 *  Filters can be used stand-alone outside PKP applications.
 *
 *  The following is a complete list of all use-cases that have been identified
 *  for filters:
 *  1) Decode/Encode
 *  * import/export: transform application objects (e.g. an Article object)
 *    into structured (rich) data formats (e.g. XML, OpenURL KEV, CSV) or
 *    vice versa.
 *  * parse: transform unstructured clob/blob data (e.g. a Word Document)
 *    into application objects (e.g. an Article plus Citation objects) or
 *    into structured data formats (e.g. XML).
 *  * render: transform application objects or structured clob/blob data into
 *    an unstructured document (e.g. PDF, HTML, Word Document).
 *
 *  2) Normalize
 *  * lookup: compare the data of a given entity (e.g. a bibliographic
 *    reference) with data from other sources (e.g. Crossref) and use this
 *    to normalize data or improve data quality.
 *  * harvest: cleanse and normalize incoming meta-data
 *
 *  3) Map
 *  * cross-walk: transform one meta-data format into another. Meta-data
 *    can be represented as structured clob/blob data (e.g. XML) or as
 *    application objects (i.e. a MetadataRecord instance).
 *  * meta-data extraction: retrieve meta-data from OO entities
 *    (e.g. an Article) into a standardized meta-data record (e.g. NLM
 *    element-citation).
 *  * meta-data injection: inject data from a standardized meta-data
 *    record into application objects.
 *
 *  4) Convert documents
 *  * binary converters: wrap binary document converters (e.g. antidoc) in
 *    a well-defined and re-usable way.
 *
 *  5) Search
 *  * indexing: pre-process data (extract, tokenize, remove stopwords,
 *    stem) for indexing.
 *  * finding: pre-process queries (parse, tokenize, remove stopwords,
 *    stem) to access the index
 */

namespace PKP\filter;

use Exception;
use PKP\core\PKPApplication;
use PKP\core\PKPString;
use PKP\core\RuntimeEnvironment;
use PKP\plugins\Hook;

class Filter extends \PKP\core\DataObject
{
    /** @var TypeDescription */
    public $_inputType;

    /** @var TypeDescription */
    public $_outputType;

    /**  */
    public $_input;

    /**  */
    public $_output;

    /** @var array a list of errors occurred while filtering */
    public $_errors = [];

    /**
     * @var RuntimeEnvironment the installation requirements required to
     * run this filter instance, false on initialization.
     */
    public $_runtimeEnvironment = false;

    /**
     * Constructor
     *
     * Receives input and output type that define the transformation.
     *
     * @see TypeDescription
     *
     * @param string $inputType a string representation of a TypeDescription
     * @param string $outputType a string representation of a TypeDescription
     */
    public function __construct($inputType, $outputType)
    {
        // Initialize the filter.
        parent::__construct();
        $this->setTransformationType($inputType, $outputType);
    }

    //
    // Setters and Getters
    //
    /**
     * Set the display name
     *
     * @param string $displayName
     */
    public function setDisplayName($displayName)
    {
        $this->setData('displayName', $displayName);
    }

    /**
     * Get the display name
     *
     * NB: The standard implementation of this
     * method will initialize the display name
     * with the filter class name. Subclasses can of
     * course override this behavior by explicitly
     * setting a display name.
     *
     * @return string
     */
    public function getDisplayName()
    {
        if (!$this->hasData('displayName')) {
            $this->setData('displayName', get_class($this));
        }

        return $this->getData('displayName');
    }

    /**
     * Set the sequence id
     *
     * @param int $seq
     */
    public function setSequence($seq)
    {
        $this->setData('seq', $seq);
    }

    /**
     * Get the sequence id
     *
     * @return int
     */
    public function getSequence()
    {
        return $this->getData('seq');
    }

    /**
     * Set the input/output type of this filter group.
     *
     * @param TypeDescription|string $inputType
     * @param TypeDescription|string $outputType
     *
     * @see TypeDescriptionFactory::instantiateTypeDescription() for more details
     */
    public function setTransformationType(&$inputType, &$outputType)
    {
        $typeDescriptionFactory = TypeDescriptionFactory::getInstance();

        // Instantiate the type descriptions if we got string input.
        if (!$inputType instanceof TypeDescription) {
            assert(is_string($inputType));
            $inputType = $typeDescriptionFactory->instantiateTypeDescription($inputType);
        }
        if (!$outputType instanceof TypeDescription) {
            assert(is_string($outputType));
            $outputType = $typeDescriptionFactory->instantiateTypeDescription($outputType);
        }

        $this->_inputType = $inputType;
        $this->_outputType = $outputType;
    }


    /**
     * Get the input type
     *
     * @return TypeDescription
     */
    public function &getInputType()
    {
        return $this->_inputType;
    }

    /**
     * Get the output type
     *
     * @return TypeDescription
     */
    public function &getOutputType()
    {
        return $this->_outputType;
    }

    /**
     * Get the last valid output produced by
     * this filter.
     *
     * This can be used for debugging internal
     * filter state or for access to intermediate
     * results when working with larger filter
     * grids.
     *
     * NB: The output will be set only after
     * output validation so that you can be
     * sure that you'll always find valid
     * data here.
     *
     */
    public function &getLastOutput()
    {
        return $this->_output;
    }

    /**
     * Get the last valid input processed by
     * this filter.
     *
     * This can be used for debugging internal
     * filter state or for access to intermediate
     * results when working with larger filter
     * grids.
     *
     * NB: The input will be set only after
     * input validation so that you can be
     * sure that you'll always find valid
     * data here.
     *
     */
    public function &getLastInput()
    {
        return $this->_input;
    }

    /**
     * Add a filter error
     *
     * @param string $message
     */
    public function addError($message)
    {
        $this->_errors[] = $message;
    }

    /**
     * Get all filter errors
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->_errors;
    }

    /**
     * Whether this filter has produced errors.
     *
     * @return bool
     */
    public function hasErrors()
    {
        return (!empty($this->_errors));
    }

    /**
     * Clear all processing errors.
     */
    public function clearErrors()
    {
        $this->_errors = [];
    }

    /**
     * Set the required runtime environment
     *
     * @param RuntimeEnvironment $runtimeEnvironment
     */
    public function setRuntimeEnvironment(&$runtimeEnvironment)
    {
        assert($runtimeEnvironment instanceof RuntimeEnvironment);
        $this->_runtimeEnvironment = & $runtimeEnvironment;

        // Inject the runtime settings into the data object
        // for persistence.
        $runtimeSettings = $this->supportedRuntimeEnvironmentSettings();
        foreach ($runtimeSettings as $runtimeSetting => $defaultValue) {
            $methodName = 'get' . PKPString::ucfirst($runtimeSetting);
            $this->setData($runtimeSetting, $runtimeEnvironment->$methodName());
        }
    }

    /**
     * Get the required runtime environment
     *
     * @return RuntimeEnvironment
     */
    public function &getRuntimeEnvironment()
    {
        return $this->_runtimeEnvironment;
    }


    //
    // Abstract template methods to be implemented by subclasses
    //
    /**
     * This method performs the actual data processing.
     * NB: sub-classes must implement this method.
     *
     * @param mixed $input validated filter input data
     *
     * @return mixed non-validated filter output or null
     *  if processing was not successful.
     */
    public function &process(&$input)
    {
        assert(false);
    }

    //
    // Public methods
    //
    /**
     * Returns true if the given input and output
     * objects represent a valid transformation
     * for this filter.
     *
     * This check must be type based. It can
     * optionally include an additional stateful
     * inspection of the given object instances.
     *
     * If the output type is null then only
     * check whether the given input type is
     * one of the input types accepted by this
     * filter.
     *
     * The standard implementation provides full
     * type based checking. Subclasses must
     * implement any required stateful inspection
     * of the provided objects.
     *
     *
     * @return bool
     */
    public function supports(&$input, &$output)
    {
        // Validate input
        $inputType = & $this->getInputType();
        $validInput = $inputType->isCompatible($input);

        // If output is null then we're done
        if (is_null($output)) {
            return $validInput;
        }

        // Validate output
        $outputType = & $this->getOutputType();
        $validOutput = $outputType->isCompatible($output);

        return $validInput && $validOutput;
    }

    /**
     * Returns true if the given input is supported
     * by this filter. Otherwise returns false.
     *
     * NB: sub-classes will not normally override
     * this method.
     *
     *
     * @return bool
     */
    public function supportsAsInput(&$input)
    {
        $nullVar = null;
        return($this->supports($input, $nullVar));
    }

    /**
     * Check whether the filter is compatible with
     * the required runtime environment.
     *
     * @return bool
     */
    public function isCompatibleWithRuntimeEnvironment()
    {
        if ($this->_runtimeEnvironment === false) {
            $phpVersionMin = $phpVersionMax = $phpExtensions = $externalPrograms = null;
            // The runtime environment has never been
            // queried before.
            $runtimeSettings = $this->supportedRuntimeEnvironmentSettings();

            // Find out whether we have any runtime restrictions set.
            $hasRuntimeSettings = false;
            foreach ($runtimeSettings as $runtimeSetting => $defaultValue) {
                if ($this->hasData($runtimeSetting)) {
                    $$runtimeSetting = $this->getData($runtimeSetting);
                    $hasRuntimeSettings = true;
                } else {
                    $$runtimeSetting = $defaultValue;
                }
            }

            // If we found any runtime restrictions then construct a
            // runtime environment from the settings.
            if ($hasRuntimeSettings) {
                $this->_runtimeEnvironment = new RuntimeEnvironment($phpVersionMin, $phpVersionMax, $phpExtensions, $externalPrograms);
            } else {
                // Set null so that we don't try to construct
                // a runtime environment object again.
                $this->_runtimeEnvironment = null;
            }
        }

        if (is_null($this->_runtimeEnvironment) || $this->_runtimeEnvironment->isCompatible()) {
            return true;
        }

        return false;
    }

    /**
     * Filters the given input.
     *
     * Input and output of this method will
     * be tested for compliance with the filter
     * definition.
     *
     * NB: sub-classes will not normally override
     * this method.
     *
     * @param mixed $input an input value that is supported
     *  by this filter
     * @param bool $returnErrors whether the value
     *  should be returned also if an error occurred
     *
     * @return mixed a valid return value or null
     *  if an error occurred during processing
     */
    public function &execute(&$input, $returnErrors = false)
    {
        // Make sure that we don't destroy referenced
        // data somewhere out there.
        unset($this->_input, $this->_output);

        // Check the runtime environment
        if (!$this->isCompatibleWithRuntimeEnvironment()) {
            // Missing installation requirements.
            throw new Exception(__('filter.error.missingRequirements'));
        }

        // Validate the filter input
        if (!$this->supportsAsInput($input)) {
            // An exception is thrown if the input is not supported.
            throw new Exception(__('filter.input.error.notSupported', [
                'displayName' => $this->getDisplayName(),
                'inputTypeName' => $this->getInputType()->_typeName,
                'typeofInput' => gettype($input) === 'object'
                    ? get_class($input)
                    : gettype($input)
            ]));
        }

        // Save a reference to the last valid input
        $this->_input = & $input;
        $this->_output = null;

        // Process the filter
        $preliminaryOutput = & $this->process($input);

        $classNameParts = explode('\\', get_class($this)); // Separate namespace info from class name
        Hook::call(strtolower_codesafe(end($classNameParts) . '::execute'), [&$preliminaryOutput]);

        // Validate the filter output
        $isValidOutput = $preliminaryOutput !== null && $this->supports($input, $preliminaryOutput);
        if ($isValidOutput || $returnErrors) {
            $this->_output = & $preliminaryOutput;
        }
        if (!$isValidOutput) {
            error_log('Filter output validation failed, expected "' . $this->getOutputType()->getTypeName() . '", but found "' . gettype($preliminaryOutput) . '"');
        }

        // Return processed data
        return $this->_output;
    }

    //
    // Public helper methods
    //
    /**
     * Returns a static array with supported runtime
     * environment settings and their default values.
     *
     * @return array
     */
    public static function supportedRuntimeEnvironmentSettings()
    {
        static $runtimeEnvironmentSettings = [
            'phpVersionMin' => PKPApplication::PHP_REQUIRED_VERSION,
            'phpVersionMax' => null,
            'phpExtensions' => [],
            'externalPrograms' => []
        ];

        return $runtimeEnvironmentSettings;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\filter\Filter', '\Filter');
}
