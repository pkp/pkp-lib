<?php

/**
 * @file classes/core/DataObject.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DataObject
 *
 * @ingroup core
 *
 * @see Core
 *
 * @brief Any class with an associated DAO should extend this class.
 */

namespace PKP\core;

use APP\core\Application;
use Exception;
use PKP\db\DAO;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\filter\FilterDAO;
use PKP\metadata\MetadataDataObjectAdapter;
use PKP\metadata\MetadataDescription;
use PKP\metadata\MetadataSchema;

/**
 * @template T of EntityDAO|DAO
 */
class DataObject
{
    /** @var array Array of object data */
    public array $_data = [];

    /** @var bool Whether this objects loads meta-data adapters from the database */
    public bool $_hasLoadableAdapters = false;

    /** @var array An array of meta-data extraction adapters (one per supported schema) */
    public array $_metadataExtractionAdapters = [];

    /** @var bool whether extraction adapters have already been loaded from the database */
    public bool $_extractionAdaptersLoaded = false;

    /** @var array An array of meta-data injection adapters (one per supported schema) */
    public array $_metadataInjectionAdapters = [];

    /** @var mixed Whether injection adapters have already been loaded from the database */
    public mixed $_injectionAdaptersLoaded = false;

    /** @var Conversion table for locales */
    public array $_localesTable = [
        'be@cyrillic' => 'be',
        'bs' => 'bs_Latn',
        'fr_FR' => 'fr',
        'nb' => 'nb_NO',
        'sr@cyrillic' => 'sr_Cyrl',
        'sr@latin' => 'sr_Latn',
        'uz@cyrillic' => 'uz',
        'uz@latin' => 'uz_Latn',
        'zh_CN' => 'zh_Hans',
    ];

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
     */
    public function getLocalizedData(string $key, ?string $preferredLocale = null, ?string &$selectedLocale = null): mixed
    {
        foreach ($this->getLocalePrecedence($preferredLocale) as $locale) {
            $value = & $this->getData($key, $locale);
            if (!empty($value)) {
                $selectedLocale = $locale;
                return $value;
            }
            unset($value);
        }

        // Fallback: Get the first available piece of data.
        $data = $this->getData($key, null);
        foreach ((array) $data as $locale => $dataValue) {
            if (!empty($dataValue)) {
                $selectedLocale = $locale;
                return $dataValue;
            }
        }

        return null;
    }

    /**
     * Get the locale precedence order for object in the following order
     *
     * 1. Preferred Locale if provided
     * 2. User's current local
     * 3. Object's default locale if set
     * 4. Context's primary locale if context available
     * 5. Site's primary locale
     */
    public function getLocalePrecedence(?string $preferredLocale = null): array
    {
        $request = Application::get()->getRequest();

        return array_unique(
            array_filter([
                $preferredLocale ?? Locale::getLocale(),
                $this->_localesTable[$preferredLocale ?? Locale::getLocale()] ?? null,
                $this->getDefaultLocale(),
                $request->getContext()?->getPrimaryLocale(),
                $request->getSite()->getPrimaryLocale(),
            ])
        );
    }

    /**
     * Get the default locale for object
     */
    public function getDefaultLocale(): ?string
    {
        return null;
    }

    /**
     * Get the value of a data variable.
     */
    public function &getData(string $key, string $locale = null)
    {
        if (is_null($locale)) {
            if (array_key_exists($key, $this->_data)) {
                return $this->_data[$key];
            }
        } elseif (array_key_exists($locale, (array) ($this->_data[$key] ?? []))) {
            return $this->_data[$key][$locale];
        }
        $nullVar = null;
        return $nullVar;
    }

