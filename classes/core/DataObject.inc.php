<?php

/**
 * @file classes/core/DataObject.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class DataObject
 * @ingroup core
 * @see Core
 *
 * @brief Any class with an associated DAO should extend this class.
 */


class DataObject {
	/** @var array Array of object data */
	var $_data = array();

	/** @var boolean whether this objects loads meta-data adapters from the database */
	var $_hasLoadableAdapters = false;

	/** @var array an array of meta-data extraction adapters (one per supported schema) */
	var $_metadataExtractionAdapters = array();

	/** @var boolean whether extraction adapters have already been loaded from the database */
	var $_extractionAdaptersLoaded = false;

	/** @var array an array of meta-data injection adapters (one per supported schema) */
	var $_metadataInjectionAdapters = array();

	/** @var boolean whether injection adapters have already been loaded from the database */
	var $_injectionAdaptersLoaded = false;

	/**
	 * Constructor.
	 */
	function __construct() {
	}


	//
	// Getters and Setters
	//
	/**
	 * Get a piece of data for this object, localized to the current
	 * locale if possible.
	 * @param $key string
	 * @return mixed
	 */
	function &getLocalizedData($key) {
		$localePrecedence = AppLocale::getLocalePrecedence();
		foreach ($localePrecedence as $locale) {
			$value =& $this->getData($key, $locale);
			if (!empty($value)) return $value;
			unset($value);
		}

		// Fallback: Get the first available piece of data.
		$data =& $this->getData($key, null);
		if (!empty($data)) {
			// WARNING: Collapsing the following into a single line causes PHP 5.0.5 to die.
			$locales = array_keys($data);
			$firstLocale = array_shift($locales);
			return $data[$firstLocale];
		}

		// No data available; return null.
		unset($data);
		$data = null;
		return $data;
	}

	/**
	 * Get the value of a data variable.
	 * @param $key string
	 * @param $locale string (optional)
	 * @return mixed
	 */
	function &getData($key, $locale = null) {
		if (is_null($locale)) {
			if (array_key_exists($key, $this->_data)) {
				return $this->_data[$key];
			}
		} else {
			// see http://bugs.php.net/bug.php?id=29848
			if (array_key_exists($key, $this->_data) && is_array($this->_data[$key]) && array_key_exists($locale, $this->_data[$key])) {
				return $this->_data[$key][$locale];
			}
		}
		$nullVar = null;
		return $nullVar;
	}

	/**
	 * Set the value of a new or existing data variable.
	 * NB: Passing in null as a value will unset the
	 * data variable if it already existed.
	 * @param $key string
	 * @param $value mixed can be either a single value or
	 *  an array of of localized values in the form:
	 *   array(
	 *     'fr_FR' => 'en français',
	 *     'en_US' => 'in English',
	 *     ...
	 *   )
	 * @param $locale string (optional) non-null for a single
	 *  localized value. Null for a non-localized value or
	 *  when setting all locales at once (see comment for
	 *  $value parameter)
	 */
	function setData($key, $value, $locale = null) {
		if (is_null($locale)) {
			// This is either a non-localized value or we're
			// passing in all locales at once.
			if (is_null($value)) {
				if (array_key_exists($key, $this->_data)) unset($this->_data[$key]);
			} else {
				$this->_data[$key] = $value;
			}
		} else {
			// (Un-)set a single localized value.
			if (is_null($value)) {
				// see http://bugs.php.net/bug.php?id=29848
				if (array_key_exists($key, $this->_data)) {
					if (is_array($this->_data[$key]) && array_key_exists($locale, $this->_data[$key])) unset($this->_data[$key][$locale]);
					// Was this the last entry for the data variable?
					if (empty($this->_data[$key])) unset($this->_data[$key]);
				}
			} else {
				$this->_data[$key][$locale] = $value;
			}
		}
	}

	/**
	 * Check whether a value exists for a given data variable.
	 * @param $key string
	 * @param $locale string (optional)
	 * @return boolean
	 */
	function hasData($key, $locale = null) {
		if (is_null($locale)) {
			return array_key_exists($key, $this->_data);
		} else {
			// see http://bugs.php.net/bug.php?id=29848
			return array_key_exists($key, $this->_data) && is_array($this->_data[$key]) && array_key_exists($locale, $this->_data[$key]);
		}
	}

