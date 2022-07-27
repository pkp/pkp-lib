<?php

/**
 * @file classes/tombstone/DataObjectTombstoneDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DataObjectTombstoneDAO
 * @ingroup tombstone
 *
 * @see DataObjectTombstone
 *
 * @brief Base class for retrieving and modifying DataObjectTombstone objects.
 */

namespace PKP\tombstone;

use PKP\db\DAORegistry;

class DataObjectTombstoneDAO extends \PKP\db\DAO
{
    /**
     * Return an instance of the DataObjectTombstone class.
     *
     * @return DataObjectTombstone
     */
    public function newDataObject()
    {
        return new DataObjectTombstone();
    }

    /**
     * Retrieve DataObjectTombstone by id.
     *
     * @param int $tombstoneId
     * @param int $assocType
     * @param int $assocId
     *
     * @return DataObjectTombstone object
     */
    public function getById($tombstoneId, $assocType = null, $assocId = null)
    {
        $params = [(int) $tombstoneId];
        if ($assocId !== null && $assocType !== null) {
            $params[] = (int) $assocType;
            $params[] = (int) $assocId;
        }
        $result = $this->retrieve(
            'SELECT DISTINCT * ' . $this->_getSelectTombstoneSql($assocType, $assocId),
            $params
        );
        $row = $result->current();
        return $row ? $this->_fromRow((array) $row) : null;
    }

    /**
     * Retrieve DataObjectTombstone by data object id.
     *
     * @param int $dataObjectId
     *
     * @return DataObjectTombstone object
     */
    public function getByDataObjectId($dataObjectId)
    {
        $result = $this->retrieve(
            'SELECT * FROM data_object_tombstones WHERE data_object_id = ?',
            [(int) $dataObjectId]
        );
        $row = $result->current();
        return $row ? $this->_fromRow((array) $row) : null;
    }

    /**
     * Creates and returns a data object tombstone object from a row.
     *
     * @param array $row
     *
     * @return DataObjectTombstone object
     */
    public function _fromRow($row)
    {
        $dataObjectTombstone = $this->newDataObject();
        $dataObjectTombstone->setId($row['tombstone_id']);
        $dataObjectTombstone->setDataObjectId($row['data_object_id']);
        $dataObjectTombstone->setDateDeleted($this->datetimeFromDB($row['date_deleted']));
        $dataObjectTombstone->setSetSpec($row['set_spec']);
        $dataObjectTombstone->setSetName($row['set_name']);
        $dataObjectTombstone->setOAIIdentifier($row['oai_identifier']);

        $OAISetObjectsIds = $this->getOAISetObjectsIds($dataObjectTombstone->getId());
        $dataObjectTombstone->setOAISetObjectsIds($OAISetObjectsIds);

        return $dataObjectTombstone;
    }

    /**
     * Delete DataObjectTombstone by tombstone id.
     *
     * @param int $tombstoneId
     * @param int $assocType
     * @param int $assocId
     *
     * @return bool
     */
    public function deleteById($tombstoneId, $assocType = null, $assocId = null)
    {
        $tombstone = $this->getById($tombstoneId, $assocType, $assocId);
        if (!$tombstone) {
            return false;
        } // Did not exist

        assert($tombstone instanceof \PKP\tombstone\DataObjectTombstone);

        if ($this->update(
            'DELETE FROM data_object_tombstones WHERE tombstone_id = ?',
            [(int) $tombstoneId]
        )) {
            $dataObjectTombstoneSettingsDao = DAORegistry::getDAO('DataObjectTombstoneSettingsDAO'); /** @var DataObjectTombstoneSettingsDAO $dataObjectTombstoneSettingsDao */
            $settingsDeleted = $dataObjectTombstoneSettingsDao->deleteSettings($tombstoneId);
            $setObjectsDeleted = $this->deleteOAISetObjects($tombstoneId);
            if ($settingsDeleted && $setObjectsDeleted) {
                return true;
            }
        }
        return false;
    }

    /**
     * Delete DataObjectTombstone by data object id.
     *
     * @param int $dataObjectId
     */
    public function deleteByDataObjectId($dataObjectId)
    {
        $dataObjectTombstone = $this->getByDataObjectId($dataObjectId);
        if ($dataObjectTombstone) {
            $this->deleteById($dataObjectTombstone->getId());
        }
    }

    /**
     * Inserts a new data object tombstone into data_object_tombstone table.
     *
     * @param DataObjectTombstone $dataObjectTombstone
     *
     * @return int Data object tombstone id.
     */
    public function insertObject(&$dataObjectTombstone)
    {
        $this->update(
            sprintf(
                'INSERT INTO data_object_tombstones
				(data_object_id, date_deleted, set_spec, set_name, oai_identifier)
				VALUES
				(?, %s, ?, ?, ?)',
                $this->datetimeToDB(date('Y-m-d H:i:s'))
            ),
            [
                (int) $dataObjectTombstone->getDataObjectId(),
                $dataObjectTombstone->getSetSpec(),
                $dataObjectTombstone->getSetName(),
                $dataObjectTombstone->getOAIIdentifier()
            ]
        );

        $dataObjectTombstone->setId($this->getInsertId());
        $this->insertOAISetObjects($dataObjectTombstone);

        return $dataObjectTombstone->getId();
    }

