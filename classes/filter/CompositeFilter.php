<?php
/**
 * @file classes/filter/CompositeFilter.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CompositeFilter
 *
 * @ingroup filter
 *
 * @brief An abstract base class for generic filters that compose other
 *  filters into filter networks.
 */

namespace PKP\filter;

class CompositeFilter extends PersistableFilter
{
    /** @var array An ordered array of sub-filters */
    public $_filters = [];

    /** @var int the max sequence number that has been attributed so far */
    public $_maxSeq = 0;

    /**
     * Constructor
     *
     * @param FilterGroup $filterGroup
     * @param string $displayName
     */
    public function __construct(&$filterGroup, $displayName = null)
    {
        $this->setDisplayName($displayName);
        parent::__construct($filterGroup);
    }

    //
    // Getters and Setters
    //
    /**
     * Adds a filter to the filter list.
     *
     * NB: A filter that is using the sequence number of
     * another filter will not be added.
     *
     * @param Filter $filter
     *
     * @return ?int the filter's sequence number, null
     *  if the sequence number of the filter had already been
     *  set before by a different filter and the filter has
     *  not been added.
     */
    public function addFilter(&$filter)
    {
        assert($filter instanceof \PKP\filter\Filter);

        // Identify an appropriate sequence number.
        $seq = $filter->getSequence();
        if (is_numeric($seq) && $seq > 0) {
            // This filter has a pre-set sequence number
            if (isset($this->_filters[$seq])) {
                return null;
            }
            if ($seq > $this->_maxSeq) {
                $this->_maxSeq = $seq;
            }
        } else {
            // We'll create a sequence number for the filter
            $this->_maxSeq++;
            $seq = $this->_maxSeq;
            $filter->setSequence($seq);
        }

        // Add the filter to the list.
        $this->_filters[$seq] = & $filter;
        return $seq;
    }

    /**
     * Identify a filter by sequence
     * number.
     *
     * @param int $seq
     *
     * @return Filter
     */
    public function &getFilter($seq)
    {
        $filter = null;
        if (isset($this->_filters[$seq])) {
            $filter = & $this->_filters[$seq];
        }
        return $filter;
    }

    /**
     * Gets the array of subfilters that are
     * part of the composite filter.
     */
    public function &getFilters()
    {
        return $this->_filters;
    }

    /**
     * Set the settings mappings
     *
     * @param array $settingsMapping
     *  A settings mapping is of the form:
     *  array(
     *    $settingName => array($targetSetting1, $targetSetting2, ...),
     *    ...
     *  )
     *
     *  $settingName stands for a setting to be used
     *  as a placeholder in the composite filter for the
     *  target settings.
     *
     *  The target settings are of the form "seq_settingName"
     *  whereby "seq" stands for the sequence number of
     *  the target filter and "settingName" for the
     *  corresponding setting there. When you give more than
     *  one target setting then all target settings will be
     *  kept synchronous.
     *
     *  You have to map all sub-filter settings that you
     *  wish to access from the composite filter.
     */
    public function setSettingsMapping($settingsMapping)
    {
        $this->setData('settingsMapping', $settingsMapping);
    }

    /**
     * Get the settings mapping.
     */
    public function getSettingsMapping()
    {
        $settingsMapping = $this->getData('settingsMapping');
        if (is_null($settingsMapping)) {
            return [];
        } else {
            return $settingsMapping;
        }
    }

    /**
     * Get a settings mapping
     *
     * @return array
     */
    public function getSettingsMappingForSetting($settingName)
    {
        $settingsMapping = [];
        $settingsMappingData = $this->getData('settingsMapping');
        if (isset($settingsMappingData[$settingName])) {
            $settingsMapping = $settingsMappingData[$settingName];
        }
        return $settingsMapping;
    }


    //
    // Overridden methods from PersistableFilter
    //
    /**
     * @see PersistableFilter::getSetting()
     */
    public function &getSetting($settingName)
    {
        // Try first whether we have the setting locally.
        if (parent::hasSetting($settingName)) {
            return parent::getSetting($settingName);
        }

        // Otherwise expect a mapped setting.
        return $this->_getSubfilterSetting($settingName);
    }

    /**
     * @see PersistableFilter::getSettings()
     */
    public function &getSettings()
    {
        // Get local settings.
        $settings = parent::getSettings();

        // Get mapped settings.
        foreach ($this->getSettingsMapping() as $settingName => $mappedSetting) {
            $settings[] = $this->_getSubfilterSetting($settingName);
        }

        return $settings;
    }

    /**
     * @see PersistableFilter::hasSettings()
     */
    public function hasSettings()
    {
        // Return true if this filter has own
        // or mapped settings.
        $settingsMapping = $this->getSettingsMapping();
        return (parent::hasSettings() || !empty($settingsMapping));
    }

    /**
     * @see PersistableFilter::getSettingNames()
     */
    public function getSettingNames()
    {
        // Composite filters persist only
        // their own settings. Mapped settings
        // will be persisted in sub-filters.
        // We cannot use the parent implementation
        // here as this would include all sub-
        // filter settings.

        // Initialize with the internal settingsMapping
        // setting.
        $settingNames = ['settingsMapping'];

        // Read only local settings.
        foreach (parent::getSettings() as $setting) {
            if (!$setting->getIsLocalized()) {
                $settingNames[] = $setting->getName();
            }
        }
        return $settingNames;
    }

    /**
     * @see PersistableFilter::getLocalizedSettingNames()
     */
    public function getLocalizedSettingNames()
    {
        // We cannot use the parent implementation
        // here as this would include all sub-
        // filter settings.
        $localizedSettingNames = [];
        foreach (parent::getSettings() as $setting) {
            if ($setting->getIsLocalized()) {
                $localizedSettingNames[] = $setting->getName();
            }
        }
        return $localizedSettingNames;
    }