	/**
	 * Return an array with all data variables.
	 * @return array
	 */
	function &getAllData() {
		return $this->_data;
	}

	/**
	 * Set all data variables at once.
	 * @param $data array
	 */
	function setAllData(&$data) {
		$this->_data =& $data;
	}

	/**
	 * Get ID of object.
	 * @return int
	 */
	function getId() {
		return $this->getData('id');
	}

	/**
	 * Set ID of object.
	 * @param $id int
	 */
	function setId($id) {
		$this->setData('id', $id);
	}


	//
	// Public helper methods
	//
	/**
	 * Upcast this data object to the target object.
	 *
	 * We use the DataObject's getAllData() and setAllData() interface
	 * to upcast objects. This means that if the default implementations
	 * of these methods do not provide data that is adequate for
	 * upcasting between objects of the same inheritance hierarchy
	 * then objects that need more complicated casting behavior
	 * must override these methods.
	 *
	 * Note: Data in the target object will be overwritten. We do not
	 * clone the target object before we upcast.
	 *
	 * @param $targetObject DataObject The object to cast to.
	 *
	 * @return DataObject The upcast target object.
	 */
	function upcastTo($targetObject) {
		// Copy data from the source to the target.
		$targetObject->setAllData($this->getAllData());

		// Return the upcast object.
		return $targetObject;
	}


	//
	// MetadataProvider interface implementation
	//
	/**
	 * Set whether the object has loadable meta-data adapters
	 * @param $hasLoadableAdapters boolean
	 */
	function setHasLoadableAdapters($hasLoadableAdapters) {
		$this->_hasLoadableAdapters = $hasLoadableAdapters;
	}

	/**
	 * Get whether the object has loadable meta-data adapters
	 * @return boolean
	 */
	function getHasLoadableAdapters() {
		return $this->_hasLoadableAdapters;
	}

	/**
	 * Add a meta-data adapter that will be supported
	 * by this application entity. Only one adapter per schema
	 * can be added.
	 * @param $metadataAdapter MetadataDataObjectAdapter
	 */
	function addSupportedMetadataAdapter($metadataAdapter) {
		$metadataSchemaName = $metadataAdapter->getMetadataSchemaName();
		assert(!empty($metadataSchemaName));

		// NB: Some adapters are injectors and extractors at the same time,
		// notably the meta-data description dummy adapter that converts
		// from/to a meta-data description. That's why we have to check
		// input and output type separately.

		// Is this a meta-data extractor?
		$inputType = $metadataAdapter->getInputType();
		if ($inputType->checkType($this)) {
			if (!isset($this->_metadataExtractionAdapters[$metadataSchemaName])) {
				$this->_metadataExtractionAdapters[$metadataSchemaName] = $metadataAdapter;
			}
		}

		// Is this a meta-data injector?
		$outputType = $metadataAdapter->getOutputType();
		if ($outputType->checkType($this)) {
			if (!isset($this->_metadataInjectionAdapters[$metadataSchemaName])) {
				$this->_metadataInjectionAdapters[$metadataSchemaName] = $metadataAdapter;
			}
		}
	}

	/**
	 * Remove all adapters for the given meta-data schema
	 * (if it exists).
	 *
	 * @param $metadataSchemaName string fully qualified class name
	 * @return boolean true if an adapter was removed, otherwise false.
	 */
	function removeSupportedMetadataAdapter($metadataSchemaName) {
		$result = false;
		if (isset($this->_metadataExtractionAdapters[$metadataSchemaName])) {
			unset($this->_metadataExtractionAdapters[$metadataSchemaName]);
			$result = true;
		}
		if (isset($this->_metadataInjectionAdapters[$metadataSchemaName])) {
			unset($this->_metadataInjectionAdapters[$metadataSchemaName]);
			$result = true;
		}
		return $result;
	}

