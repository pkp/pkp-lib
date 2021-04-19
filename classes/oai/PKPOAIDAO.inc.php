<?php

/**
 * @file classes/oai/PKPOAIDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPOAIDAO
 * @ingroup oai
 * @see OAI
 *
 * @brief Base class for DAO operations for the OAI interface.
 */

import('lib.pkp.classes.oai.OAI');

abstract class PKPOAIDAO extends DAO {

 	/** @var OAI parent OAI object */
 	var $oai;


	/**
	 * Set parent OAI object.
	 * @param JournalOAI
	 */
	function setOAI($oai) {
		$this->oai = $oai;
	}

	//
	// Resumption tokens
	//
	/**
	 * Clear stale resumption tokens.
	 */
	function clearTokens() {
		$this->update(
			'DELETE FROM oai_resumption_tokens WHERE expire < ?', [time()]
		);
	}

	/**
	 * Retrieve a resumption token.
	 * @param $tokenId string OAI resumption token
	 * @return OAIResumptionToken
	 */
	function getToken($tokenId) {
		$result = $this->retrieve('SELECT * FROM oai_resumption_tokens WHERE token = ?', [$tokenId]);
		$row = $result->current();
		return $row ? new OAIResumptionToken($row->token, $row->record_offset, unserialize($row->params), $row->expire) : null;
	}

	/**
	 * Insert an OAI resumption token, generating a new ID.
	 * @param $token OAIResumptionToken
	 * @return OAIResumptionToken
	 */
	function insertToken($token) {
		do {
			// Generate unique token ID
			$token->id = md5(uniqid(mt_rand(), true));
			$result = $this->retrieve(
				'SELECT COUNT(*) AS row_count FROM oai_resumption_tokens WHERE token = ?',
				[$token->id]
			);
			$row = $result->current();
			$val = $row->row_count;
		} while($val != 0);

		$this->update(
			'INSERT INTO oai_resumption_tokens (token, record_offset, params, expire)
			VALUES
			(?, ?, ?, ?)',
			[$token->id, $token->offset, serialize($token->params), $token->expire]
		);

		return $token;
	}


	/**
	 * Check if a data object ID specifies a data object.
	 * @param $dataObjectId int
	 * @param $setIds array optional Objects ids that specify an OAI set,
	 * in hierarchical order. If passed, will check for the data object id
	 * only inside the specified set.
	 * @return boolean
	 */
	function recordExists($dataObjectId, $setIds = array()) {
		return $this->getRecord($dataObjectId, $setIds)?true:false;
	}

	/**
	 * Return OAI record for specified data object.
	 * @param $dataObjectId int
	 * @param $setIds array optional Objects ids that specify an OAI set,
	 * in hierarchical order. If passed, will check for the data object id
	 * only inside the specified set.
	 * @return OAIRecord
	 */
	function getRecord($dataObjectId, $setIds = array()) {
		$result = $this->_getRecordsRecordSet($setIds, null, null, null, $dataObjectId);
		$row = $result->current();
		return $row ? $this->_returnRecordFromRow((array) $row) : null;
	}

	/**
	 * Return set of OAI records matching specified parameters.
	 * @param $setIds array Objects ids that specify an OAI set,
	 * in hierarchical order. The returned records will be part
	 * of this set.
	 * @param $from int timestamp
	 * @param $until int timestamp
	 * @param $set string setSpec
	 * @param $offset int
	 * @param $limit int
	 * @param $total int
	 * @return array OAIRecord
	 */
	function getRecords($setIds, $from, $until, $set, $offset, $limit, &$total) {
		$result = $this->_getRecordsRecordSet($setIds, $from, $until, $set);

		for ($i=0; $i<$offset; $i++) {
			if ($result->next()) $total++; // FIXME inefficient
		}
		$records = [];
		for ($count = 0; ($row = $result->current()) && $count < $limit; $count++) {
			$records[] = $this->_returnRecordFromRow((array) $row);
			$total++;
			$result->next();
		}
		while ($result->current()) {
			$total++;
			$result->next();
		}
		return $records;
	}

