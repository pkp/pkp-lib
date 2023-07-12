<?php

/**
 * @file classes/oai/PKPOAIDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPOAIDAO
 *
 * @ingroup oai
 *
 * @see OAI
 *
 * @brief Base class for DAO operations for the OAI interface.
 */

namespace PKP\oai;

use PKP\plugins\Hook;

abstract class PKPOAIDAO extends \PKP\db\DAO
{
    /** @var OAI parent OAI object */
    public $oai;


    /**
     * Set parent OAI object.
     *
     * @param OAI $oai
     */
    public function setOAI($oai)
    {
        $this->oai = $oai;
    }

    //
    // Resumption tokens
    //
    /**
     * Clear stale resumption tokens.
     */
    public function clearTokens()
    {
        $this->update(
            'DELETE FROM oai_resumption_tokens WHERE expire < ?',
            [time()]
        );
    }

    /**
     * Retrieve a resumption token.
     *
     * @param string $tokenId OAI resumption token
     *
     * @return OAIResumptionToken
     */
    public function getToken($tokenId)
    {
        $result = $this->retrieve('SELECT * FROM oai_resumption_tokens WHERE token = ?', [$tokenId]);
        $row = $result->current();
        return $row ? new OAIResumptionToken($row->token, $row->record_offset, unserialize($row->params), $row->expire) : null;
    }

    /**
     * Insert an OAI resumption token, generating a new ID.
     *
     * @param OAIResumptionToken $token
     *
     * @return OAIResumptionToken
     */
    public function insertToken($token)
    {
        do {
            // Generate unique token ID
            $token->id = md5(uniqid(random_int(0, PHP_INT_MAX), true));
            $result = $this->retrieve(
                'SELECT COUNT(*) AS row_count FROM oai_resumption_tokens WHERE token = ?',
                [$token->id]
            );
            $row = $result->current();
            $val = $row->row_count;
        } while ($val != 0);

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
     *
     * @param int $dataObjectId
     * @param array $setIds optional Objects ids that specify an OAI set,
     * in hierarchical order. If passed, will check for the data object id
     * only inside the specified set.
     *
     * @return bool
     */
    public function recordExists($dataObjectId, $setIds = [])
    {
        return $this->getRecord($dataObjectId, $setIds) ? true : false;
    }

    /**
     * Return OAI record for specified data object.
     *
     * @param int $dataObjectId
     * @param array $setIds optional Objects ids that specify an OAI set,
     * in hierarchical order. If passed, will check for the data object id
     * only inside the specified set.
     *
     * @return OAIRecord
     */
    public function getRecord($dataObjectId, $setIds = [])
    {
        $result = $this->_getRecordsRecordSetQuery($setIds, null, null, null, $dataObjectId);
        $row = $result->first();
        return $row ? $this->_returnRecordFromRow((array) $row) : null;
    }

    /**
     * Return set of OAI records matching specified parameters.
     *
     * @param array $setIds Objects ids that specify an OAI set,
     * in hierarchical order. The returned records will be part
     * of this set.
     * @param int $from timestamp
     * @param int $until timestamp
     * @param string $set setSpec
     * @param int $offset
     * @param int $limit
     * @param int $total
     *
     * @return array OAIRecord
     */
    public function getRecords($setIds, $from, $until, $set, $offset, $limit, &$total)
    {
        $query = $this->_getRecordsRecordSetQuery($setIds, $from, $until, $set);
        $total = $query->count();
        $results = $query->offset($offset)->limit($limit)->get();

        $records = [];
        foreach ($results as $row) {
            $records[] = $this->_returnRecordFromRow((array) $row);
        }
        return $records;
    }

    /**
     * Return set of OAI identifiers matching specified parameters.
     *
     * @param array $setIds Objects ids that specify an OAI set,
     * in hierarchical order. The returned records will be part
     * of this set.
     * @param int $from timestamp
     * @param int $until timestamp
     * @param string $set setSpec
     * @param int $offset
     * @param int $limit
     * @param int $total
     *
     * @return array OAIIdentifier
     */
    public function getIdentifiers($setIds, $from, $until, $set, $offset, $limit, &$total)
    {
        $query = $this->_getRecordsRecordSetQuery($setIds, $from, $until, $set);
        $total = $query->count();
        $results = $query->offset($offset)->limit($limit)->get();

        $records = [];
        foreach ($results as $row) {
            $records[] = $this->_returnIdentifierFromRow((array) $row);
        }
        return $records;
    }

    /**
     * Return the *nix timestamp of the earliest published submission.
     *
     * @param array $setIds optional Objects ids that specify an OAI set,
     * in hierarchical order. If empty, all records from
     * all sets will be included.
     *
     * @return int
     */
    public function getEarliestDatestamp($setIds = [])
    {
        $query = $this->_getRecordsRecordSetQuery($setIds, null, null, null, null, 'last_modified');
        if ($row = $query->first()) {
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
     *
     * @param array $row
     *
     * @return OAIRecord
     */
    public function _returnRecordFromRow($row)
    {
        $record = new OAIRecord();
        $record = $this->_doCommonOAIFromRowOperations($record, $row);

        Hook::call('OAIDAO::_returnRecordFromRow', [&$record, &$row]);

        return $record;
    }

    /**
     * Return OAIIdentifier object from database row.
     *
     * @param array $row
     *
     * @return OAIIdentifier
     */
    public function _returnIdentifierFromRow($row)
    {
        $record = new OAIIdentifier();
        $record = $this->_doCommonOAIFromRowOperations($record, $row);

        Hook::call('OAIDAO::_returnIdentifierFromRow', [&$record, &$row]);

        return $record;
    }

    /**
     * Common operations for OAIRecord and OAIIdentifier object data set.
     *
     * @param OAIRecord|OAIIdentifier $record
     * @param array $row
     *
     * @return OAIRecord|OAIIdentifier
     */
    public function _doCommonOAIFromRowOperations($record, $row)
    {
        $record->datestamp = OAIUtils::UTCDate(strtotime($this->datetimeFromDB($row['last_modified'])));

        if (isset($row['tombstone_id'])) {
            $record->identifier = $row['oai_identifier'];
            $record->sets = [$row['set_spec']];
            $record->status = OAI::OAIRECORD_STATUS_DELETED;
        } else {
            $record->status = OAI::OAIRECORD_STATUS_ALIVE;
            $record = $this->setOAIData($record, $row, $record instanceof \PKP\oai\OAIRecord);
        }

        return $record;
    }

    /**
     * Get a OAI records record set.
     *
     * @param array $setIds Objects ids that specify an OAI set,
     * in hierarchical order.
     * @param int|string $from *nix timestamp or ISO datetime string
     * @param int|string $until *nix timestamp or ISO datetime string
     * @param string $set
     * @param int $submissionId optional
     * @param string $orderBy UNFILTERED
     *
     * @return \Illuminate\Database\Query\Builder
     */
    abstract public function _getRecordsRecordSetQuery($setIds, $from, $until, $set, $submissionId = null, $orderBy = 'journal_id, submission_id');
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\oai\PKPOAIDAO', '\PKPOAIDAO');
}
