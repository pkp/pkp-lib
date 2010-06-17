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
				(display_name, seq, class_name, input_type, output_type)
				VALUES (?, ?, ?, ?, ?)'),
			array(
				$filter->getDisplayName(),
				(integer)$filter->getSeq(),
				$filter->getClassName(),
				$inputType->getTypeDescription(),
				$outputType->getTypeDescription()
			)
		);
		$filter->setId($this->getInsertId());
		$this->updateDataObjectSettings('filter_settings', $filter,
				array('filter_id' => $filter->getId()));
		return $filter->getId();
	}

	/**
	 * Retrieve a configured filter instance (transformation) by id.
	 * @param $filterId integer
	 * @return Filter
	 */
	function &getObjectById($filterId) {
		$result =& $this->retrieve(
			'SELECT * FROM filters WHERE filter_id = ?', $filterId
		);

		$filter = null;
		if ($result->RecordCount() != 0) {
			$filter =& $this->_fromRow($result->GetRowAssoc(false));
		}

		$result->Close();
		unset($result);

		return $filter;
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
	 * @param $rehash boolean if true then the (costly) filter
	 *  hash operation will be repeated even if the filters have
	 *  been hashed before.
	 * @return array all compatible filter instances (transformations).
	 */
	function &getCompatibleObjects($inputSample, $outputSample, $rehash = false) {
		static $filterHash = array();
		static $typeDescriptionFactory = null;
		static $typeDescriptionCache = array();
		static $filterInstanceCache = array();

		// Instantiate the type description factory
		if (is_null($typeDescriptionFactory)) {
			$typeDescriptionFactory = new TypeDescriptionFactory();
		}

		// 1) Hash all available transformations by input
		//    and output type.
		if (empty($filterHash) || $rehash) {
			$result =& $this->retrieve('SELECT * FROM filters');
			foreach($result->GetAssoc() as $filterRow) {
				$filterHash[$filterRow['input_type']][$filterRow['output_type']][] = $filterRow;
			}
		}

		// 2) Check the input sample against all input types.
		$intermediateCandidates = array();
		foreach($filterHash as $inputType => $outputHash) {
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
				$runtimeEnvironment = $filterInstance->getRuntimeEnvironment();
				if (!is_null($runtimeEnvironment) && !$runtimeEnvironment->isCompatible()) {
					$filterInstanceCache[$matchingFilterRow['filter_id']] = $runTimeRequirementNotMet;
				} else {
					$filterInstanceCache[$matchingFilterRow['filter_id']] =& $filterInstance;
				}
				unset($filterInstance);
			}
			if ($filterInstanceCache[$matchingFilterRow['filter_id']] !== $runTimeRequirementNotMet) {
				$matchingFilters[] = $filterInstanceCache[$matchingFilterRow['filter_id']];
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
				seq = ?,
				class_name = ?,
				input_type = ?,
				output_type = ?
			WHERE filter_id = ?',
			array(
				$filter->getDisplayName(),
				(integer)$filter->getSeq(),
				$filter->getClassName(),
				$inputType->getTypeDescription(),
				$outputType->getTypeDescription(),
				$filter->getId()
			)
		);
		$this->updateDataObjectSettings('filter_settings', $filter,
				array('filter_id' => $filter->getId()));
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
		$params = array((int)$filterId);
		$this->update('DELETE FROM filters WHERE filter_id = ?', $params);
		return $this->update('DELETE FROM filter_settings WHERE filter_id = ?', $params);
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
	 * @param $filterClass string a fully qualified class name
	 * @param $inputType string
	 * @param $outputType string
	 * @return Filter
	 */
	function &_newDataObject($filterClass, $inputType, $outputType) {
		$filterClassParts = explode('.', $filterClass);
		$filterClassName = array_pop($filterClassParts);
		assert(!empty($filterClass) && !empty($filterClassName) && count($filterClassParts) > 1);

		// FIXME: validate filter class to avoid code inclusion vulnerabilities.

		// Instantiate the filter with the given input/output data types
		import($filterClass);
		$filter = new $filterClassName($inputType, $outputType);
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
		$filter =& $this->_newDataObject($row['class_name'], $row['input_type'], $row['output_type']);
		$filter->setId((int)$row['filter_id']);

		// Configure the filter instance
		$filter->setDisplayName($row['display_name']);
		$filter->setSeq((int)$row['seq']);

		$this->getDataObjectSettings('filter_settings', 'filter_id', $row['filter_id'], $filter);

		return $filter;
	}
}

?>