	/**
	 * Return set of OAI identifiers matching specified parameters.
	 * @param $setIds array Objects ids that specify an OAI set,
	 * in hierarchical order. The returned records will be part
	 * of this set.
	 * @param $from int timestamp
	 * @param $until int timestamp
	 * @param $set string setSpec
	 * @param $offset int
	 * @param $limit int
	 * @param $total int
	 * @return array OAIIdentifier
	 */
	function getIdentifiers($setIds, $from, $until, $set, $offset, $limit, &$total) {
		$result = $this->_getRecordsRecordSet($setIds, $from, $until, $set);

		for ($i=0; $i<$offset; $i++) {
			if ($result->next()) $total++; // FIXME inefficient
		}
		$records = [];
		for ($count = 0; ($row = $result->current()) && $count < $limit; $count++) {
			$records[] = $this->_returnIdentifierFromRow((array) $row);
			$total++;
			$result->next();
		}
		while ($result->current()) {
			$total++;
			$result->next();
		}
		return $records;
	}

	/**
	 * Return the *nix timestamp of the earliest published submission.
	 * @param $setIds array optional Objects ids that specify an OAI set,
	 * in hierarchical order. If empty, all records from
	 * all sets will be included.
	 * @return int
	 */
	function getEarliestDatestamp($setIds = array()) {
		$result = $this->_getRecordsRecordSet($setIds, null, null, null, null, 'last_modified ASC');
		if ($row = $result->current()) {
			$record = $this->_returnRecordFromRow((array) $row);
			return OAIUtils::UTCtoTimestamp($record->datestamp);
		}
		return 0;
	}


	//
	// Private helper methods.
	//
	/**
	 * Return OAIRecord object from database row.
	 * @param $row array
	 * @return OAIRecord
	 */
	function _returnRecordFromRow($row) {
		$record = new OAIRecord();
		$record = $this->_doCommonOAIFromRowOperations($record, $row);

		HookRegistry::call('OAIDAO::_returnRecordFromRow', [&$record, &$row]);

		return $record;
	}

	/**
	 * Return OAIIdentifier object from database row.
	 * @param $row array
	 * @return OAIIdentifier
	 */
	function _returnIdentifierFromRow($row) {
		$record = new OAIIdentifier();
		$record = $this->_doCommonOAIFromRowOperations($record, $row);

		HookRegistry::call('OAIDAO::_returnIdentifierFromRow', [&$record, &$row]);

		return $record;
	}

	/**
	 * Common operations for OAIRecord and OAIIdentifier object data set.
	 * @param $record OAIRecord/OAIIdentifier
	 * @param $row array
	 * @return OAIRecord/OAIIdentifier
	 */
	function _doCommonOAIFromRowOperations($record, $row) {
		$record->datestamp = OAIUtils::UTCDate(strtotime($this->datetimeFromDB($row['last_modified'])));

		if (isset($row['tombstone_id'])) {
			$record->identifier = $row['oai_identifier'];
			$record->sets = array($row['set_spec']);
			$record->status = OAIRECORD_STATUS_DELETED;
		} else {
			$record->status = OAIRECORD_STATUS_ALIVE;
			$record = $this->setOAIData($record, $row, is_a($record, 'OAIRecord'));
		}

		return $record;
	}

	/**
	 * Get a OAI records record set.
	 * @param $setIds array Objects ids that specify an OAI set,
	 * in hierarchical order.
	 * @param $from int/string *nix timestamp or ISO datetime string
	 * @param $until int/string *nix timestamp or ISO datetime string
	 * @param $set string
	 * @param $submissionId int optional
	 * @param $orderBy string UNFILTERED
	 * @return Iterable
	 */
	abstract function _getRecordsRecordSet($setIds, $from, $until, $set, $submissionId = null, $orderBy = 'journal_id, submission_id');
}


