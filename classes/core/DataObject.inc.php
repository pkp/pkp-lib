<?php

/**
 * @file classes/core/DataObject.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DataObject
 * @ingroup core
 *
 * @see Core
 *
 * @brief Any class with an associated DAO should extend this class.
 */

namespace PKP\core;

use PKP\facades\Locale;

class DataObject
{
    /** @var array Array of object data */
    public $_data = [];

    /** @var bool whether this objects loads meta-data adapters from the database */
    public $_hasLoadableAdapters = false;

    /** @var array an array of meta-data extraction adapters (one per supported schema) */
    public $_metadataExtractionAdapters = [];

    /** @var bool whether extraction adapters have already been loaded from the database */
    public $_extractionAdaptersLoaded = false;

    /** @var array an array of meta-data injection adapters (one per supported schema) */
    public $_metadataInjectionAdapters = [];

    /** @var bool whether injection adapters have already been loaded from the database */
    public $_injectionAdaptersLoaded = false;

    /**
     * Constructor
     */
    public function __construct()
    {
    }


    //
    // Getters and Setters
    //
    /**
     * Get a piece of data for this object, localized to the current
     * locale if possible.
     *
     * @param string $key
     * @param string $preferredLocale
     */
    public function getLocalizedData($key, $preferredLocale = null)
    {
        $localePrecedence = $this->_getLocalePrecedence();
        foreach ($localePrecedence as $locale) {
            $value = & $this->getData($key, $locale);
            if (!empty($value)) {
                return $value;
            }
            unset($value);
        }

        // Fallback: Get the first available piece of data.
        $data = $this->getData($key, null);
        if (!empty($data)) {
            $locales = array_keys($data);
            $firstLocale = array_shift($locales);
            return $data[$firstLocale];
        }

        return null;
    }

    /**
     * Get the stack of "important" locales, most important first.

     * @return string[]
     */
    private function _getLocalePrecedence(): array
    {
        static $localePrecedence;
        if (!isset($localePrecedence)) {
            $request = Application::get()->getRequest();
            $localePrecedence = [Locale::getLocale()];

            $context = $request->getContext();
            if ($context && !in_array($context->getPrimaryLocale(), $localePrecedence)) {
                $localePrecedence[] = $context->getPrimaryLocale();
            }

            $site = $request->getSite();
            if ($site && !in_array($site->getPrimaryLocale(), $localePrecedence)) {
                $localePrecedence[] = $site->getPrimaryLocale();
            }
        }
        return $localePrecedence;
    }

    /**
     * Get the value of a data variable.
     *
     * @param string $key
     * @param string $locale (optional)
     */
    public function &getData($key, $locale = null)
    {
        if (is_null($locale)) {
            if (array_key_exists($key, $this->_data)) {
                return $this->_data[$key];
            }
        } else if (array_key_exists($locale, (array) ($this->_data[$key] ?? []))) {
            return $this->_data[$key][$locale];
        }
        $nullVar = null;
        return $nullVar;
    }

    /**
     * Set the value of a new or existing data variable.
     *
     * @param string $key
     * @param mixed $value can be either a single value or
     *  an array of of localized values in the form:
     *   array(
     *     'fr_FR' => 'en français',
     *     'en_US' => 'in English',
     *     ...
     *   )
     * @param string $locale (optional) non-null for a single
     *  localized value. Null for a non-localized value or
     *  when setting all locales at once (see comment for
     *  $value parameter)
     */
    public function setData($key, $value, $locale = null)
    {
        if (is_null($locale)) {
            // This is either a non-localized value or we're passing in all locales at once.
            $this->_data[$key] = $value;
            return;
        }
        // Set a single localized value.
        if (!is_null($value)) {
            $this->_data[$key][$locale] = $value;
            return;
        }
        // If the value is null, remove the entry.
        if (array_key_exists($key, $this->_data)) {
            if (array_key_exists($locale, (array) $this->_data[$key])) {
                unset($this->_data[$key][$locale]);
            }
            // Was this the last entry for the data variable?
            if (empty($this->_data[$key])) {
                unset($this->_data[$key]);
            }
        }
    }

    /**
     * Unset an element of the data object.
     *
     * @param string $key
     * @param string $locale (optional) non-null for a single
     *  localized value. Null for a non-localized value or
     *  when unsetting all locales at once.
     */
    public function unsetData($key, $locale = null)
    {
        if (is_null($locale)) {
            unset($this->_data[$key]);
        } else {
            unset($this->_data[$key][$locale]);
        }
    }

    /**
     * Check whether a value exists for a given data variable.
     *
     * @param string $key
     * @param string $locale (optional)
     *
     * @return bool
     */
    public function hasData($key, $locale = null)
    {
        return is_null($locale) ? array_key_exists($key, $this->_data) : array_key_exists($locale, (array) ($this->_data[$key] ?? []));
    }

    /**
     * Return an array with all data variables.
     *
     * @return array
     */
    public function &getAllData()
    {
        return $this->_data;
    }

    /**
     * Set all data variables at once.
     *
     * @param array $data
     */
    public function setAllData($data)
    {
        $this->_data = $data;
    }

    /**
     * Get ID of object.
     *
     * @return int
     */
    public function getId()
    {
        return $this->getData('id');
    }

