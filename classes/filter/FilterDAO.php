<?php

/**
 * @file classes/filter/FilterDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FilterDAO
 *
 * @ingroup filter
 *
 * @see PersistableFilter
 *
 * @brief Operations for retrieving and modifying Filter objects.
 *
 * The filter DAO acts as a filter registry. It allows filter providers to
 * register transformations and filter consumers to identify available
 * transformations that convert a given input type into a required output type.
 *
 * Transformations are defined as a combination of a filter class, a pair of
 * input/output type specifications supported by that filter implementation
 * and a set of configuration parameters that customize the filter.
 *
 * Transformations can be based on filter templates. A template is defined as
 * a filter instance without parameterization. A flag is used to distinguish
 * filter templates from fully parameterized filter instances.
 *
 * Different filters that perform semantically related transformations (e.g.
 * all citation parsers or all citation output filters) are clustered into
 * filter groups (@see FilterGroup).
 *
 * The following additional conditions apply:
 * 1) A single filter class may support several transformations, i.e. distinct
 *    combinations of input and output types or distinct parameterizations.
 *    Therefore the filter DAO must be able to handle several registry entries
 *    per filter class.
 * 2) The DAO must take care to only select such transformations that are
 *    supported by the current runtime environment.
 * 3) The DAO implementation must be scalable, fast and memory efficient.
 */

namespace PKP\filter;

use APP\core\Application;
use Exception;
use PKP\core\DataObject;
use PKP\db\DAORegistry;
use PKP\db\DAOResultFactory;

class FilterDAO extends \PKP\db\DAO
{
    /** @var array names of additional settings for the currently persisted/retrieved filter */
    public $additionalFieldNames;

    /** @var array names of localized settings for the currently persisted/retrieved filter */
    public $localeFieldNames;


    /**
     * Instantiates a new filter from configuration data and then
     * installs it.
     *
     * @param array $settings key-value pairs that can be directly written
     *  via \PKP\core\DataObject::setData().
     * @param int $contextId the context the filter should be installed into
     * @param array $subFilters sub-filters (only allowed when the filter is a CompositeFilter)
     * @param bool $persist whether to actually persist the filter
     *
     * @return PersistableFilter|boolean the new filter if installation successful, otherwise 'false'.
     */
    public function configureObject(
        string $filterClassName,
        string $filterGroupSymbolic,
        array $settings = [],
        bool $asTemplate = false,
        ?int $contextId = Application::SITE_CONTEXT_ID,
        array $subFilters = [],
        bool $persist = true
    ): bool|PersistableFilter {
        // Retrieve the filter group from the database.
        $filterGroupDao = DAORegistry::getDAO('FilterGroupDAO'); /** @var FilterGroupDAO $filterGroupDao */
        $filterGroup = $filterGroupDao->getObjectBySymbolic($filterGroupSymbolic);
        if (!$filterGroup instanceof \PKP\filter\FilterGroup) {
            return false;
        }

        // Instantiate the filter.
        if (preg_match('/^[a-zA-Z0-9_.]+$/', $filterClassName)) {
            // DEPRECATED as of 3.4.0: Use old class.name.style and import() function (pre-PSR classloading) pkp/pkp-lib#8186
            $filter = instantiate($filterClassName, 'PersistableFilter', null, 'execute', $filterGroup); /** @var PersistableFilter $filter */
        } elseif (class_exists($filterClassName)) {
            $filter = new $filterClassName($filterGroup);
        } else {
            // Could not find class
            return false;
        }
        if (!$filter) {
            return false;
        }

        // Is this a template?
        $filter->setIsTemplate((bool)$asTemplate);

        // Add sub-filters (if any).
        if (!empty($subFilters)) {
            if (!$filter instanceof \PKP\filter\CompositeFilter) {
                throw new Exception('Filter is not expected type CompositeFilter!');
            }
            if (!is_array($subFilters)) {
                throw new Exception('Subfilters must be an array!');
            }
            foreach ($subFilters as $subFilter) {
                $filter->addFilter($subFilter);
            }
        }

        // Parameterize the filter.
        assert(is_array($settings));
        foreach ($settings as $key => $value) {
            $filter->setData($key, $value);
        }

        // Persist the filter.
        if ($persist) {
            $filterId = $this->insertObject($filter, $contextId);
            if (!$filterId) {
                return false;
            }
        }

        return $filter;
    }

