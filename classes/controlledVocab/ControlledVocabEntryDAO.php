<?php

/**
 * @file classes/controlledVocab/ControlledVocabEntryDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ControlledVocabEntryDAO
 *
 * @ingroup controlled_vocab
 *
 * @see ControlledVocabEntry
 *
 * @brief Operations for retrieving and modifying ControlledVocabEntry objects
 */

namespace PKP\controlledVocab;

use PKP\core\PKPApplication;
use PKP\db\DAOResultFactory;
use PKP\db\DBResultRange;

class ControlledVocabEntryDAO extends \PKP\db\DAO
{
    /**
     * Retrieve a controlled vocab entry by controlled vocab entry ID.
     *
     * @param int $controlledVocabEntryId
     * @param null|mixed $controlledVocabId
     *
     * @return ControlledVocabEntry
     */
    public function getById($controlledVocabEntryId, $controlledVocabId = null)
    {
        $params = [(int) $controlledVocabEntryId];
        if (!empty($controlledVocabId)) {
            $params[] = (int) $controlledVocabId;
        }

        $result = $this->retrieve(
            'SELECT * FROM controlled_vocab_entries WHERE controlled_vocab_entry_id = ?' .
            (!empty($controlledVocabId) ? ' AND controlled_vocab_id = ?' : ''),
            $params
        );
        $row = $result->current();
        return $row ? $this->_fromRow((array) $row) : null;
    }

    /**
     * Retrieve a controlled vocab entry by resolving one of its settings
     * to the corresponding entry id.
     *
     * @param string $settingValue the setting value to be searched for
     * @param string $symbolic the vocabulary to be searched, identified by its symbolic name
     * @param int $assocType
     * @param int $assocId
     * @param string $settingName the setting to be searched
     * @param string $locale
     *
     * @return ControlledVocabEntry
     */
    public function getBySetting($settingValue, $symbolic, $assocType = 0, $assocId = 0, $settingName = 'name', $locale = '')
    {
        $result = $this->retrieve(
            'SELECT cve.*
			 FROM controlled_vocabs cv
			 INNER JOIN controlled_vocab_entries cve ON cv.controlled_vocab_id = cve.controlled_vocab_id
			 INNER JOIN controlled_vocab_entry_settings cves ON cve.controlled_vocab_entry_id = cves.controlled_vocab_entry_id
			 WHERE	cves.setting_name = ? AND
				cves.locale = ? AND
				cves.setting_value = ? AND
				cv.symbolic = ? AND
				cv.assoc_type = ? AND
				cv.assoc_id = ?',
            [$settingName, $locale, $settingValue, $symbolic, $assocType, $assocId]
        );
        $row = $result->current();
        return $row ? $this->_fromRow((array) $row) : null;
    }

    /**
     * Construct a new data object corresponding to this DAO.
     *
     * @return ControlledVocabEntry
     */
    public function newDataObject()
    {
        return new ControlledVocabEntry();
    }

    /**
     * Internal function to return a ControlledVocabEntry object from a
     * row.
     *
     * @param array $row
     *
     * @return ControlledVocabEntry
     */
    public function _fromRow($row)
    {
        $controlledVocabEntry = $this->newDataObject();
        $controlledVocabEntry->setControlledVocabId($row['controlled_vocab_id']);
        $controlledVocabEntry->setId($row['controlled_vocab_entry_id']);
        $controlledVocabEntry->setSequence($row['seq']);

        $this->getDataObjectSettings('controlled_vocab_entry_settings', 'controlled_vocab_entry_id', $row['controlled_vocab_entry_id'], $controlledVocabEntry);

        return $controlledVocabEntry;
    }

    /**
     * Get the list of fields for which data can be localized.
     */
    public function getLocaleFieldNames(): array
    {
        return ['name'];
    }

    /**
     * Update the localized fields for this table
     *
     * @param object $controlledVocabEntry
     */
    public function updateLocaleFields($controlledVocabEntry)
    {
        $this->updateDataObjectSettings('controlled_vocab_entry_settings', $controlledVocabEntry, [
            'controlled_vocab_entry_id' => $controlledVocabEntry->getId()
        ]);
    }

    /**
     * Insert a new ControlledVocabEntry.
     *
     * @param ControlledVocabEntry $controlledVocabEntry
     *
     * @return int Inserted controlled vocabulary entry ID
     */
    public function insertObject($controlledVocabEntry)
    {
        $this->update(
            'INSERT INTO controlled_vocab_entries (controlled_vocab_id, seq)
			VALUES (?, ?)',
            [
                (int) $controlledVocabEntry->getControlledVocabId(),
                (float) $controlledVocabEntry->getSequence()
            ]
        );
        $controlledVocabEntry->setId($this->getInsertId());
        $this->updateLocaleFields($controlledVocabEntry);
        return (int)$controlledVocabEntry->getId();
    }

    /**
     * Delete a controlled vocab entry.
     *
     * @param ControlledVocabEntry $controlledVocabEntry
     */
    public function deleteObject($controlledVocabEntry)
    {
        $this->deleteObjectById($controlledVocabEntry->getId());
    }