	/**
	 * Get all meta-data extraction adapters that
	 * support this data object. This includes adapters
	 * loaded from the database.
	 * @return array
	 */
	function getSupportedExtractionAdapters() {
		// Load meta-data adapters from the database.
		if ($this->getHasLoadableAdapters() && !$this->_extractionAdaptersLoaded) {
			$filterDao = DAORegistry::getDAO('FilterDAO'); /* @var $filterDao FilterDAO */
			$loadedAdapters = $filterDao->getObjectsByTypeDescription('class::%', 'metadata::%', $this);
			foreach($loadedAdapters as $loadedAdapter) {
				$this->addSupportedMetadataAdapter($loadedAdapter);
			}
			$this->_extractionAdaptersLoaded = true;
		}

		return $this->_metadataExtractionAdapters;
	}

	/**
	 * Get all meta-data injection adapters that
	 * support this data object. This includes adapters
	 * loaded from the database.
	 * @return array
	 */
	function getSupportedInjectionAdapters() {
		// Load meta-data adapters from the database.
		if ($this->getHasLoadableAdapters() && !$this->_injectionAdaptersLoaded) {
			$filterDao = DAORegistry::getDAO('FilterDAO'); /* @var $filterDao FilterDAO */
			$loadedAdapters = $filterDao->getObjectsByTypeDescription('metadata::%', 'class::%', $this, false);
			foreach($loadedAdapters as $loadedAdapter) {
				$this->addSupportedMetadataAdapter($loadedAdapter);
			}
			$this->_injectionAdaptersLoaded = true;
		}

		return $this->_metadataInjectionAdapters;
	}

	/**
	 * Returns all supported meta-data schemas
	 * which are supported by extractor adapters.
	 * @return array
	 */
	function getSupportedMetadataSchemas() {
		$supportedMetadataSchemas = array();
		$extractionAdapters = $this->getSupportedExtractionAdapters();
		foreach($extractionAdapters as $metadataAdapter) {
			$supportedMetadataSchemas[] = $metadataAdapter->getMetadataSchema();
		}
		return $supportedMetadataSchemas;
	}

	/**
	 * Retrieve the names of meta-data
	 * properties of this data object.
	 * @param $translated boolean if true, return localized field
	 *  names, otherwise return additional field names.
	 */
	function getMetadataFieldNames($translated = true) {
		// Create a list of all possible meta-data field names
		$metadataFieldNames = array();
		$extractionAdapters = $this->getSupportedExtractionAdapters();
		foreach($extractionAdapters as $metadataAdapter) {
			// Add the field names from the current adapter
			$metadataFieldNames = array_merge(
				$metadataFieldNames,
				$metadataAdapter->getDataObjectMetadataFieldNames($translated)
			);
		}
		return array_unique($metadataFieldNames);
	}

	/**
	 * Retrieve the names of meta-data
	 * properties that need to be persisted
	 * (i.e. that have data).
	 * @param $translated boolean if true, return localized field
	 *  names, otherwise return additional field names.
	 * @return array an array of field names
	 */
	function getSetMetadataFieldNames($translated = true) {
		// Retrieve a list of all possible meta-data field names
		$metadataFieldNameCandidates = $this->getMetadataFieldNames($translated);

		// Only retain those fields that have data
		$metadataFieldNames = array();
		foreach($metadataFieldNameCandidates as $metadataFieldNameCandidate) {
			if($this->hasData($metadataFieldNameCandidate)) {
				$metadataFieldNames[] = $metadataFieldNameCandidate;
			}
		}
		return $metadataFieldNames;
	}

	/**
	 * Retrieve the names of translated meta-data
	 * properties that need to be persisted.
	 * @return array an array of field names
	 */
	function getLocaleMetadataFieldNames() {
		return $this->getMetadataFieldNames(true);
	}

	/**
	 * Retrieve the names of additional meta-data
	 * properties that need to be persisted.
	 * @return array an array of field names
	 */
	function getAdditionalMetadataFieldNames() {
		return $this->getMetadataFieldNames(false);
	}