    /**
     * Update a data object tombstone in the data_object_tombstones table.
     *
     * @param DataObjectTombstone $dataObjectTombstone
     *
     * @return int dataObjectTombstone id
     */
    public function updateObject($dataObjectTombstone)
    {
        $returner = $this->update(
            sprintf(
                'UPDATE	data_object_tombstones SET
					data_object_id = ?,
					date_deleted = %s,
					set_spec = ?,
					set_name = ?,
					oai_identifier = ?
					WHERE	tombstone_id = ?',
                $this->datetimeToDB(date('Y-m-d H:i:s'))
            ),
            [
                (int) $publicationFormatTombstone->getDataObjectId(),
                $publicationFormatTombstone->getSetSpec(),
                $publicationFormatTombstone->getSetName(),
                $publicationFormatTombstone->getOAIIdentifier(),
                (int) $publicationFormatTombstone->getId()
            ]
        );

        $this->updateOAISetObjects($dataObjectTombstone);

        return $returner;
    }

    /**
     * Get the ID of the last inserted data object tombstone.
     *
     * @return int
     */
    public function getInsertId()
    {
        return $this->_getInsertId('data_object_tombstones', 'tombstone_id');
    }

    /**
     * Retrieve all sets for data object tombstones that are inside of
     * the passed set object id.
     *
     * @param int $assocType The assoc type of the parent set object.
     * @param int $assocId The id of the parent set object.
     *
     * @return array('setSpec' => setName)
     */
    public function &getSets($assocType, $assocId)
    {
        $result = $this->retrieve(
            'SELECT DISTINCT dot.set_spec AS set_spec, dot.set_name AS set_name FROM data_object_tombstones dot
			LEFT JOIN data_object_tombstone_oai_set_objects oso ON (dot.tombstone_id = oso.tombstone_id)
			WHERE oso.assoc_type = ? AND oso.assoc_id = ?',
            [(int) $assocType, (int) $assocId]
        );

        $returner = [];
        foreach ($result as $row) {
            $returner[$row->set_spec] = $row->set_name;
        }
        return $returner;
    }

    /**
     * Get all objects ids that are part of the passed
     * tombstone OAI set.
     *
     * @param int $tombstoneId
     *
     * @return array assocType => assocId
     */
    public function getOAISetObjectsIds($tombstoneId)
    {
        $result = $this->retrieve(
            'SELECT * FROM data_object_tombstone_oai_set_objects WHERE tombstone_id = ?',
            [(int) $tombstoneId]
        );

        $oaiSetObjectsIds = [];
        foreach ($result as $row) {
            $oaiSetObjectsIds[$row->assoc_type] = $row->assoc_id;
        }
        return $oaiSetObjectsIds;
    }

    /**
     * Delete OAI set objects data from data_object_tombstone_oai_set_objects table.
     *
     * @param int $tombstoneId The related tombstone id.
     */
    public function deleteOAISetObjects($tombstoneId)
    {
        return $this->update(
            'DELETE FROM data_object_tombstone_oai_set_objects WHERE tombstone_id = ?',
            [(int) $tombstoneId]
        );
    }

    /**
     * Insert OAI set objects data into data_object_tombstone_oai_set_objects table.
     *
     * @param DataObjectTombstone $dataObjectTombstone
     */
    public function insertOAISetObjects($dataObjectTombstone)
    {
        foreach ($dataObjectTombstone->getOAISetObjectsIds() as $assocType => $assocId) {
            $this->update(
                'INSERT INTO data_object_tombstone_oai_set_objects
					(tombstone_id, assoc_type, assoc_id)
					VALUES
					(?, ?, ?)',
                [
                    (int) $dataObjectTombstone->getId(),
                    (int) $assocType,
                    (int) $assocId
                ]
            );
        }
    }

    /**
     * Update OAI set objects data into data_object_tombstone_oai_set_objects table.
     *
     * @param DataObjectTombstone $dataObjectTombstone
     *
     * @return bool
     */
    public function updateOAISetObjects($dataObjectTombstone)
    {
        foreach ($dataObjectTombstone->getOAISetObjectsIds() as $assocType => $assocId) {
            $this->update(
                'UPDATE data_object_tombstone_oai_set_objects SET
					assoc_type = ?,
					assoc_id = ?
					WHERE	tombstone_id = ?',
                [
                    (int) $assocType,
                    (int) $assocId,
                    (int) $dataObjectTombstone->getId()
                ]
            );
        }
    }


    //
    // Private helper methods.
    //

    /**
     * Get the sql to select a tombstone from table, optionally
     * using an OAI set object id.
     *
     * @return string
     */
    public function _getSelectTombstoneSql($assocType, $assocId)
    {
        return 'FROM data_object_tombstones dot
			LEFT JOIN data_object_tombstone_oai_set_objects oso ON (dot.tombstone_id = oso.tombstone_id)
			WHERE dot.tombstone_id = ?' .
            (isset($assocId) && isset($assocType) ? 'AND oso.assoc_type = ? AND oso.assoc_id = ?' : '');
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\tombstone\DataObjectTombstoneDAO', '\DataObjectTombstoneDAO');
}
