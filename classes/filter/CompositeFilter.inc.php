<?php
/**
 * @file classes/filter/CompositeFilter.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CompositeFilter
 * @ingroup filter
 *
 * @brief An abstract base class for generic filters that compose other
 *  filters into filter networks.
 */

import('lib.pkp.classes.filter.GenericFilter');

class CompositeFilter extends GenericFilter {
	/** @var array An ordered array of sub-filters */
	var $_filters = array();

	/** @var integer the max sequence number that has been attributed so far */
	var $_maxSeq = 0;

	/**
	 * Constructor
	 */
	function CompositeFilter($displayName = null, $transformation = null) {
		parent::GenericFilter($displayName, $transformation);
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
	 * @param $filter Filter
	 * @param $settingsMapping array
	 *  A settings mapping is of the form:
	 *  array(
	 *    $settingName => array($sourceFilterSeq, $sourceSettingName),
	 *    ...
	 *  )
	 *
	 *  A settings mapping means that the given setting
	 *  will no longer be independent. It will be directly
	 *  linked to the source setting. Whenever the source
	 *  setting is changed, the given setting will
	 *  automatically change as well.
	 *
	 *  The setting will also not have its own FilterSetting
	 *  entry when calling getSettings() on a CompositeFilter.
	 *  It will effectively disappear from the interface to
	 *  make sure that users don't have to fill in the same
	 *  setting twice if it applies to several sub-filters
	 *  in the exact same way.
	 *
	 *  Internally the two settings will be kept separately,
	 *  to make sure that we can use the default persistence
	 *  and filter logic.
	 *
	 *  NB: The target filter must be added before you
	 *  can map a setting to it.
	 *
	 * @return integer the filter's sequence number, null
	 *  if the sequence number of the filter had already been
	 *  set before by a different filter.
	 */
	function addFilter(&$filter, $settingsMapping = array()) {
		// Add the filter to the ordered sub-filter list.
		assert(is_a($filter, 'Filter'));
		$seq = $filter->getSeq();
		if (is_numeric($seq) && $seq > 0) {
			// This filter has a pre-set sequence number
			if (isset($this->_filters[$seq])) return null;
			if ($seq > $this->_maxSeq) $this->_maxSeq = $seq;
		} else {
			// We'll create a sequence number for the filter
			$this->_maxSeq++;
			$seq = $this->_maxSeq;
			$filter->setSeq($seq);
		}
		$this->_filters[$seq] =& $filter;

		// Add the filter settings to the composite filter.
		$subFilterSettings =& $filter->getSettings();
		foreach($subFilterSettings as $subFilterSetting) {
			// Make the setting name unique.
			$subFilterSettingName = $subFilterSetting->getName();
			$compositeSettingName = 'seq'.$seq.'_'.$subFilterSettingName;

			// Is the setting mapped to another setting?
			if (isset($settingsMapping[$subFilterSettingName])) {
				// Don't set the setting but merely map it
				// to the source which will override it.
				$sourceSetting = $settingsMapping[$subFilterSettingName];
				assert(is_array($sourceSetting) && count($sourceSetting) == 2);
				list($sourceSeq, $sourceSettingName) = $settingsMapping[$subFilterSettingName];
				$compositeSourceSettingName = 'seq'.$sourceSeq.'_'.$sourceSettingName;

				$settingsMappingData = $this->getData('settingsMapping');
				if (!isset($settingsMappingData[$compositeSourceSettingName])) {
					$settingsMappingData[$compositeSourceSettingName] = array();
				}
				if (!in_array($compositeSettingName, $settingsMappingData[$compositeSourceSettingName])) {
					$settingsMappingData[$compositeSourceSettingName][] = $compositeSettingName;
				}
				$this->setData('settingsMapping', $settingsMappingData);
			} else {
				// Only add the setting as composite setting if it is not mapped.
				$setting =& cloneObject($subFilterSetting);
				$setting->setName($compositeSettingName);
				parent::addSetting($setting);
				unset($setting);
			}
		}

		return $seq;
	}

	/**
	 * Identify a filter by sequence
	 * number.
	 * @param $seq integer
	 * @return Filter
	 */
	function &getFilter($seq) {
		$filter = null;
		if (isset($this->_filters[$seq])) {
			$filter =& $this->_filters[$seq];
		}
		return $filter;
	}

	/**
	 * Gets the array of subfilters that are
	 * part of the composite filter.
	 */
	function &getFilters() {
		return $this->_filters;
	}

	/**
	 * Get the settings mappings
	 * @return array
	 */
	function getSettingsMapping($settingName) {
		$settingsMapping = array();
		$settingsMappingData = $this->getData('settingsMapping');
		if (isset($settingsMappingData[$settingName])) {
			$settingsMapping = $settingsMappingData[$settingName];
		}
		return $settingsMapping;
	}


	//
	// Overridden methods from Filter
	//
	/**
	 * @see Filter::addSetting()
	 */
	function addSetting(&$setting) {
		// Composite filters have read only settings
		// imported from sub-filters. Trying to set
		// an additional setting is not supported.
		assert(false);
	}

	/**
	 * @see Filter::hasSettings()
	 */
	function hasSettings() {
		// If any of the sub-filters has settings
		// then return true.
		foreach($this->getFilters() as $filter) {
			if ($filter->hasSettings()) return true;
		}
		return false;
	}

	/**
	 * @see Filter::getSettingNames()
	 */
	function getSettingNames() {
		// Composite filters never persist
		// settings of their own except for
		// their internal settings mapping.
		$settingNames = array('settingsMapping');
		return $settingNames;
	}

	/**
	 * @see Filter::getLocalizedSettingNames()
	 */
	function getLocalizedSettingNames() {
		// Composite filters never persist
		// settings of their own
		$localizedSettingNames = array();
		return $localizedSettingNames;
	}

	/**
	 * @see Filter::isCompatibleWithRuntimeEnvironment()
	 */
	function isCompatibleWithRuntimeEnvironment() {
		// Return false if any of the sub-filters is not compatible.
		foreach ($this->getFilters() as $filter) {
			if (!$filter->isCompatibleWithRuntimeEnvironment()) return false;
		}
		return true;
	}

	/**
	 * @see Filter::getInternalSettings()
	 */
	function getInternalSettings() {
		$filterInternalSettings = parent::getInternalSettings();
		$filterInternalSettings[] = 'settingsMapping';
		return $filterInternalSettings;
	}

	//
	// Overridden methods from DataObject
	//
	/**
	 * @see DataObject::getData()
	 */
	function getData($key, $locale = null) {
		// Directly read internal settings.
		if (in_array($key, $this->getInternalSettings())) return parent::getData($key, $locale);

		// All other settings will be delegated to sub-filters.
		list($filter, $settingName) = $this->_resolveDataKey($key);
		return $filter->getData($settingName, $locale);
	}

	/**
	 * @see DataObject::setData()
	 */
	function setData($key, $value, $locale = null) {
		static $lockedKeys = array();

		// Directly write internal settings.
		if (in_array($key, $this->getInternalSettings())) return parent::setData($key, $value, $locale);

		// All other settings will be delegated to sub-filters.

		// Check whether the key is already locked (loop detection).
		if (isset($lockedKeys[$key])) fatalError('Detected a settings mapping loop for key "'.$key.'"!');

		// Lock the key.
		$lockedKeys[$key] = true;

		// If this setting is a source for other settings then
		// recursively set the target settings to the same value.
		foreach($this->getSettingsMapping($key) as $targetSetting) $this->setData($targetSetting, $value, $locale);

		// Release the key.
		unset($lockedKeys[$key]);

		// Write the setting to the sub-filter.
		list($filter, $settingName) = $this->_resolveDataKey($key);
		return $filter->setData($settingName, $value, $locale);
	}

	/**
	 * @see DataObject::setData()
	 */
	function hasData($key, $locale = null) {
		// Keys that start with "seq" will be delegated to sub-filters.
		if (substr($key, 0, 3) == 'seq') {
			// Identify the filter sequence number
			$keyParts = explode('_', $key, 2);
			if (count($keyParts) != 2) return false;
			list($seq, $settingName) = $keyParts;
			$seq = str_replace('seq', '', $seq);
			if (!is_numeric($seq)) return false;
			$seq = (integer)$seq;

			// Identify the sub-filter.
			$filter =& $this->getFilter($seq);
			if (is_null($filter)) return false;

			// Delegate to the sub-filter
			return $filter->hasData($settingName, $locale);
		}

		// Directly check all other settings.
		if (in_array($key, $this->getInternalSettings())) return parent::hasData($key, $locale);
	}

	//
	// Private helper methods
	//
	/**
	 * Split a composite setting key and identify the
	 * corresponding sub-filter and setting name.
	 * @param $key string
	 * @return array the first entry will be the sub-filter
	 *  and the second entry the setting name.
	 */
	function _resolveDataKey($key) {
		// The key should be of the
		// form filterSeq-settingName.
		$keyParts = explode('_', $key, 2);
		if (count($keyParts) != 2) fatalError('Invalid setting name "'.$key.'"!');
		list($seq, $settingName) = $keyParts;
		$seq = str_replace('seq', '', $seq);
		if (!is_numeric($seq)) fatalError('Invalid sequence number in "'.$key.'"!');
		$seq = (integer)$seq;

		// Identify the sub-filter.
		$filter =& $this->getFilter($seq);
		if (is_null($filter)) fatalError('Invalid filter sequence number!');

		// Return the result.
		return array(&$filter, $settingName);
	}
}
?>
