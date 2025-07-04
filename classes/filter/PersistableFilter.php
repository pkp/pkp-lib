<?php

/**
 * @file classes/filter/PersistableFilter.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PersistableFilter
 *
 * @ingroup filter
 *
 * @see FilterGroup
 * @see FilterSetting
 *
 * @brief A filter that can be persisted to the database.
 *
 * Persisted filters are attributed to a filter group so that all filters
 * of the same kind can be discovered from the database.
 *
 * Persisted filters also can provide a list of FilterSetting objects which
 * represent persistable filter configuration parameters.
 *
 * Persisted filters are templatable. This means that a non-parameterized
 * copy of the filter can be persisted as a template for actual filter
 * instances. The end user can discover such templates and use them to
 * configure personalized transformations.
 *
 * The filter template can provide default settings for all filter
 * settings.
 *
 * Persistable filters can be accessed via the FilterDAO which acts as a
 * filter registry.
 *
 * Filters can be organized hierarchically into filter networks or
 * filter pipelines. The hierarchical relation is represented via parent-
 * child relationships. See CompositeFilter for more details.
 */

namespace PKP\filter;

define('FILTER_GROUP_TEMPORARY_ONLY', '$$$temporary$$$');

abstract class PersistableFilter extends Filter
{
    /** @var FilterGroup */
    public $_filterGroup;

    /** @var array a list of FilterSetting objects */
    public $_settings = [];

    /**
     * Constructor
     *
     * NB: Sub-classes of this class must not add additional
     * mandatory constructor arguments. Sub-classes that implement
     * additional optional constructor arguments must make these
     * also accessible via setters if they are required to fully
     * parameterize the transformation. Filter parameters must be
     * stored as data in the underlying \PKP\core\DataObject.
     *
     * This is necessary as the FilterDAO does not support
     * constructor configuration. Filter parameters will be
     * configured via DataObject::setData(). Only parameters
     * that are available in the DataObject will be persisted.
     *
     * @param FilterGroup $filterGroup
     */
    public function __construct($filterGroup)
    {
        // Check and set the filter group.
        assert($filterGroup instanceof \PKP\filter\FilterGroup);
        $this->_filterGroup = $filterGroup;

        // Initialize the filter.
        $this->setParentFilterId(null);
        $this->setIsTemplate(false);
        parent::__construct($filterGroup->getInputType(), $filterGroup->getOutputType());
    }


    //
    // Setters and Getters
    //
    /**
     * Get the filter group
     *
     * @return FilterGroup
     */
    public function getFilterGroup()
    {
        return $this->_filterGroup;
    }

    /**
     * Set whether this is a transformation template
     * rather than an actual transformation.
     *
     * Transformation templates are saved to the database
     * when the filter is first registered. They are
     * configured with default settings and will be used
     * to let users identify available transformation
     * types.
     *
     * There must be exactly one transformation template
     * for each supported filter group.
     *
     * @param bool $isTemplate
     */
    public function setIsTemplate($isTemplate)
    {
        $this->setData('isTemplate', (bool)$isTemplate);
    }

    /**
     * Is this a transformation template rather than
     * an actual transformation?
     *
     * @return bool
     */
    public function getIsTemplate()
    {
        return $this->getData('isTemplate');
    }

    /**
     * Set the parent filter id
     */
    public function setParentFilterId(?int $parentFilterId): void
    {
        $this->setData('parentFilterId', $parentFilterId);
    }

    /**
     * Get the parent filter id
     */
    public function getParentFilterId(): ?int
    {
        return $this->getData('parentFilterId');
    }

    /**
     * Add a filter setting
     *
     * @param FilterSetting $setting
     */
    public function addSetting($setting)
    {
        assert($setting instanceof \PKP\filter\FilterSetting);
        $settingName = $setting->getName();

        // Check that the setting name does not
        // collide with one of the internal settings.
        if (in_array($settingName, $this->getInternalSettings())) {
            throw new \Exception('Trying to override an internal filter setting!');
        }

        assert(!isset($this->_settings[$settingName]));
        $this->_settings[$settingName] = $setting;
    }

    /**
     * Get a filter setting
     */
    public function getSetting(string $settingName): FilterSetting
    {
        return $this->_settings[$settingName];
    }

    /**
     * Get all filter settings
     *
     * @return array a list of FilterSetting objects
     */
    public function &getSettings()
    {
        return $this->_settings;
    }

    /**
     * Check whether a given setting
     * is present in this filter.
     */
    public function hasSetting($settingName)
    {
        return isset($this->_settings[$settingName]);
    }

    /**
     * Can this filter be parameterized?
     */
    public function hasSettings(): bool
    {
        return count($this->_settings);
    }

    /**
     * Return the fully qualified class name of the filter class. This
     * information must be persisted when saving a filter so that the
     * filter can later be reconstructed from the information in the
     * database.
     */
    public function getClassName(): string
    {
        return static::class;
    }

    //
    // Public methods
    //
    /**
     * Return an array with the names of non-localized
     * filter settings.
     *
     * This will be used by the FilterDAO for filter
     * setting persistence.
     *
     * @return array
     */
    public function getSettingNames()
    {
        $settingNames = [];
        foreach ($this->getSettings() as $setting) { /** @var FilterSetting $setting */
            if (!$setting->getIsLocalized()) {
                $settingNames[] = $setting->getName();
            }
        }
        return $settingNames;
    }

    /**
     * Return an array with the names of localized
     * filter settings.
     *
     * This will be used by the FilterDAO for filter
     * setting persistence.
     *
     * @return array
     */
    public function getLocalizedSettingNames()
    { /** @var FilterSetting $setting */
        $localizedSettingNames = [];
        foreach ($this->getSettings() as $setting) {
            if ($setting->getIsLocalized()) {
                $localizedSettingNames[] = $setting->getName();
            }
        }
        return $localizedSettingNames;
    }


    //
    // Public static helper methods
    //
    /**
     * There are certain generic filters (e.g. CompositeFilters)
     * that sometimes need to be persisted and sometimes are
     * instantiated and used in code only.
     *
     * As we don't have multiple inheritance in PHP we'll have
     * to use the more general filter type (PersistableFilter) as
     * the base class of these "hybrid" filters.
     *
     * This means that we carry around some structure (e.g. filter
     * groups) that do only make sense when a filter is actually
     * being persisted and otherwise create unnecessary code.
     *
     * We provide this helper function to instantiate a temporary
     * filter group on the fly with only an input and an output type
     * which takes away at least some of the cruft.
     *
     * @param string $inputType
     * @param string $outputType
     */
    public static function tempGroup($inputType, $outputType)
    {
        $temporaryGroup = new FilterGroup();
        $temporaryGroup->setSymbolic(FILTER_GROUP_TEMPORARY_ONLY);
        $temporaryGroup->setInputType($inputType);
        $temporaryGroup->setOutputType($outputType);
        return $temporaryGroup;
    }


    //
    // Protected helper methods
    //
    /**
     * Returns names of settings which are in use by the
     * filter class and therefore cannot be set as filter
     * settings.
     *
     * @return array
     */
    public function getInternalSettings()
    {
        return ['id', 'displayName', 'isTemplate', 'parentFilterId', 'seq'];
    }
}
