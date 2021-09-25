<?php

/**
 * @file classes/controlledVocab/ControlledVocabDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ControlledVocabDAO
 * @ingroup controlled_vocab
 *
 * @see ControlledVocab
 *
 * @brief Operations for retrieving and modifying ControlledVocab objects.
 */

namespace PKP\controlledVocab;

use PKP\facades\Locale;
use PKP\db\DAORegistry;

class ControlledVocabDAO extends \PKP\db\DAO
{
    /**
     * Return the Controlled Vocab Entry DAO for this Controlled Vocab.
     * Can be subclassed to provide extended DAOs.
     */
    public function getEntryDAO()
    {
        return DAORegistry::getDAO('ControlledVocabEntryDAO');
    }

    /**
     * Retrieve a controlled vocab by controlled vocab ID.
     *
     * @param int $controlledVocabId
     *
     * @return ControlledVocab
     */
    public function getById($controlledVocabId)
    {
        $result = $this->retrieve('SELECT * FROM controlled_vocabs WHERE controlled_vocab_id = ?', [(int) $controlledVocabId]);
        $row = $result->current();
        return $row ? $this->_fromRow((array) $row) : null;
    }

    /**
     * Fetch a controlled vocab by symbolic info, building it if needed.
     *
     * @param string $symbolic
     * @param int $assocType
     * @param int $assocId
     *
     * @return $controlledVocab
     */
    public function _build($symbolic, $assocType = 0, $assocId = 0)
    {
        // Attempt to fetch an existing controlled vocabulary.
        $controlledVocab = $this->getBySymbolic($symbolic, $assocType, $assocId);
        if ($controlledVocab) {
            return $controlledVocab;
        }

        // Attempt to build a new controlled vocabulary.
        $controlledVocab = $this->newDataObject();
        $controlledVocab->setSymbolic($symbolic);
        $controlledVocab->setAssocType($assocType);
        $controlledVocab->setAssocId($assocId);
        $id = $this->insertObject($controlledVocab, false);
        if ($id !== null) {
            return $controlledVocab;
        }

        // Presume that an error was a duplicate insert.
        // In this case, try to fetch an existing controlled
        // vocabulary.
        return $this->getBySymbolic($symbolic, $assocType, $assocId);
    }

    /**
     * Construct a new data object corresponding to this DAO.
     *
     * @return ControlledVocabEntry
     */
    public function newDataObject()
    {
        return new ControlledVocab();
    }

    /**
     * Internal function to return an ControlledVocab object from a row.
     *
     * @param array $row
     *
     * @return ControlledVocab
     */
    public function _fromRow($row)
    {
        $controlledVocab = $this->newDataObject();
        $controlledVocab->setId($row['controlled_vocab_id']);
        $controlledVocab->setAssocType($row['assoc_type']);
        $controlledVocab->setAssocId($row['assoc_id']);
        $controlledVocab->setSymbolic($row['symbolic']);

        return $controlledVocab;
    }