    /**
     * Delete a controlled vocab entry by controlled vocab entry ID.
     *
     * @param int $controlledVocabEntryId
     */
    public function deleteObjectById($controlledVocabEntryId)
    {
        $this->update('DELETE FROM controlled_vocab_entry_settings WHERE controlled_vocab_entry_id = ?', [(int) $controlledVocabEntryId]);
        $this->update('DELETE FROM controlled_vocab_entries WHERE controlled_vocab_entry_id = ?', [(int) $controlledVocabEntryId]);
    }

    /**
     * Retrieve an iterator of controlled vocabulary entries matching a
     * particular controlled vocabulary ID.
     *
     * @param int $controlledVocabId
     * @param ?DBResultRange $rangeInfo
     * @param null|mixed $filter
     *
     * @return DAOResultFactory<ControlledVocabEntry> Object containing matching CVE objects
     */
    public function getByControlledVocabId($controlledVocabId, $rangeInfo = null, $filter = null)
    {
        $params = [(int) $controlledVocabId];
        if (!empty($filter)) {
            $params[] = "%{$filter}%";
        }

        $result = $this->retrieveRange(
            'SELECT *
			 FROM controlled_vocab_entries cve ' .
             (!empty($filter) ? 'INNER JOIN controlled_vocab_entry_settings cves ON cve.controlled_vocab_entry_id = cves.controlled_vocab_entry_id ' : '') .
            'WHERE controlled_vocab_id = ? ' .
             (!empty($filter) ? 'AND cves.setting_value LIKE ? ' : '') .
            'ORDER BY seq',
            $params,
            $rangeInfo
        );

        return new DAOResultFactory($result, $this, '_fromRow');
    }

    /**
     * Retrieve an array of controlled vocab entries that exist for a given context
     * (assigned to at least one submission in that context) and which match the
     * requested symbolic (eg - keywords/subjects)
     *
     * @param string $symbolic One of the CONTROLLED_VOCAB_* constants
     * @param int $contextId
     * @param string $locale
     *
     * @return DAOResultFactory<ControlledVocabEntry>
     */
    public function getByContextId($symbolic, int $contextId, $locale, ?string $term = null)
    {
        $params = [
            $symbolic,
            PKPApplication::ASSOC_TYPE_PUBLICATION,
            $contextId,
            $locale
        ];
        $words = array_map(fn (string $word) => '%' . addcslashes($word, '%_') . '%', preg_split('/\s+/u', trim($term ?? '')));

        $termFilter = '';
        if (count($words)) {
            array_push($params, ...$words);
            $condition = 'cves.setting_value LIKE ?';
            $termFilter = " AND ({$condition}" . str_repeat(" OR {$condition}", count($words) - 1) . ')';
        }
        $result = $this->retrieve(
            "SELECT cve.*
            FROM controlled_vocab_entries AS cve
            INNER JOIN controlled_vocabs AS cv ON cv.controlled_vocab_id = cve.controlled_vocab_id
            INNER JOIN controlled_vocab_entry_settings AS cves ON cves.controlled_vocab_entry_id = cve.controlled_vocab_entry_id
            INNER JOIN publications as p ON p.publication_id = cv.assoc_id
            INNER JOIN submissions AS s ON s.submission_id = p.submission_id
            WHERE
                cv.symbolic = ?
                AND cv.assoc_type = ?
                AND s.context_id = ?
                AND cves.locale = ?
                {$termFilter}
            ORDER BY
                cves.setting_value",
            $params
        );

        return new DAOResultFactory($result, $this, '_fromRow');
    }

    /**
     * Update an existing review form element.
     *
     * @param ControlledVocabEntry $controlledVocabEntry
     */
    public function updateObject($controlledVocabEntry)
    {
        $this->update(
            'UPDATE	controlled_vocab_entries
			SET	controlled_vocab_id = ?,
				seq = ?
			WHERE	controlled_vocab_entry_id = ?',
            [
                (int) $controlledVocabEntry->getControlledVocabId(),
                (float) $controlledVocabEntry->getSequence(),
                (int) $controlledVocabEntry->getId()
            ]
        );
        $this->updateLocaleFields($controlledVocabEntry);
    }

    /**
     * Sequentially renumber entries in their sequence order.
     *
     * @param int $controlledVocabId Controlled vocabulary ID
     */
    public function resequence($controlledVocabId)
    {
        $result = $this->retrieve('SELECT controlled_vocab_entry_id FROM controlled_vocab_entries WHERE controlled_vocab_id = ? ORDER BY seq', [(int) $controlledVocabId]);

        for ($i = 1; $row = $result->current(); $i++) {
            $this->update('UPDATE controlled_vocab_entries SET seq = ? WHERE controlled_vocab_entry_id = ?', [(int) $i, (int) $row->controlled_vocab_entry_id]);
            $result->next();
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\controlledVocab\ControlledVocabEntryDAO', '\ControlledVocabEntryDAO');
}
