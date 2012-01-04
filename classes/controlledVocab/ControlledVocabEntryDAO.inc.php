<?php

/**
 * @file ControlledVocabEntryDAO.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ControlledVocabEntryDAO
 * @ingroup controlled_vocab
 * @see ControlledVocabEntry
 *
 * @brief Operations for retrieving and modifying ControlledVocabEntry objects
 */

//$Id$

import('controlledVocab.ControlledVocabEntry');

class ControlledVocabEntryDAO extends DAO {
	/**
	 * Retrieve a controlled vocab entry by controlled vocab entry ID.
	 * @param $controlledVocabEntryId int
	 * @param $controlledVocabEntry int optional
	 * @return ControlledVocabEntry
	 */
	function getById($controlledVocabEntryId, $controlledVocabId = null) {
		$params = array((int) $controlledVocabEntryId);
		if (!empty($controlledVocabId)) $params[] = (int) $controlledVocabId;

		$result =& $this->retrieve(
			'SELECT * FROM controlled_vocab_entries WHERE controlled_vocab_entry_id = ?' .
			(!empty($controlledVocabId)?' AND controlled_vocab_id = ?':''),
			$params
		);

		$returner = null;
		if ($result->RecordCount() != 0) {
			$returner = $this->_fromRow($result->GetRowAssoc(false));
		}
		$result->Close();
		return $returner;
	}

	/**
	 * Construct a new data object corresponding to this DAO.
	 * @return ControlledVocabEntry
	 */
	function newDataObject() {
		return new ControlledVocabEntry();
	}

	/**
	 * Internal function to return an ControlledVocabEntry object from a
	 * row.
	 * @param $row array
	 * @return ControlledVocabEntry
	 */
	function _fromRow(&$row) {
		$controlledVocabEntry = $this->newDataObject();
		$controlledVocabEntry->setControlledVocabId($row['controlled_vocab_id']);
		$controlledVocabEntry->setId($row['controlled_vocab_entry_id']);
		$controlledVocabEntry->setSequence($row['seq']);

		$this->getDataObjectSettings('controlled_vocab_entry_settings', 'controlled_vocab_entry_id', $row['controlled_vocab_entry_id'], $controlledVocabEntry);

		return $controlledVocabEntry;
	}

	/**
	 * Get the list of fields for which data can be localized.
	 * @return array
	 */
	function getLocaleFieldNames() {
		return array('name');
	}

	/**
	 * Update the localized fields for this table
	 * @param $controlledVocabEntry object
	 */
	function updateLocaleFields(&$controlledVocabEntry) {
		$this->updateDataObjectSettings('controlled_vocab_entry_settings', $controlledVocabEntry, array(
			'controlled_vocab_entry_id' => $controlledVocabEntry->getId()
		));
	}

	/**
	 * Insert a new ControlledVocabEntry.
	 * @param $controlledVocabEntry ControlledVocabEntry
	 * @return int
	 */
	function insertObject(&$controlledVocabEntry) {
		$this->update(
			sprintf('INSERT INTO controlled_vocab_entries
				(controlled_vocab_id, seq)
				VALUES
				(?, ?)'),
			array(
				(int) $controlledVocabEntry->getControlledVocabId(),
				(float) $controlledVocabEntry->getSequence()
			)
		);
		$controlledVocabEntry->setId($this->getInsertId());
		$this->updateLocaleFields($controlledVocabEntry);
		return $controlledVocabEntry->getId();
	}

	/**
	 * Delete a controlled vocab entry.
	 * @param $controlledVocabEntry ControlledVocabEntry
	 * @return boolean
	 */
	function deleteObject($controlledVocabEntry) {
		return $this->deleteObjectById($controlledVocabEntry->getId());
	}

	/**
	 * Delete a controlled vocab entry by controlled vocab entry ID.
	 * @param $controlledVocabEntryId int
	 * @return boolean
	 */
	function deleteObjectById($controlledVocabEntryId) {
		$params = array((int) $controlledVocabEntryId);
		$this->update('DELETE FROM controlled_vocab_entry_settings WHERE controlled_vocab_entry_id = ?', $params);
		return $this->update('DELETE FROM controlled_vocab_entries WHERE controlled_vocab_entry_id = ?', $params);
	}

	/**
	 * Retrieve an iterator of controlled vocabulary entries matching a
	 * particular controlled vocabulary ID.
	 * @param $controlledVocabId int
	 * @return object DAOResultFactory containing matching CVE objects
	 */
	function getByControlledVocabId($controlledVocabId, $rangeInfo = null) {
		$result =& $this->retrieveRange(
			'SELECT * FROM controlled_vocab_entries WHERE controlled_vocab_id = ? ORDER BY seq',
			array((int) $controlledVocabId),
			$rangeInfo
		);

		$returner = new DAOResultFactory($result, $this, '_fromRow');
		return $returner;
	}

	/**
	 * Update an existing review form element.
	 * @param $controlledVocabEntry ControlledVocabEntry
	 */
	function updateObject(&$controlledVocabEntry) {
		$returner = $this->update(
			'UPDATE	controlled_vocab_entries
			SET	controlled_vocab_id = ?,
				seq = ?
			WHERE	controlled_vocab_entry_id = ?',
			array(
				(int) $controlledVocabEntry->getControlledVocabId(),
				(float) $controlledVocabEntry->getSequence(),
				(int) $controlledVocabEntry->getId()
			)
		);
		$this->updateLocaleFields($controlledVocabEntry);
	}

	/**
	 * Sequentially renumber entries in their sequence order.
	 */
	function resequence($controlledVocabId) {
		$result =& $this->retrieve(
			'SELECT controlled_vocab_entry_id FROM controlled_vocab_entries WHERE controlled_vocab_id = ? ORDER BY seq',
			array((int) $controlledVocabId)
		);

		for ($i=1; !$result->EOF; $i++) {
			list($controlledVocabEntryId) = $result->fields;
			$this->update(
				'UPDATE controlled_vocab_entries SET seq = ? WHERE controlled_vocab_entry_id = ?',
				array(
					(int) $i,
					(int) $controlledVocabEntryId
				)
			);

			$result->MoveNext();
		}

		$result->close();
		unset($result);
	}

	/**
	 * Get the ID of the last inserted controlled vocab.
	 * @return int
	 */
	function getInsertId() {
		return parent::getInsertId('controlled_vocab_entries', 'controlled_vocab_entry_id');
	}
}

?>
