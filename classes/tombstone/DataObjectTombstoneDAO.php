<?php

/**
 * @file classes/tombstone/DataObjectTombstoneDAO.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2003-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DataObjectTombstoneDAO
 *
 * @see DataObjectTombstone
 *
 * @brief Base class for retrieving and modifying DataObjectTombstone objects.
 */

namespace PKP\tombstone;

use Illuminate\Support\Facades\DB;
use PKP\db\DAO;

class DataObjectTombstoneDAO extends DAO
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
     */
    public function getById(int $tombstoneId, ?int $assocType = null, ?int $assocId = null): ?DataObjectTombstone
    {
        $params = [$tombstoneId];
        if ($assocId !== null && $assocType !== null) {
            $params[] = $assocType;
            $params[] = $assocId;
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
     */
    public function getByDataObjectId(int $dataObjectId): ?DataObjectTombstone
    {
        $result = $this->retrieve(
            'SELECT * FROM data_object_tombstones WHERE data_object_id = ?',
            [$dataObjectId]
        );
        $row = $result->current();
        return $row ? $this->_fromRow((array) $row) : null;
    }

    /**
     * Creates and returns a data object tombstone object from a row.
     */
    public function _fromRow(array $row): DataObjectTombstone
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
     */
    public function deleteById(int $tombstoneId, ?int $assocType = null, ?int $assocId = null): int
    {
        $tombstone = $this->getById($tombstoneId, $assocType, $assocId);
        if (!$tombstone) {
            // Did not exist
            return 0;
        }

        return DB::table('data_object_tombstones')
            ->where('tombstone_id', '=', $tombstoneId)
            ->delete();
    }

    /**
     * Delete DataObjectTombstone by data object id.
     */
    public function deleteByDataObjectId(int $dataObjectId): int
    {
        $dataObjectTombstone = $this->getByDataObjectId($dataObjectId);
        if (!$dataObjectTombstone) {
            return 0;
        }

        return $this->deleteById($dataObjectTombstone->getId());
    }

    /**
     * Inserts a new data object tombstone into data_object_tombstone table.
     *
     * @return int Data object tombstone id.
     */
    public function insertObject(DataObjectTombstone $dataObjectTombstone): int
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
                $dataObjectTombstone->getDataObjectId(),
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
     * @return int Affected row count
     */
    public function updateObject(DataObjectTombstone $dataObjectTombstone): int
    {
        $affectedRows = $this->update(
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
                $dataObjectTombstone->getDataObjectId(),
                $dataObjectTombstone->getSetSpec(),
                $dataObjectTombstone->getSetName(),
                $dataObjectTombstone->getOAIIdentifier(),
                $dataObjectTombstone->getId()
            ]
        );

        $this->updateOAISetObjects($dataObjectTombstone);

        return $affectedRows;
    }

    /**
     * Retrieve all sets for data object tombstones that are inside of
     * the passed set object id.
     *
     * @param $assocType The assoc type of the parent set object.
     * @param $assocId The id of the parent set object.
     *
     * @return array('setSpec' => setName)
     */
    public function getSets(int $assocType, int $assocId): array
    {
        $result = $this->retrieve(
            'SELECT DISTINCT dot.set_spec AS set_spec, dot.set_name AS set_name FROM data_object_tombstones dot
			LEFT JOIN data_object_tombstone_oai_set_objects oso ON (dot.tombstone_id = oso.tombstone_id)
			WHERE oso.assoc_type = ? AND oso.assoc_id = ?',
            [$assocType, $assocId]
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
     * @return array assocType => assocId
     */
    public function getOAISetObjectsIds(int $tombstoneId): array
    {
        $result = $this->retrieve(
            'SELECT * FROM data_object_tombstone_oai_set_objects WHERE tombstone_id = ?',
            [$tombstoneId]
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
     * @param $tombstoneId The related tombstone id.
     */
    public function deleteOAISetObjects(int $tombstoneId): int
    {
        return $this->update(
            'DELETE FROM data_object_tombstone_oai_set_objects WHERE tombstone_id = ?',
            [$tombstoneId]
        );
    }

    /**
     * Insert OAI set objects data into data_object_tombstone_oai_set_objects table.
     */
    public function insertOAISetObjects(DataObjectTombstone $dataObjectTombstone): void
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
     */
    public function updateOAISetObjects(DataObjectTombstone $dataObjectTombstone): void
    {
        foreach ($dataObjectTombstone->getOAISetObjectsIds() as $assocType => $assocId) {
            $this->update(
                'UPDATE data_object_tombstone_oai_set_objects SET
					assoc_type = ?,
					assoc_id = ?
					WHERE	tombstone_id = ?',
                [
                    $assocType,
                    $assocId,
                    $dataObjectTombstone->getId()
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
     */
    public function _getSelectTombstoneSql(?int $assocType, ?int $assocId): string
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