	/**
	 * Inject a meta-data description into this
	 * data object.
	 * @param $metadataDescription MetadataDescription
	 * @param $replace boolean whether to delete existing meta-data
	 * @return boolean true on success, otherwise false
	 */
	function injectMetadata($metadataDescription) {
		$dataObject = null;
		$metadataSchemaName = $metadataDescription->getMetadataSchemaName();
		$injectionAdapters = $this->getSupportedInjectionAdapters();
		if(isset($injectionAdapters[$metadataSchemaName])) {
			// Get the meta-data adapter that supports the
			// given meta-data description's schema.
			$metadataAdapter = $injectionAdapters[$metadataSchemaName]; /* @var $metadataAdapter MetadataDataObjectAdapter */

			// Pass in a reference to the data object which
			// the filter will use to update the current instance
			// of the data object.
			$metadataAdapter->setTargetDataObject($this);

			// Use adapter filter to convert from a meta-data
			// description to a data object.
			$dataObject = $metadataAdapter->execute($metadataDescription);
		}
		return $dataObject;
	}

	/**
	 * Extract a meta-data description from this
	 * data object.
	 * @param $metadataSchema MetadataSchema
	 * @return $metadataDescription MetadataDescription
	 */
	function extractMetadata($metadataSchema) {
		$metadataDescription = null;
		$metadataSchemaName = $metadataSchema->getClassName();
		$extractionAdapters = $this->getSupportedExtractionAdapters();
		if(isset($extractionAdapters[$metadataSchemaName])) {
			// Get the meta-data adapter that supports the
			// given meta-data description's schema.
			$metadataAdapter = $extractionAdapters[$metadataSchemaName];

			// Use adapter filter to convert from a data object
			// to a meta-data description.
			$metadataDescription = $metadataAdapter->execute($this);
		}
		return $metadataDescription;
	}

	/**
	 * Get DAO class name for this object.
	 * @return DAO
	 */
	function getDAO() {
		assert(false);
	}

	/**
	 * Compile data array representing this object
	 *
	 * This method is intended to be used with the rest API or other situations
	 * where we want to pass a compiled representation of this data object.
	 *
	 * @params array $params A key/value hash indicating which data should be
	 *  returned. Default: null. All values are compiled and returned when
	 *  $params is null.
	 * @return array
	 */
	public function toArray($params = null) {

		$output = array();

		if (is_null($params)) {
			$params = array(
				'id' => true,
			);
		}

		if (!empty($params['id'])) {
			$output['id'] = $this->getId();
		}

		return $output;
	}

	/**
	 * Compiles the params passed to the toArray method
	 *
	 * Merges requested params with the defaults, and filters out those which
	 * shouldn't be included
	 *
	 * @params array $defaultParams The default param settings
	 * @params array|null $params The param settings for this request
	 * @return array
	 */
	public function getOutputParams($defaultParams, $params = null) {

		$compiled = is_null($params) ? $defaultParams : array_merge($defaultParams, $params);

		$output = array_filter($compiled, array($this, '_filterOutputParams'));

		return $output;
	}

	/**
	 * Helper function to check roles for getOutputParams. Used by array_filter
	 *
	 * @params array $param
	 */
	public function _filterOutputParams($param) {
		$currentUser = Application::getRequest()->getUser();
		$context = Application::getRequest()->getContext();
		if (!$context) {
			return false;
		}

		if ($param === true) {
			return true;
		} elseif (is_array($param) && !is_null($currentUser)) {
			if ($currentUser->hasRole($param, $context->getId())) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Retrieve the result of a "get" method if one exists
	 *
	 * This takes a key (eg `title`) and looks for accessor methods (eg
	 * `getLocalizedTitle` or `getTitle`). If found, it returns the method name.
	 * This is used as a shortcut for retrieving common params compiled in the
	 * toArray() method.
	 *
	 * @params string $param The param to searh for a get method.
	 * @return string
	 */
	public function findGetMethod($param) {

		$method = '';

		if (method_exists($this, 'getLocalized' . ucfirst($param))) {
			$method = 'getLocalized' . ucfirst($param);
		} elseif (method_exists($this, 'get' . ucfirst($param))) {
			$method = 'get' . ucfirst($param);
		}

		if (empty($method)) {
			error_log('No method found for retrieving param ' . $param . ' in ' . get_class($this) . ' on line ' . __LINE__ . '.');
			return '';
		}

		return $method;
	}
}
?>