    /**
     * Insert a new filter instance (transformation).
     *
     * @param $filter The configured filter instance to be persisted
     *
     * @return The new filter id
     */
    public function insertObject(PersistableFilter $filter, ?int $contextId = Application::SITE_CONTEXT_ID): int
    {
        $filterGroup = $filter->getFilterGroup();
        assert($filterGroup->getSymbolic() != FILTER_GROUP_TEMPORARY_ONLY);

        $this->update(
            sprintf('INSERT INTO filters
                (filter_group_id, context_id, display_name, class_name, is_template, parent_filter_id, seq)
                VALUES (?, ?, ?, ?, ?, ?, ?)'),
            [
                $filterGroup->getId(),
                $contextId,
                $filter->getDisplayName(),
                $filter->getClassName(),
                $filter->getIsTemplate() ? 1 : 0,
                $filter->getParentFilterId(),
                (int) $filter->getSequence()
            ]
        );
        $filter->setId((int)$this->getInsertId());
        $this->updateDataObjectSettings(
            'filter_settings',
            $filter,
            ['filter_id' => $filter->getId()]
        );

        // Recursively insert sub-filters.
        $this->_insertSubFilters($filter);

        return $filter->getId();
    }

    /**
     * Retrieve a configured filter instance (transformation)
     */
    public function getObject(PersistableFilter $filter): PersistableFilter
    {
        return $this->getObjectById($filter->getId());
    }

    /**
     * Retrieve a configured filter instance (transformation) by id.
     */
    public function getObjectById(int $filterId, bool $allowSubfilter = false): ?PersistableFilter
    {
        $result = $this->retrieve(
            'SELECT * FROM filters
            WHERE ' . ($allowSubfilter ? '' : 'parent_filter_id IS NULL AND ') . '
            filter_id = ?',
            [(int) $filterId]
        );
        $row = $result->current();
        return $row ? $this->_fromRow((array) $row) : null;
    }

    /**
     * Retrieve a result set with all filter instances
     * (transformations) that are based on the given class.
     *
     * @param bool $getTemplates set true if you want filter templates
     *  rather than actual transformations
     *
     * @return DAOResultFactory<PersistableFilter>
     */
    public function getObjectsByClass(
        string $className,
        ?int $contextId = Application::SITE_CONTEXT_ID,
        bool $getTemplates = false,
        bool $allowSubfilters = false
    ): DAOResultFactory {
        $result = $this->retrieve(
            'SELECT * FROM filters
                WHERE COALESCE(context_id, 0) = ? AND
                LOWER(class_name) = LOWER(?) AND
            ' . ($allowSubfilters ? '' : ' parent_filter_id IS NULL AND ') . '
            ' . ($getTemplates ? ' is_template = 1' : ' is_template = 0'),
            [(int) $contextId, $className]
        );

        return new DAOResultFactory($result, $this, '_fromRow', ['filter_id']);
    }

    /**
     * Retrieve a result set with all filter instances
     * (transformations) within a given group that are
     * based on the given class.
     *
     * @param bool $getTemplates set true if you want filter templates
     *  rather than actual transformations
     *
     * @return DAOResultFactory<PersistableFilter>
     */
    public function getObjectsByGroupAndClass(
        string $groupSymbolic,
        string $className,
        ?int $contextId = Application::SITE_CONTEXT_ID,
        bool $getTemplates = false,
        bool $allowSubfilters = false
    ): DAOResultFactory {
        $result = $this->retrieve(
            'SELECT f.* FROM filters f' .
            ' INNER JOIN filter_groups fg ON f.filter_group_id = fg.filter_group_id' .
            ' WHERE fg.symbolic = ? AND COALESCE(f.context_id, 0) = ? AND LOWER(f.class_name) = LOWER(?)' .
            ' ' . ($allowSubfilters ? '' : 'AND f.parent_filter_id IS NULL') .
            ' AND ' . ($getTemplates ? 'f.is_template = 1' : 'f.is_template = 0'),
            [$groupSymbolic, (int) $contextId, $className]
        );

        return new DAOResultFactory($result, $this, '_fromRow', ['filter_id']);
    }

    /**
     * Retrieve filters based on the supported input/output type.
     *
     * @param string $inputTypeDescription a type description that has to match the input type
     * @param string $outputTypeDescription a type description that has to match the output type
     *  NB: input and output type description can contain wildcards.
     * @param mixed $data the data to be matched by the filter. If no data is given then
     *  all filters will be matched.
     * @param bool $dataIsInput true if the given data object is to be checked as
     *  input type, false to check against the output type.
     *
     * @return PersistableFilter[] a list of matched filters.
     */
    public function getObjectsByTypeDescription(
        string $inputTypeDescription,
        string $outputTypeDescription,
        $data = null,
        bool $dataIsInput = true
    ): array {
        static $filterCache = [];
        static $objectFilterCache = [];

        // We do not yet support array data types. Implement when required.
        assert(!is_array($data));

        // Build the adapter cache.
        $filterCacheKey = md5($inputTypeDescription . '=>' . $outputTypeDescription);
        if (!isset($filterCache[$filterCacheKey])) {
            // Get all adapter filters.
            $result = $this->retrieve(
                'SELECT f.*' .
                ' FROM filters f' .
                '  INNER JOIN filter_groups fg ON f.filter_group_id = fg.filter_group_id' .
                ' WHERE LOWER(fg.input_type) LIKE LOWER(?)' .
                '  AND LOWER(fg.output_type) LIKE LOWER(?)' .
                '  AND f.parent_filter_id IS NULL AND f.is_template = 0',
                [$inputTypeDescription, $outputTypeDescription]
            );

            // Instantiate all filters.
            /** @var DAOResultFactory<PersistableFilter> */
            $filterFactory = new DAOResultFactory($result, $this, '_fromRow', ['filter_id']);
            $filterCache[$filterCacheKey] = $filterFactory->toAssociativeArray();
        }

        // Return all filter candidates if no data is given to check against.
        if (is_null($data)) {
            return $filterCache[$filterCacheKey];
        }

        // Build the object-specific adapter cache.
        $objectFilterCacheKey = md5($filterCacheKey . (is_object($data) ? get_class($data) : "'{$data}'") . ($dataIsInput ? 'in' : 'out'));
        if (!isset($objectFilterCache[$objectFilterCacheKey])) {
            $objectFilterCache[$objectFilterCacheKey] = [];
            foreach ($filterCache[$filterCacheKey] as $filterCandidateId => $filterCandidate) { /** @var PersistableFilter $filterCandidate */
                // Check whether the given object can be transformed
                // with this filter.
                if ($dataIsInput) {
                    $filterDataType = $filterCandidate->getInputType();
                } else {
                    $filterDataType = $filterCandidate->getOutputType();
                }
                if ($filterDataType->checkType($data)) {
                    $objectFilterCache[$objectFilterCacheKey][$filterCandidateId] = $filterCandidate;
                }
                unset($filterCandidate);
            }
        }

        return $objectFilterCache[$objectFilterCacheKey];
    }

    /**
     * Retrieve filter instances configured for a given context
     * that belong to a given filter group.
     *
     * Only filters supported by the current run-time environment
     * will be returned when $checkRuntimeEnvironment is set to 'true'.
     *
     * @param $contextId returns filters from the site context and
     *  the given filters of all contexts if set to null
     * @param $getTemplates set true if you want filter templates
     *  rather than actual transformations
     * @param $checkRuntimeEnvironment whether to remove filters
     *  from the result set that do not match the current run-time environment.
     *
     * @return filter instances (transformations) in the given group
     */
    public function getObjectsByGroup(
        string $groupSymbolic,
        ?int $contextId = Application::SITE_CONTEXT_ID_ALL,
        bool $getTemplates = false,
        bool $checkRuntimeEnvironment = true
    ): array {
        // 1) Get all available transformations in the group.
        $result = $this->retrieve(
            'SELECT f.* FROM filters f' .
            ' INNER JOIN filter_groups fg ON f.filter_group_id = fg.filter_group_id' .
            ' WHERE LOWER(fg.symbolic) = LOWER(?) AND ' . ($getTemplates ? 'f.is_template = 1' : 'f.is_template = 0') .
            '  ' . ($contextId !== Application::SITE_CONTEXT_ID_ALL ? 'AND COALESCE(f.context_id, 0) IN (0, ' . (int)$contextId . ')' : '') .
            '  AND f.parent_filter_id IS NULL',
            [$groupSymbolic]
        );


        // 2) Instantiate and return all transformations in the
        //    result set that comply with the current runtime
        //    environment.
        $matchingFilters = [];
        foreach ($result as $row) {
            $filterInstance = $this->_fromRow((array) $row);
            if (!$checkRuntimeEnvironment || $filterInstance->isCompatibleWithRuntimeEnvironment()) {
                $matchingFilters[$filterInstance->getId()] = $filterInstance;
            }
        }

        return $matchingFilters;
    }

    /**
     * Update an existing filter instance (transformation).
     */
    public function updateObject(PersistableFilter $filter): void
    {
        $filterGroup = $filter->getFilterGroup();
        assert($filterGroup->getSymbolic() != FILTER_GROUP_TEMPORARY_ONLY);

        $this->update(
            'UPDATE	filters
            SET	filter_group_id = ?,
                display_name = ?,
                class_name = ?,
                is_template = ?,
                parent_filter_id = ?,
                seq = ?
            WHERE filter_id = ?',
            [
                (int) $filterGroup->getId(),
                $filter->getDisplayName(),
                $filter->getClassName(),
                $filter->getIsTemplate() ? 1 : 0,
                $filter->getParentFilterId(),
                (int) $filter->getSequence(),
                (int) $filter->getId()
            ]
        );
        $this->updateDataObjectSettings(
            'filter_settings',
            $filter,
            ['filter_id' => $filter->getId()]
        );

        // Do we update a composite filter?
        if ($filter instanceof \PKP\filter\CompositeFilter) {
            // Delete all sub-filters
            $this->_deleteSubFiltersByParentFilterId($filter->getId());

            // Re-insert sub-filters
            $this->_insertSubFilters($filter);
        }
    }

    /**
     * Delete a filter instance (transformation).
     */
    public function deleteObject(PersistableFilter $filter): bool
    {
        return $this->deleteObjectById($filter->getId());
    }

    /**
     * Delete a filter instance (transformation) by id.
     */
    public function deleteObjectById(int $filterId): bool
    {
        $this->update('DELETE FROM filters WHERE filter_id = ?', [$filterId]);
        $this->update('DELETE FROM filter_settings WHERE filter_id = ?', [$filterId]);
        $this->_deleteSubFiltersByParentFilterId($filterId);
        return true;
    }


    //
    // Overridden methods from DAO
    //
    /**
     * @see DAO::updateDataObjectSettings()
     */
    public function updateDataObjectSettings(string $tableName, DataObject $dataObject, array $idArray): void
    {
        // Make sure that the update function finds the filter settings
        $this->additionalFieldNames = $dataObject->getSettingNames();
        $this->localeFieldNames = $dataObject->getLocalizedSettingNames();

        // Add runtime settings
        foreach ($dataObject->supportedRuntimeEnvironmentSettings() as $runtimeSetting => $defaultValue) {
            if ($dataObject->hasData($runtimeSetting)) {
                $this->additionalFieldNames[] = $runtimeSetting;
            }
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
    public function getAdditionalFieldNames(): array
    {
        return $this->additionalFieldNames;
    }

    /**
     * @see DAO::getLocaleFieldNames()
     */
    public function getLocaleFieldNames(): array
    {
        return $this->localeFieldNames;
    }


    //
    // Private helper methods
    //
    /**
     * Construct a new configured filter instance (transformation).
     *
     * @param string $filterClassName a fully qualified class name
     *
     */
    public function _newDataObject(string $filterClassName, int $filterGroupId): PersistableFilter
    {
        // Instantiate the filter group.
        $filterGroupDao = DAORegistry::getDAO('FilterGroupDAO'); /** @var FilterGroupDAO $filterGroupDao */
        $filterGroup = $filterGroupDao->getObjectById($filterGroupId);
        if (!$filterGroup) {
            throw new Exception('Could not get filter group by ID!');
        }

        // Instantiate the filter
        if (preg_match('/^[a-zA-Z0-9_.]+$/', $filterClassName)) {
            // DEPRECATED as of 3.4.0: Use old class.name.style and import() function (pre-PSR classloading) pkp/pkp-lib#8186
            $filter = instantiate($filterClassName, 'PersistableFilter', null, 'execute', $filterGroup); /** @var PersistableFilter $filter */
        } elseif (class_exists($filterClassName)) {
            $filter = new $filterClassName($filterGroup);
        }
        if (!is_object($filter)) {
            throw new Exception('Error while instantiating class "' . $filterClassName . '" as filter!');
        }

        return $filter;
    }

    /**
     * Internal function to return a filter instance (transformation)
     * object from a row.
     */
    public function _fromRow(array $row): PersistableFilter
    {
        static $lockedFilters = [];
        $filterId = $row['filter_id'];

        // Check the filter lock (to detect loops).
        // NB: This is very important otherwise the request
        // could eat up lots of memory if the PHP memory max was
        // set too high.
        if (isset($lockedFilters[$filterId])) {
            throw new Exception('Detected a loop in the definition of the filter with id ' . $filterId . '!');
        }

        // Lock the filter id.
        $lockedFilters[$filterId] = true;

        // Instantiate the filter.
        $filter = $this->_newDataObject($row['class_name'], (int)$row['filter_group_id']);

        // Configure the filter instance
        $filter->setId((int)$row['filter_id']);
        $filter->setDisplayName($row['display_name']);
        $filter->setIsTemplate((bool)$row['is_template']);
        $filter->setParentFilterId($row['parent_filter_id']);
        $filter->setSequence((int)$row['seq']);
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
     */
    public function _populateSubFilters(PersistableFilter $parentFilter): void
    {
        if (!$parentFilter instanceof \PKP\filter\CompositeFilter) {
            // Nothing to do. Only composite filters
            // can have sub-filters.
            return;
        }

        // Retrieve the sub-filters from the database.
        $result = $this->retrieve(
            'SELECT * FROM filters WHERE parent_filter_id = ? ORDER BY seq',
            [$parentFilter->getId()]
        );
        /** @var DAOResultFactory<PersistableFilter> */
        $daoResultFactory = new DAOResultFactory($result, $this, '_fromRow', ['filter_id']);

        // Add sub-filters.
        while ($subFilter = $daoResultFactory->next()) {
            // Add the sub-filter to the filter list
            // of its parent filter.
            $parentFilter->addFilter($subFilter);
        }
    }

    /**
     * Recursively insert sub-filters of
     * the given parent filter.
     */
    public function _insertSubFilters(Filter $parentFilter): void
    {
        if (!$parentFilter instanceof \PKP\filter\CompositeFilter) {
            // Nothing to do. Only composite filters
            // can have sub-filters.
            return;
        }

        // Recursively insert sub-filters
        foreach ($parentFilter->getFilters() as $subFilter) {
            $subFilter->setParentFilterId($parentFilter->getId());
            $subfilterId = $this->insertObject($subFilter);
        }
    }

    /**
     * Recursively delete all sub-filters for the given parent filter.
     */
    public function _deleteSubFiltersByParentFilterId(int $parentFilterId): void
    {
        // Identify sub-filters.
        $result = $this->retrieve(
            'SELECT * FROM filters WHERE parent_filter_id = ?',
            [$parentFilterId]
        );

        foreach ($result as $row) {
            // Delete sub-filters
            // NB: We need to do this before we delete
            // sub-sub-filters to avoid loops.
            $subFilterId = $row->filter_id;
            $this->deleteObjectById($subFilterId);

            // Recursively delete sub-sub-filters.
            $this->_deleteSubFiltersByParentFilterId($subFilterId);
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\filter\FilterDAO', '\FilterDAO');
}