    /**
     * Set the value of a new or existing data variable.
     *
     * @param mixed $value can be either a single value or
     *  an array of of localized values in the form:
     *   array(
     *     'fr_FR' => 'en français',
     *     'en' => 'in English',
     *     ...
     *   )
     * @param $locale (optional) non-null for a single
     *  localized value. Null for a non-localized value or
     *  when setting all locales at once (see comment for
     *  $value parameter)
     */
    public function setData(string $key, mixed $value, ?string $locale = null)
    {
        if (is_null($locale)) {
            // This is either a non-localized value or we're passing in all locales at once.
            $this->_data[$key] = $value;
            return;
        }
        // Set a single localized value.
        if (!is_null($value)) {
            if (isset($this->_data[$key]) && !is_array($this->_data[$key])) {
                $this->_data[$key] = [];
            }
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
     * @param $locale (optional) non-null for a single
     *  localized value. Null for a non-localized value or
     *  when unsetting all locales at once.
     */
    public function unsetData(string $key, ?string $locale = null): void
    {
        if (is_null($locale)) {
            unset($this->_data[$key]);
        } else {
            unset($this->_data[$key][$locale]);
        }
    }

    /**
     * Check whether a value exists for a given data variable.
     */
    public function hasData(string $key, ?string $locale = null): bool
    {
        return is_null($locale) ? array_key_exists($key, $this->_data) : array_key_exists($locale, (array) ($this->_data[$key] ?? []));
    }

    /**
     * Return an array with all data variables.
     */
    public function &getAllData(): array
    {
        return $this->_data;
    }

    /**
     * Set all data variables at once.
     */
    public function setAllData(array $data)
    {
        $this->_data = $data;
    }

    /**
     * Get ID of object.
     */
    public function getId(): ?int
    {
        return $this->getData('id');
    }

    /**
     * Set ID of object.
     */
    public function setId(int $id)
    {
        $this->setData('id', $id);
    }


    //
    // MetadataProvider interface implementation
    //
    /**
     * Set whether the object has loadable meta-data adapters
     */
    public function setHasLoadableAdapters(bool $hasLoadableAdapters)
    {
        $this->_hasLoadableAdapters = $hasLoadableAdapters;
    }

    /**
     * Get whether the object has loadable meta-data adapters
     */
    public function getHasLoadableAdapters(): bool
    {
        return $this->_hasLoadableAdapters;
    }

    /**
     * Add a meta-data adapter that will be supported
     * by this application entity. Only one adapter per schema
     * can be added.
     */
    public function addSupportedMetadataAdapter(MetadataDataObjectAdapter $metadataAdapter): void
    {
        $metadataSchemaName = $metadataAdapter->getMetadataSchemaName();
        if (empty($metadataSchemaName)) {
            throw new Exception('Metadata schema name not specified!');
        }

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
     * @param $metadataSchemaName fully qualified class name
     */
    public function removeSupportedMetadataAdapter(string $metadataSchemaName): bool
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
     */
    public function getSupportedExtractionAdapters(): array
    {
        // Load meta-data adapters from the database.
        if ($this->getHasLoadableAdapters() && !$this->_extractionAdaptersLoaded) {
            $filterDao = DAORegistry::getDAO('FilterDAO'); /** @var FilterDAO $filterDao */
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
     */
    public function getSupportedInjectionAdapters(): array
    {
        // Load meta-data adapters from the database.
        if ($this->getHasLoadableAdapters() && !$this->_injectionAdaptersLoaded) {
            $filterDao = DAORegistry::getDAO('FilterDAO'); /** @var FilterDAO $filterDao */
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
     */
    public function getSupportedMetadataSchemas(): array
    {
        $supportedMetadataSchemas = [];
        $extractionAdapters = $this->getSupportedExtractionAdapters();
        foreach ($extractionAdapters as $metadataAdapter) {
            $supportedMetadataSchemas[] = $metadataAdapter->getMetadataSchema();
        }
        return $supportedMetadataSchemas;
    }

    /**
     * Retrieve the names of meta-data properties of this data object.
     *
     * @param $translated if true, return localized field
     *  names, otherwise return additional field names.
     */
    public function getMetadataFieldNames(bool $translated = true): array
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
     * @param $translated if true, return localized field
     *  names, otherwise return additional field names.
     *
     * @return array an array of field names
     */
    public function getSetMetadataFieldNames(bool $translated = true): array
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
     */
    public function getLocaleMetadataFieldNames(): array
    {
        return $this->getMetadataFieldNames(true);
    }

    /**
     * Retrieve the names of additional meta-data
     * properties that need to be persisted.
     */
    public function getAdditionalMetadataFieldNames(): array
    {
        return $this->getMetadataFieldNames(false);
    }

    /**
     * Inject a meta-data description into this
     * data object.
     *
     * @return bool true on success, otherwise false
     */
    public function injectMetadata(\PKP\metadata\MetadataDescription $metadataDescription): bool
    {
        $dataObject = null;
        $metadataSchemaName = $metadataDescription->getMetadataSchemaName();
        $injectionAdapters = $this->getSupportedInjectionAdapters();
        if (isset($injectionAdapters[$metadataSchemaName])) {
            // Get the meta-data adapter that supports the
            // given meta-data description's schema.
            $metadataAdapter = $injectionAdapters[$metadataSchemaName]; /** @var \PKP\metadata\MetadataDataObjectAdapter $metadataAdapter */

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
     */
    public function extractMetadata(MetadataSchema $metadataSchema): MetadataDescription
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
     * Get DAO class for this object.
     */
    public function getDAO(): \PKP\db\DAO|\PKP\core\EntityDAO
    {
        throw new Exception('Must be implemented by subclass if used');
    }
}