    /**
     * @see PersistableFilter::getInternalSettings()
     */
    public function getInternalSettings()
    {
        $filterInternalSettings = parent::getInternalSettings();
        $filterInternalSettings[] = 'settingsMapping';
        return $filterInternalSettings;
    }


    //
    // Overridden methods from Filter
    //
    /**
     * @see Filter::isCompatibleWithRuntimeEnvironment()
     */
    public function isCompatibleWithRuntimeEnvironment()
    {
        // Return false if any of the sub-filters is not compatible.
        foreach ($this->getFilters() as $filter) {
            if (!$filter->isCompatibleWithRuntimeEnvironment()) {
                return false;
            }
        }
        return true;
    }


    //
    // Overridden methods from DataObject
    //
    /**
     * @see \PKP\core\DataObject::getData()
     *
     * @param null|mixed $locale
     */
    public function &getData($key, $locale = null)
    {
        // Directly read local settings.
        if (in_array($key, $this->getInternalSettings()) || in_array($key, $this->getSettingNames())) {
            return parent::getData($key, $locale);
        }

        // All other settings will be delegated to sub-filters.
        $compositeSettingName = $this->_getCompositeSettingName($key);
        [$filter, $settingName] = $this->_resolveCompositeSettingName($compositeSettingName);
        return $filter->getData($settingName, $locale);
    }

    /**
     * @see \PKP\core\DataObject::setData()
     *
     * @param null|mixed $locale
     */
    public function setData($key, $value, $locale = null)
    {
        // Directly write internal settings.
        if (is_null($locale)) {
            if (in_array($key, $this->getInternalSettings()) || in_array($key, $this->getSettingNames())) {
                return parent::setData($key, $value);
            }
        } else {
            if (in_array($key, $this->getLocalizedSettingNames())) {
                return parent::setData($key, $value, $locale);
            }
        }

        // All other settings will be delegated to sub-filters.
        $settingsMapping = $this->getSettingsMappingForSetting($key);
        if (!is_array($settingsMapping)) {
            $settingsMapping = [$settingsMapping];
        }
        foreach ($settingsMapping as $compositeSettingName) {
            // Write the setting to the sub-filter.
            [$filter, $settingName] = $this->_resolveCompositeSettingName($compositeSettingName);
            $filter->setData($settingName, $value, $locale);
        }
    }

    /**
     * @see \PKP\core\DataObject::hasData()
     *
     * @param null|mixed $locale
     */
    public function hasData($key, $locale = null)
    {
        // Internal settings will only be checked locally.
        if (in_array($key, $this->getInternalSettings())) {
            return parent::hasData($key);
        }

        // Now try local settings.
        if (parent::hasData($key, $locale)) {
            return true;
        }

        // If nothing is found we try sub-filter settings.
        $compositeSettingName = $this->_getCompositeSettingName($key);
        if (is_null($compositeSettingName)) {
            return false;
        }
        [$filter, $settingName] = $this->_resolveCompositeSettingName($compositeSettingName);
        return $filter->hasData($settingName, $locale);
    }


    //
    // Private helper methods
    //
    /**
     * Get the composite setting name for a
     * mapped setting. If the setting is mapped
     * to several sub-filters then we assume that
     * they are identical and return only the first
     * one.
     *
     * @param string $settingName
     *
     * @return $compositeSettingName string
     */
    public function _getCompositeSettingName($settingName)
    {
        $compositeSettingName = $this->getSettingsMappingForSetting($settingName);
        if (empty($compositeSettingName)) {
            return null;
        }
        if (is_array($compositeSettingName)) {
            $compositeSettingName = $compositeSettingName[0];
        }
        return $compositeSettingName;
    }

    /**
     * Get a setting object from a sub-filter. If
     * the setting mapping points to several sub-filters
     * then we assume that those settings are identical
     * and will return only the first one.
     *
     * @param string $settingName a mapped sub-filter setting
     *
     * @return FilterSetting
     */
    public function &_getSubfilterSetting($settingName)
    {
        // Resolve the setting name and retrieve the setting by name.
        $compositeSettingName = $this->_getCompositeSettingName($settingName);
        [$filter, $settingName] = $this->_resolveCompositeSettingName($compositeSettingName);
        return $filter->getSetting($settingName);
    }

    /**
     * Split a composite setting name and identify the
     * corresponding sub-filter and setting name.
     *
     * @param string $compositeSettingName
     *
     * @return array the first entry will be the sub-filter
     *  and the second entry the setting name.
     */
    public function _resolveCompositeSettingName($compositeSettingName)
    {
        assert(is_string($compositeSettingName));

        // The key should be of the
        // form filterSeq-settingName.
        $compositeSettingNameParts = explode('_', $compositeSettingName, 2);
        if (count($compositeSettingNameParts) != 2) {
            fatalError('Invalid composite setting name "' . $compositeSettingName . '"!');
        }
        [$seq, $settingName] = $compositeSettingNameParts;
        $seq = str_replace('seq', '', $seq);
        if (!is_numeric($seq)) {
            fatalError('Invalid sequence number in "' . $compositeSettingName . '"!');
        }
        $seq = (int)$seq;

        // Identify the sub-filter.
        $filter = & $this->getFilter($seq);
        if (is_null($filter)) {
            fatalError('Invalid filter sequence number!');
        }

        // Return the result.
        return [&$filter, $settingName];
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\filter\CompositeFilter', '\CompositeFilter');
}
