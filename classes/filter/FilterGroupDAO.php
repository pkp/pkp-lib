<?php

/**
 * @file classes/filter/FilterGroupDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FilterGroupDAO
 * @ingroup filter
 *
 * @see FilterGroup
 *
 * @brief Operations for retrieving and modifying FilterGroup objects.
 */

namespace PKP\filter;

use PKP\db\DAORegistry;

class FilterGroupDAO extends \PKP\db\DAO
{
    /**
     * Insert a new filter group.
     *
     * @param FilterGroup $filterGroup
     *
     * @return int the new filter group id
     */
    public function insertObject(&$filterGroup)
    {
        $this->update(
            sprintf('INSERT INTO filter_groups
				(symbolic, display_name, description, input_type, output_type)
				VALUES (?, ?, ?, ?, ?)'),
            [
                $filterGroup->getSymbolic(),
                $filterGroup->getDisplayName(),
                $filterGroup->getDescription(),
                $filterGroup->getInputType(),
                $filterGroup->getOutputType()
            ]
        );
        $filterGroup->setId((int)$this->getInsertId());
        return $filterGroup->getId();
    }

    /**
     * Retrieve a filter group
     *
     * @param FilterGroup $filterGroup
     *
     * @return FilterGroup
     */
    public function getObject($filterGroup)
    {
        return $this->getObjectById($filterGroup->getId());
    }

    /**
     * Retrieve a configured filter group by id.
     *
     * @param int $filterGroupId
     *
     * @return FilterGroup
     */
    public function getObjectById($filterGroupId)
    {
        $result = $this->retrieve(
            'SELECT * FROM filter_groups' .
                ' WHERE filter_group_id = ?',
            [$filterGroupId]
        );
        $row = (array) $result->current();
        return $row ? $this->_fromRow($row) : null;
    }

    /**
     * Retrieve a configured filter group by its symbolic representation.
     *
     * @param string $filterGroupSymbolic
     *
     * @return FilterGroup
     */
    public function getObjectBySymbolic($filterGroupSymbolic)
    {
        $result = $this->retrieve(
            'SELECT * FROM filter_groups' .
                ' WHERE symbolic = ?',
            [$filterGroupSymbolic]
        );

        $row = (array) $result->current();
        return $row ? $this->_fromRow($row) : null;
    }

    /**
     * Update an existing filter group.
     *
     * @param FilterGroup $filterGroup
     */
    public function updateObject(&$filterGroup)
    {
        $this->update(
            'UPDATE	filter_groups
			SET	symbolic = ?,
				display_name = ?,
				description = ?,
				input_type = ?,
				output_type = ?
			WHERE	filter_group_id = ?',
            [
                $filterGroup->getSymbolic(),
                $filterGroup->getDisplayName(),
                $filterGroup->getDescription(),
                $filterGroup->getInputType(),
                $filterGroup->getOutputType(),
                (int)$filterGroup->getId()
            ]
        );
    }

    /**
     * Delete a filter group (only works if there are not more filters in this group).
     *
     * @param FilterGroup $filterGroup
     *
     * @return bool
     */
    public function deleteObject($filterGroup)
    {
        $filterDao = DAORegistry::getDAO('FilterDAO'); /** @var FilterDAO $filterDao */

        // Check whether there are still templates saved for this filter group.
        $filterTemplates = $filterDao->getObjectsByGroup($filterGroup->getSymbolic(), null, true, false);
        if (!empty($filterTemplates)) {
            return false;
        }

        // Check whether there are still filters saved for this filter group.
        $filters = $filterDao->getObjectsByGroup($filterGroup->getSymbolic(), null, false, false);
        if (!empty($filters)) {
            return false;
        }

        // Delete the group if it's empty.
        $this->update('DELETE FROM filter_groups WHERE filter_group_id = ?', [$filterGroup->getId()]);

        return true;
    }

    /**
     * Delete a filter group by id.
     *
     * @param int $filterGroupId
     *
     * @return bool
     */
    public function deleteObjectById($filterGroupId)
    {
        $filterGroupId = (int)$filterGroupId;
        $filterGroup = $this->getObjectById($filterGroupId);
        if (!$filterGroup instanceof \PKP\filter\FilterGroup) {
            return false;
        }
        return $this->deleteObject($filterGroup);
    }

    /**
     * Delete a filter group by symbolic name.
     *
     * @param string $filterGroupSymbolic
     *
     * @return bool
     */
    public function deleteObjectBySymbolic($filterGroupSymbolic)
    {
        $filterGroup = $this->getObjectBySymbolic($filterGroupSymbolic);
        if (!$filterGroup instanceof \PKP\filter\FilterGroup) {
            return false;
        }
        return $this->deleteObject($filterGroup);
    }


    //
    // Protected helper methods
    //
    /**
     * Get the ID of the last inserted filter group.
     *
     * @return int
     */
    public function getInsertId()
    {
        return parent::_getInsertId('filter_groups', 'filter_group_id');
    }

    /**
     * Construct and return a new data object
     *
     * @return DataObject
     */
    public function newDataObject()
    {
        return new FilterGroup();
    }


    //
    // Private helper methods
    //
    /**
     * Internal function to return a filter group
     * object from a row.
     *
     * @param array $row
     *
     * @return FilterGroup
     */
    public function _fromRow($row)
    {
        // Instantiate the filter group.
        $filterGroup = $this->newDataObject();

        // Configure the filter group.
        $filterGroup->setId((int)$row['filter_group_id']);
        $filterGroup->setSymbolic($row['symbolic']);
        $filterGroup->setDisplayName($row['display_name']);
        $filterGroup->setDescription($row['description']);
        $filterGroup->setInputType($row['input_type']);
        $filterGroup->setOutputType($row['output_type']);

        return $filterGroup;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\filter\FilterGroupDAO', '\FilterGroupDAO');
}
