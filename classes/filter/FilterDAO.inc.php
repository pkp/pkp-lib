<?php

/**
 * @file FilterDAO.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FilterDAO
 * @ingroup filter
 * @see Filter
 *
 * @brief Operations for retrieving and modifying Filter objects.
 *
 * The filter DAO acts as a filter registry. It allows filter providers to
 * register transformations and filter consumers to identify available
 * transformations that convert a given input type into a required output type.
 *
 * Transformations are defined as a combination of a filter class and a pair of
 * input/output type specifications supported by that filter implementation.
 *
 * The following additional conditions apply:
 * 1) A single filter may implement several transformations, i.e. distinct
 *    combinations of input and output types. Therefore the filter DAO
 *    must be able to check all available transformations against a given input
 *    type and an expected output type and select those filters instances that
 *    support compatible transformations.
 * 2) The DAO must take care to only select such transformations that are
 *    supported by the current runtime environment.
 * 3) The DAO implementation must be fast and memory efficient.
 */

import('lib.pkp.classes.filter.Filter');

class FilterDAO extends DAO {
	/** @var array names of additional settings for the currently persisted/retrieved filter */
	var $additionalFieldNames;

	/** @var array names of localized settings for the currently persisted/retrieved filter */
	var $localeFieldNames;

	/**
	 * Insert a new filter instance (transformation).
	 *
	 * @param $filter Filter The configured filter instance to be persisted
	 * @return integer the new filter id
	 */
	function insertObject(&$filter) {
		$inputType = $filter->getInputType();
		$outputType = $filter->getOutputType();

		$this->update(
			sprintf('INSERT INTO filters
				(display_name, class_name, input_type, output_type, is_template, parent_filter_id, seq)
				VALUES (?, ?, ?, ?, ?, ?, ?)'),
			array(
				$filter->getDisplayName(),
				$filter->getClassName(),
				$inputType->getTypeDescription(),
				$outputType->getTypeDescription(),
				$filter->getIsTemplate()?1:0,
				(integer)$filter->getParentFilterId(),
				(integer)$filter->getSeq()
			)
		);
		$filter->setId((int)$this->getInsertId());
		$this->updateDataObjectSettings('filter_settings', $filter,
				array('filter_id' => $filter->getId()));

		// Recursively insert sub-filters.
		$this->_insertSubFilters($filter);

		return $filter->getId();
	}

	/**
	 * Retrieve a configured filter instance (transformation)
	 * @param $filter Filter
	 * @return Filter
	 */
	function &getObject(&$filter) {
		return $this->getObjectById($filter->getId());
	}

	/**
	 * Retrieve a configured filter instance (transformation) by id.
	 * @param $filterId integer
	 * @param $allowSubfilter boolean
	 * @return Filter
	 */
	function &getObjectById($filterId, $allowSubfilter = false) {
		$result =& $this->retrieve(
				'SELECT * FROM filters'.
				' WHERE '.($allowSubfilter ? '' : 'parent_filter_id = 0 AND').
				' filter_id = ?', $filterId);

		$filter = null;
		if ($result->RecordCount() != 0) {
			$filter =& $this->_fromRow($result->GetRowAssoc(false));
		}

		$result->Close();
		unset($result);

		return $filter;
	}

	/**
	 * Retrieve a result set with all filter instances
	 * (transformations) that are based on the given class.
	 * @param $className string
	 * @param $getTemplates boolean set true if you want filter templates
	 *  rather than actual transformations
	 * @param $allowSubfilters boolean
	 * @return DAOResultFactory
	 */
	function &getObjectsByClass($className, $getTemplates = false, $allowSubfilters = false) {
		$result =& $this->retrieve(
				'SELECT * FROM filters WHERE class_name = ?'.
				' '.($allowSubfilters ? '' : 'AND parent_filter_id = 0').
				' AND '.($getTemplates ? '' : 'NOT ').'is_template',
				$className);

		$daoResultFactory = new DAOResultFactory($result, $this, '_fromRow', array('filter_id'));
		return $daoResultFactory;
	}

	/**
	 * Retrieve filter instances that support a given input and
	 * output sample object.
	 *
	 * The selection of filters that are compatible with the
	 * given input and output samples is based on their type
	 * description.
	 *
	 * @param $inputSample mixed
	 * @param $outputSample mixed
	 * @param $getTemplates boolean set true if you want filter templates
	 *  rather than actual transformations
	 * @param $rehash boolean if true then the (costly) filter
	 *  hash operation will be repeated even if the filters have
	 *  been hashed before.
	 * @return array all compatible filter instances (transformations).
	 */
	function &getCompatibleObjects($inputSample, $outputSample, $getTemplates = false, $rehash = false) {
		static $filterHash = array();
		static $typeDescriptionFactory = null;
		static $typeDescriptionCache = array();
		static $filterInstanceCache = array();

		// Instantiate the type description factory
		if (is_null($typeDescriptionFactory)) {
			$typeDescriptionFactory =& TypeDescriptionFactory::getInstance();
		}

		// 1) Hash all available transformations by input
		//    and output type.
		if (!isset($filterHash[$getTemplates]) || $rehash) {
			$result =& $this->retrieve(
				'SELECT * FROM filters'.
				' WHERE '.($getTemplates ? '' : 'NOT ').'is_template'.
				' AND parent_filter_id = 0');
			foreach($result->GetAssoc() as $filterRow) {
				$filterHash[$getTemplates][$filterRow['input_type']][$filterRow['output_type']][] = $filterRow;
			}

			// Set an empty array if no filters were found at all.
			if (!isset($filterHash[$getTemplates])) $filterHash[$getTemplates] = array();
		}

		// 2) Check the input sample against all input types.
		$intermediateCandidates = array();
		foreach($filterHash[$getTemplates] as $inputType => $outputHash) {
			// Instantiate the type description if not yet done
			// before.
			if (!isset($typeDescriptionCache[$inputType])) {
				$typeDescriptionCache[$inputType] =& $typeDescriptionFactory->instantiateTypeDescription($inputType);
			}

			// 3) Whenever an input type matches, hash all filters
			//    with this input type by output type.
			if ($typeDescriptionCache[$inputType]->checkType($inputSample)) {
				foreach($outputHash as $outputType => $filterRows) {
					if (!isset($intermediateCandidates[$outputType])) {
						$intermediateCandidates[$outputType] = $filterRows;
					} else {
						$intermediateCandidates[$outputType] = array_merge($intermediateCandidates[$outputType], $filterRows);
					}
				}
			}
		}

		// 4) Check the output sample against all hashed output
		//    types.
		$matchingFilterRows = array();
		foreach ($intermediateCandidates as $outputType => $filterRows) {
			// Instantiate the type description if not yet done
			// before.
			if (!isset($typeDescriptionCache[$outputType])) {
				$typeDescriptionCache[$outputType] =& $typeDescriptionFactory->instantiateTypeDescription($outputType);
			}

			// 5) Whenever an output type matches, add all filters
			//    with this output type to the result set.
			if ($typeDescriptionCache[$outputType]->checkType($outputSample)) {
				$matchingFilterRows = array_merge($matchingFilterRows, $filterRows);
			}
		}

		// 6) Instantiate and return all transformations in the
		//    result set that comply with the current runtime
		//    environment.
		$matchingFilters = array();
		$runTimeRequirementNotMet = -1;
		foreach($matchingFilterRows as $matchingFilterRow) {
			if (!isset($filterInstanceCache[$matchingFilterRow['filter_id']])) {
				$filterInstance =& $this->_fromRow($matchingFilterRow);
				if ($filterInstance->isCompatibleWithRuntimeEnvironment()) {
					$filterInstanceCache[$matchingFilterRow['filter_id']] =& $filterInstance;
				} else {
					$filterInstanceCache[$matchingFilterRow['filter_id']] = $runTimeRequirementNotMet;
				}
				unset($filterInstance);
			}
			if ($filterInstanceCache[$matchingFilterRow['filter_id']] !== $runTimeRequirementNotMet) {
				$matchingFilters[$matchingFilterRow['filter_id']] = $filterInstanceCache[$matchingFilterRow['filter_id']];
			}
		}

		return $matchingFilters;
	}

	/**
	 * Update an existing filter instance (transformation).
	 * @param $filter Filter
	 */
	function updateObject(&$filter) {
		$inputType = $filter->getInputType();
		$outputType = $filter->getOutputType();

		$returner = $this->update(
			'UPDATE	filters
			SET	display_name = ?,
				class_name = ?,
				input_type = ?,
				output_type = ?,
				is_template = ?,
				parent_filter_id = ?,
				seq = ?
			WHERE filter_id = ?',
			array(
				$filter->getDisplayName(),
				$filter->getClassName(),
				$inputType->getTypeDescription(),
				$outputType->getTypeDescription(),
				$filter->getIsTemplate()?1:0,
				(integer)$filter->getParentFilterId(),
				(integer)$filter->getSeq(),
				(integer)$filter->getId()
			)
		);
		$this->updateDataObjectSettings('filter_settings', $filter,
				array('filter_id' => $filter->getId()));

		// Do we update a composite filter?
		if (is_a($filter, 'CompositeFilter')) {
			// Delete all sub-filters
			$this->_deleteSubFiltersByParentFilterId($filter->getId());

			// Re-insert sub-filters
			$this->_insertSubFilters($filter);
		}
	}

	/**
	 * Delete a filter instance (transformation).
	 * @param $filter Filter
	 * @return boolean
	 */
	function deleteObject(&$filter) {
		return $this->deleteObjectById($filter->getId());
	}

	/**
	 * Delete a filter instance (transformation) by id.
	 * @param $filterId int
	 * @return boolean
	 */
	function deleteObjectById($filterId) {
		$filterId = (int)$filterId;
		$this->update('DELETE FROM filters WHERE filter_id = ?', $filterId);
		$this->update('DELETE FROM filter_settings WHERE filter_id = ?', $filterId);
		$this->_deleteSubFiltersByParentFilterId($filterId);
	}


	//
	// Overridden methods from DAO
	//
	/**
	 * @see DAO::updateDataObjectSettings()
	 */
	function updateDataObjectSettings($tableName, &$dataObject, $idArray) {
		// Make sure that the update function finds the filter settings
		$this->additionalFieldNames = $dataObject->getSettingNames();
		$this->localeFieldNames = $dataObject->getLocalizedSettingNames();

		// Add runtime settings
		foreach($dataObject->supportedRuntimeEnvironmentSettings() as $runtimeSetting => $defaultValue) {
			if ($dataObject->hasData($runtimeSetting)) $this->additionalFieldNames[] = $runtimeSetting;
		}

		// Update the filter settings
		parent::updateDataObjectSettings($tableName, $dataObject, $idArray);

		// Reset the internal fields
		unset($this->additionalFieldNames, $this->localeFieldNames);
	}


	//
	// Implement template methods from DAO
	//
	/**
	 * @see DAO::getAdditionalFieldNames()
	 */
	function getAdditionalFieldNames() {
		assert(is_array($this->additionalFieldNames));
		return $this->additionalFieldNames;
	}

	/**
	 * @see DAO::getLocaleFieldNames()
	 */
	function getLocaleFieldNames() {
		assert(is_array($this->localeFieldNames));
		return $this->localeFieldNames;
	}


	//
	// Protected helper methods
	//
	/**
	 * Get the ID of the last inserted Source Description.
	 * @return int
	 */
	function getInsertId() {
		return parent::getInsertId('filters', 'filter_id');
	}


	//
	// Private helper methods
	//
	/**
	 * Construct a new configured filter instance (transformation).
	 * @param $filterClassName string a fully qualified class name
	 * @param $inputType string
	 * @param $outputType string
	 * @return Filter
	 */
	function &_newDataObject($filterClassName, $inputType, $outputType) {
		// Instantiate the filter
		$filter =& instantiate($filterClassName, 'Filter');
		if (!is_object($filter)) fatalError('Error while instantiating class "'.$filterClassName.'" as filter!');

		// Set input/output data types (transformation type).
		// NB: This will raise a fatal error if the transformation is not
		// supported by this filter.
 		$filter->setTransformationType($inputType, $outputType);

		return $filter;
	}

	/**
	 * Internal function to return a filter instance (transformation)
	 * object from a row.
	 *
	 * @param $row array
	 * @return Filter
	 */
	function &_fromRow(&$row) {
		static $lockedFilters = array();
		$filterId = $row['filter_id'];

		// Check the filter lock (to detect loops).
		// NB: This is very important otherwise the request
		// could eat up lots of memory if the PHP memory max was
		// set too high.
		if (isset($lockedFilters[$filterId])) fatalError('Detected a loop in the definition of the filter with id '.$filterId.'!');

		// Lock the filter id.
		$lockedFilters[$filterId] = true;

		// Instantiate the filter.
		$filter =& $this->_newDataObject($row['class_name'], $row['input_type'], $row['output_type']);

		// Configure the filter instance
		$filter->setId((int)$row['filter_id']);
		$filter->setDisplayName($row['display_name']);
		$filter->setIsTemplate((boolean)$row['is_template']);
		$filter->setParentFilterId((int)$row['parent_filter_id']);
		$filter->setSeq((int)$row['seq']);
		$this->getDataObjectSettings('filter_settings', 'filter_id', $row['filter_id'], $filter);

		// Recursively retrieve sub-filters of this filter.
		$this->_populateSubFilters($filter);

		// Release the lock on the filter id.
		unset($lockedFilters[$filterId]);

		return $filter;
	}

	/**
	 * Populate the sub-filters (if any) for the
	 * given parent filter.
	 * @param $parentFilter Filter
	 */
	function _populateSubFilters(&$parentFilter) {
		if (!is_a($parentFilter, 'CompositeFilter')) {
			// Nothing to do. Only composite filters
			// can have sub-filters.
			return;
		}

		// Retrieve the sub-filters from the database.
		$parentFilterId = $parentFilter->getId();
		$result =& $this->retrieve(
				'SELECT * FROM filters WHERE parent_filter_id = ? ORDER BY seq', $parentFilterId);
		$daoResultFactory = new DAOResultFactory($result, $this, '_fromRow', array('filter_id'));

		// Build a reverse settings mapping: The orignal settings
		// mapping points from the source to the target. Now
		// we need to identify the targets per sub-filter so that
		// we can configure the mappings when populating the filter
		// with sub-filters.
		$reverseSettingsMapping = array();
		if ($parentFilter->hasData('settingsMapping')) {
			$settingsMapping = $parentFilter->getData('settingsMapping');
			assert(is_array($settingsMapping));
			foreach ($settingsMapping as $compositeSourceSettingName => $compositeTargetSettingNames) {
				// Identify the source filter sequence and setting name.
				list($sourceFilterSeq, $sourceSettingName) = $this->_parseCompositeSettingName($compositeSourceSettingName);

				// A source can be copied to several targets.
				assert(is_array($compositeTargetSettingNames));
				foreach($compositeTargetSettingNames as $compositeTargetSettingName) {
					// Identify the target filter sequence and setting name.
					list($targetFilterSeq, $targetSettingName) = $this->_parseCompositeSettingName($compositeTargetSettingName);

					// Build the reverse settings mapping.
					assert(!isset($reverseSettingsMapping[$targetFilterSeq][$targetSettingName]));
					$reverseSettingsMapping[$targetFilterSeq][$targetSettingName] = array($sourceFilterSeq, $sourceSettingName);
				}
			}
		}

		// Add sub-filters.
		while (!$daoResultFactory->eof()) {
			// Retrieve the sub filter.
			// NB: This recursively loads sub-filters
			// of this filter via _fromRow().
			$subFilter =& $daoResultFactory->next();

			// Is there a settings mapping to be set?
			if (isset($reverseSettingsMapping[$subFilter->getSeq()])) {
				$settingsMapping = $reverseSettingsMapping[$subFilter->getSeq()];
			} else {
				$settingsMapping = array();
			}

			// Add the sub-filter to the filter list
			// of its parent filter.
			$parentFilter->addFilter($subFilter, $settingsMapping);

			unset($subFilter);
		}
	}

	/**
	 * Recursively insert sub-filters of
	 * the given parent filter.
	 * @param $parentFilter Filter
	 */
	function _insertSubFilters(&$parentFilter) {
		if (!is_a($parentFilter, 'CompositeFilter')) {
			// Nothing to do. Only composite filters
			// can have sub-filters.
			return;
		}

		// Recursively insert sub-filters
		foreach($parentFilter->getFilters() as $subFilter) {
			$subFilter->setParentFilterId($parentFilter->getId());
			$subfilterId = $this->insertObject($subFilter);
			assert(is_numeric($subfilterId));
		}
	}

	/**
	 * Recursively delete all sub-filters for
	 * the given parent filter.
	 * @param $parentFilterId integer
	 */
	function _deleteSubFiltersByParentFilterId(&$parentFilterId) {
		$parentFilterId = (int)$parentFilterId;

		// Identify sub-filters.
		$result =& $this->retrieve(
				'SELECT * FROM filters WHERE parent_filter_id = ?', $parentFilterId);

		$allSubFilterRows = $result->GetArray();
		foreach($allSubFilterRows as $subFilterRow) {
			// Delete sub-filters
			// NB: We need to do this before we delete
			// sub-sub-filters to avoid loops.
			$subFilterId = $subFilterRow['filter_id'];
			$this->deleteObjectById($subFilterId);;

			// Recursively delete sub-sub-filters.
			$this->_deleteSubFiltersByParentFilterId($subFilterId);
		}
	}

	/**
	 * Parse a composite setting name of the form
	 * seqX_someName, whereby 'X' is a filter sequence
	 * number and 'someName' a setting name.
	 *
	 * @param $compositeSettingName string
	 * @return array the sequence number as first entry
	 *  and the setting name as second.
	 */
	function _parseCompositeSettingName($compositeSettingName) {
		$settingParts = explode('_', $compositeSettingName);
		assert(count($settingParts) == 2);
		list($filterSeq, $settingName) = $settingParts;
		$filterSeq = str_replace('seq', '', $filterSeq);
		assert(is_numeric($filterSeq));
		$filterSeq = (int) $filterSeq;
		return array($filterSeq, $settingName);
	}
}

?>
