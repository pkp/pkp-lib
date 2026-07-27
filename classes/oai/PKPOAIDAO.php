<?php

/**
 * @file classes/oai/PKPOAIDAO.php
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2003-2026 John Willinsky
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

use Illuminate\Database\Query\Builder;
use PKP\db\DAO;
use PKP\plugins\Hook;

abstract class PKPOAIDAO extends DAO
{
    /** @var OAI parent OAI object */
    public OAI $oai;

    /**
     * Set the parent OAI object.
     */
    public function setOAI(OAI $oai): void
    {
        $this->oai = $oai;
    }

    //
    // Resumption tokens
    //
    /**
     * Clear stale resumption tokens.
     */
    public function clearTokens(): void
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
     */
    public function getToken(string $tokenId): ?OAIResumptionToken
    {
        $result = $this->retrieve('SELECT * FROM oai_resumption_tokens WHERE token = ?', [$tokenId]);
        $row = $result->current();
        return $row ?
            new OAIResumptionToken($row->token, $row->record_offset, unserialize($row->params), $row->expire) :
            null;
    }

    /**
     * Insert an OAI resumption token, generating a new ID.
     */
    public function insertToken(OAIResumptionToken $token): OAIResumptionToken
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
     * @param array $setIds optional Objects ids that specify an OAI set,
     * in hierarchical order. If passed, will check for the data object id
     * only inside the specified set.
     * @param int|null $publicationId optional. If passed, restrict to the
     * record representing that specific object version.
     */
    public function recordExists(int $dataObjectId, array $setIds = [], ?int $publicationId = null): bool
    {
        return (bool)$this->getRecord($dataObjectId, $setIds, $publicationId);
    }

    /**
     * Return an OAI record for the specified data object.
     *
     * @param array $setIds optional Objects ids that specify an OAI set,
     * in hierarchical order. If passed, will check for the data object id
     * only inside the specified set.
     * @param int|null $publicationId optional. If passed, return the record
     * representing that specific object version.
     */
    public function getRecord(int $dataObjectId, array $setIds = [], ?int $publicationId = null): ?OAIRecord
    {
        $result = $this->getRecordsRecordSetQuery(
            $setIds,
            null,
            null,
            null,
            $dataObjectId,
            publicationId: $publicationId
        );
        $row = $result->first();
        return $row ? $this->returnRecordFromRow((array) $row) : null;
    }

    /**
     * Return a set of OAI records matching specified parameters.
     *
     * @param array $setIds Objects ids that specify an OAI set,
     * in hierarchical order. The returned records will be part
     * of this set.
     *
     * @return array<OAIRecord>
     */
    public function getRecords(
        array $setIds,
        ?int $from,
        ?int $until,
        ?string $set,
        int $offset,
        int $limit,
        int &$total
    ): array {
        $query = $this->getRecordsRecordSetQuery($setIds, $from, $until, $set);
        $total = $query->getCountForPagination();
        $results = $query->offset($offset)->limit($limit)->get();

        $records = [];
        foreach ($results as $row) {
            $records[] = $this->returnRecordFromRow((array) $row);
        }
        return $records;
    }

    /**
     * Return a set of OAI identifiers matching specified parameters.
     *
     * @param array $setIds Objects ids that specify an OAI set,
     * in hierarchical order. The returned records will be part
     * of this set.
     *
     * @return array<OAIIdentifier>
     */
    public function getIdentifiers(
        array $setIds,
        ?int $from,
        ?int $until,
        ?string $set,
        int $offset,
        int $limit,
        int &$total
    ): array {
        $query = $this->getRecordsRecordSetQuery($setIds, $from, $until, $set);
        $total = $query->getCountForPagination();
        $results = $query->offset($offset)->limit($limit)->get();

        $records = [];
        foreach ($results as $row) {
            $records[] = $this->returnIdentifierFromRow((array) $row);
        }
        return $records;
    }

    /**
     * Return the *nix timestamp of the earliest published submission.
     *
     * @param array $setIds optional Objects ids that specify an OAI set,
     * in hierarchical order. If empty, all records from
     * all sets will be included.
     */
    public function getEarliestDatestamp(array $setIds = []): int
    {
        $query = $this->getRecordsRecordSetQuery($setIds, null, null, null, null, 'last_modified');
        if ($row = $query->first()) {
            $record = $this->returnRecordFromRow((array) $row);
            return OAIUtils::UTCtoTimestamp($record->datestamp);
        }
        return 0;
    }

    //
    // Private helper methods.
    //
    /**
     * Return the OAIRecord object from the database row.
     *
     * @hook OAIDAO::_returnRecordFromRow [[&$record, &$row]]
     */
    public function returnRecordFromRow(array $row): OAIRecord
    {
        $record = new OAIRecord();
        $record = $this->doCommonOAIFromRowOperations($record, $row);

        Hook::call('OAIDAO::_returnRecordFromRow', [&$record, &$row]);

        return $record;
    }

    /**
     * Return an OAIIdentifier object from the database row.
     *
     * @hook OAIDAO::_returnIdentifierFromRow [[&$record, &$row]]
     */
    public function returnIdentifierFromRow(array $row): OAIIdentifier
    {
        $record = new OAIIdentifier();
        $record = $this->doCommonOAIFromRowOperations($record, $row);

        Hook::call('OAIDAO::_returnIdentifierFromRow', [&$record, &$row]);

        return $record;
    }

    /**
     * Common operations for OAIRecord and OAIIdentifier object data set.
     */
    public function doCommonOAIFromRowOperations(OAIRecord|OAIIdentifier $record, array $row): OAIRecord|OAIIdentifier
    {
        $record->datestamp = OAIUtils::UTCDate(strtotime($this->datetimeFromDB($row['last_modified'])));

        if (isset($row['tombstone_id'])) {
            $record->identifier = $row['oai_identifier'];
            $record->sets = [$row['set_spec']];
            $record->status = OAI::OAIRECORD_STATUS_DELETED;
        } else {
            $record->status = OAI::OAIRECORD_STATUS_ALIVE;
            $record = $this->setOAIData($record, $row, $record instanceof OAIRecord);
        }

        return $record;
    }

    /**
     * Get an OAI records record set.
     *
     * @param array $setIds Objects ids that specify an OAI set,
     * in hierarchical order.
     * @param int|string|null $from *nix timestamp or ISO datetime string
     * @param int|string|null $until *nix timestamp or ISO datetime string
     * @param ?string $set
     * @param ?int $submissionId optional
     * @param string $orderBy UNFILTERED
     * @param ?int $publicationId optional. If passed, restrict the record set to
     * the given object version (used to expose per-version OAI records).
     */
    abstract public function getRecordsRecordSetQuery(
        array $setIds,
        int|string|null $from,
        int|string|null $until,
        ?string $set,
        ?int $submissionId = null,
        string $orderBy = 'journal_id, submission_id',
        ?int $publicationId = null
    ): Builder;
}