    /**
     * Set ID of object.
     *
     * @param int $id
     */
    public function setId($id)
    {
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
     * @param \PKP\core\DataObject $targetObject The object to cast to.
     *
     * @return \PKP\core\DataObject The upcast target object.
     */
    public function upcastTo($targetObject)
    {
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
     *
     * @param bool $hasLoadableAdapters
     */
    public function setHasLoadableAdapters($hasLoadableAdapters)
    {
        $this->_hasLoadableAdapters = $hasLoadableAdapters;
    }

    /**
     * Get whether the object has loadable meta-data adapters
     *
     * @return bool
     */
    public function getHasLoadableAdapters()
    {
        return $this->_hasLoadableAdapters;
    }

    /**
     * Add a meta-data adapter that will be supported
     * by this application entity. Only one adapter per schema
     * can be added.
     *
     * @param MetadataDataObjectAdapter $metadataAdapter
     */
    public function addSupportedMetadataAdapter($metadataAdapter)
    {
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
     * @param string $metadataSchemaName fully qualified class name
     *
     * @return bool true if an adapter was removed, otherwise false.
     */
    public function removeSupportedMetadataAdapter($metadataSchemaName)
    {
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
     *
     * @return array
     */
    public function getSupportedExtractionAdapters()
    {
        // Load meta-data adapters from the database.
        if ($this->getHasLoadableAdapters() && !$this->_extractionAdaptersLoaded) {
            $filterDao = \DAORegistry::getDAO('FilterDAO'); /** @var FilterDAO $filterDao */
            $loadedAdapters = $filterDao->getObjectsByTypeDescription('class::%', 'metadata::%', $this);
            foreach ($loadedAdapters as $loadedAdapter) {
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
     *
     * @return array
     */
    public function getSupportedInjectionAdapters()
    {
        // Load meta-data adapters from the database.
        if ($this->getHasLoadableAdapters() && !$this->_injectionAdaptersLoaded) {
            $filterDao = \DAORegistry::getDAO('FilterDAO'); /** @var FilterDAO $filterDao */
            $loadedAdapters = $filterDao->getObjectsByTypeDescription('metadata::%', 'class::%', $this, false);
            foreach ($loadedAdapters as $loadedAdapter) {
                $this->addSupportedMetadataAdapter($loadedAdapter);
            }
            $this->_injectionAdaptersLoaded = true;
        }

        return $this->_metadataInjectionAdapters;
    }

    /**
     * Returns all supported meta-data schemas
     * which are supported by extractor adapters.
     *
     * @return array
     */
    public function getSupportedMetadataSchemas()
    {
        $supportedMetadataSchemas = [];
        $extractionAdapters = $this->getSupportedExtractionAdapters();
        foreach ($extractionAdapters as $metadataAdapter) {
            $supportedMetadataSchemas[] = $metadataAdapter->getMetadataSchema();
        }
        return $supportedMetadataSchemas;
    }

    /**
     * Retrieve the names of meta-data
     * properties of this data object.
     *
     * @param bool $translated if true, return localized field
     *  names, otherwise return additional field names.
     */
    public function getMetadataFieldNames($translated = true)
    {
        // Create a list of all possible meta-data field names
        $metadataFieldNames = [];
        $extractionAdapters = $this->getSupportedExtractionAdapters();
        foreach ($extractionAdapters as $metadataAdapter) {
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
     *
     * @param bool $translated if true, return localized field
     *  names, otherwise return additional field names.
     *
     * @return array an array of field names
     */
    public function getSetMetadataFieldNames($translated = true)
    {
        // Retrieve a list of all possible meta-data field names
        $metadataFieldNameCandidates = $this->getMetadataFieldNames($translated);

        // Only retain those fields that have data
        $metadataFieldNames = [];
        foreach ($metadataFieldNameCandidates as $metadataFieldNameCandidate) {
            if ($this->hasData($metadataFieldNameCandidate)) {
                $metadataFieldNames[] = $metadataFieldNameCandidate;
            }
        }
        return $metadataFieldNames;
    }

    /**
     * Retrieve the names of translated meta-data
     * properties that need to be persisted.
     *
     * @return array an array of field names
     */
    public function getLocaleMetadataFieldNames()
    {
        return $this->getMetadataFieldNames(true);
    }

    /**
     * Retrieve the names of additional meta-data
     * properties that need to be persisted.
     *
     * @return array an array of field names
     */
    public function getAdditionalMetadataFieldNames()
    {
        return $this->getMetadataFieldNames(false);
    }

    /**
     * Inject a meta-data description into this
     * data object.
     *
     * @param MetadataDescription $metadataDescription
     *
     * @return bool true on success, otherwise false
     */
    public function injectMetadata($metadataDescription)
    {
        $dataObject = null;
        $metadataSchemaName = $metadataDescription->getMetadataSchemaName();
        $injectionAdapters = $this->getSupportedInjectionAdapters();
        if (isset($injectionAdapters[$metadataSchemaName])) {
            // Get the meta-data adapter that supports the
            // given meta-data description's schema.
            $metadataAdapter = $injectionAdapters[$metadataSchemaName]; /** @var MetadataDataObjectAdapter $metadataAdapter */

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
     *
     * @param MetadataSchema $metadataSchema
     *
     * @return $metadataDescription MetadataDescription
     */
    public function extractMetadata($metadataSchema)
    {
        $metadataDescription = null;
        $metadataSchemaName = $metadataSchema->getClassName();
        $extractionAdapters = $this->getSupportedExtractionAdapters();
        if (isset($extractionAdapters[$metadataSchemaName])) {
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
     *
     * @return DAO
     */
    public function getDAO()
    {
        assert(false);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\core\DataObject', '\DataObject');
}