    /**
     * Insert a new ControlledVocab.
     *
     * @param ControlledVocab $controlledVocab
     *
     * @return int? New insert ID on insert, or null on error
     */
    public function insertObject($controlledVocab, $dieOnError = true)
    {
        $success = $this->update(
            sprintf('INSERT INTO controlled_vocabs
				(symbolic, assoc_type, assoc_id)
				VALUES
				(?, ?, ?)'),
            [
                $controlledVocab->getSymbolic(),
                (int) $controlledVocab->getAssocType(),
                (int) $controlledVocab->getAssocId()
            ],
            true, // callHooks
            $dieOnError
        );
        if ($success) {
            $controlledVocab->setId($this->getInsertId());
            return $controlledVocab->getId();
        } else {
            return null;
        } // An error occurred on insert
    }

    /**
     * Update an existing controlled vocab.
     *
     * @param ControlledVocab $controlledVocab
     *
     * @return bool
     */
    public function updateObject($controlledVocab)
    {
        $returner = $this->update(
            sprintf('UPDATE	controlled_vocabs
				SET	symbolic = ?,
					assoc_type = ?,
					assoc_id = ?
				WHERE	controlled_vocab_id = ?'),
            [
                $controlledVocab->getSymbolic(),
                (int) $controlledVocab->getAssocType(),
                (int) $controlledVocab->getAssocId(),
                (int) $controlledVocab->getId()
            ]
        );
        return $returner;
    }

    /**
     * Delete a controlled vocab.
     *
     * @param ControlledVocab $controlledVocab
     *
     * @return bool
     */
    public function deleteObject($controlledVocab)
    {
        return $this->deleteObjectById($controlledVocab->getId());
    }

    /**
     * Delete a controlled vocab by controlled vocab ID.
     *
     * @param int $controlledVocabId
     *
     * @return bool
     */
    public function deleteObjectById($controlledVocabId)
    {
        $controlledVocabEntryDao = DAORegistry::getDAO('ControlledVocabEntryDAO'); /** @var ControlledVocabEntryDAO $controlledVocabEntryDao */
        $controlledVocabEntries = $this->enumerate($controlledVocabId);
        foreach ($controlledVocabEntries as $controlledVocabEntryId => $controlledVocabEntryName) {
            $controlledVocabEntryDao->deleteObjectById($controlledVocabEntryId);
        }
        return $this->update('DELETE FROM controlled_vocabs WHERE controlled_vocab_id = ?', [(int) $controlledVocabId]);
    }

    /**
     * Retrieve an array of controlled vocabs matching the specified
     * symbolic name and assoc info.
     *
     * @param string $symbolic
     * @param int $assocType
     * @param int $assocId
     *
     * @return ControlledVocab?
     */
    public function getBySymbolic($symbolic, $assocType = 0, $assocId = 0)
    {
        $result = $this->retrieve(
            'SELECT * FROM controlled_vocabs WHERE symbolic = ? AND assoc_type = ? AND assoc_id = ?',
            [$symbolic, (int) $assocType, (int) $assocId]
        );
        $row = $result->current();
        return $row ? $this->_fromRow((array) $row) : null;
    }

    /**
     * Get a list of controlled vocabulary options.
     *
     * @param string $symbolic
     * @param int $assocType
     * @param int $assocId
     * @param string $settingName optional
     *
     * @return array $controlledVocabEntryId => $settingValue
     */
    public function enumerateBySymbolic($symbolic, $assocType, $assocId, $settingName = 'name')
    {
        $controlledVocab = $this->getBySymbolic($symbolic, $assocType, $assocId);
        if (!$controlledVocab) {
            return [];
        }
        return $controlledVocab->enumerate($settingName);
    }

    /**
     * Get a list of controlled vocabulary options.
     *
     * @param int $controlledVocabId
     * @param string $settingName optional
     *
     * @return array $controlledVocabEntryId => name
     */
    public function enumerate($controlledVocabId, $settingName = 'name')
    {
        $result = $this->retrieve(
            'SELECT	e.controlled_vocab_entry_id,
				COALESCE(l.setting_value, p.setting_value, n.setting_value) AS setting_value,
				COALESCE(l.setting_type, p.setting_type, n.setting_type) AS setting_type
			FROM	controlled_vocab_entries e
				LEFT JOIN controlled_vocab_entry_settings l ON (l.controlled_vocab_entry_id = e.controlled_vocab_entry_id AND l.setting_name = ? AND l.locale = ?)
				LEFT JOIN controlled_vocab_entry_settings p ON (p.controlled_vocab_entry_id = e.controlled_vocab_entry_id AND p.setting_name = ? AND p.locale = ?)
				LEFT JOIN controlled_vocab_entry_settings n ON (n.controlled_vocab_entry_id = e.controlled_vocab_entry_id AND n.setting_name = ? AND n.locale = ?)
			WHERE	e.controlled_vocab_id = ?
			ORDER BY e.seq',
            [
                $settingName, Locale::getLocale(),		// Current locale
                $settingName, Locale::getPrimaryLocale(),	// Primary locale
                $settingName, '',				// No locale
                (int) $controlledVocabId
            ]
        );

        $returner = [];
        foreach ($result as $row) {
            $returner[$row->controlled_vocab_entry_id] = $this->convertFromDB(
                $row->setting_value,
                $row->setting_type
            );
        }
        return $returner;
    }

    /**
     * Get the ID of the last inserted controlled vocab.
     *
     * @return int
     */
    public function getInsertId()
    {
        return parent::_getInsertId('controlled_vocabs', 'controlled_vocab_id');
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\controlledVocab\ControlledVocabDAO', '\ControlledVocabDAO');
}
