<?php

/**
 * @file classes/user/InterestEntryDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class InterestsEntryDAO
 * @ingroup user
 *
 * @see User
 *
 * @brief Operations for retrieving and modifying a user's review interests.
 */

namespace PKP\user;

use PKP\controlledVocab\ControlledVocabEntryDAO;
use PKP\db\DAOResultFactory;

class InterestEntryDAO extends ControlledVocabEntryDAO
{
    /**
     * Construct a new data object corresponding to this DAO.
     *
     * @return InterestEntry
     */
    public function newDataObject()
    {
        return new InterestEntry();
    }

    /**
     * Get the list of non-localized additional fields to store.
     *
     * @return array
     */
    public function getAdditionalFieldNames()
    {
        return ['interest'];
    }

    /**
     * Retrieve an iterator of controlled vocabulary entries matching a
     * particular controlled vocabulary ID.
     *
     * @param int $controlledVocabId
     * @param RangeInfo $rangeInfo optional range information for result
     * @param string $filter Optional filter to match to beginnings of results
     *
     * @return object DAOResultFactory containing matching CVE objects
     */
    public function getByControlledVocabId($controlledVocabId, $rangeInfo = null, $filter = null)
    {
        $params = [(int) $controlledVocabId];
        if ($filter) {
            $params[] = 'interest';
            $params[] = $filter . '%';
        }

        $result = $this->retrieveRange(
            'SELECT	cve.*
			FROM	controlled_vocab_entries cve
				JOIN user_interests ui ON (cve.controlled_vocab_entry_id = ui.controlled_vocab_entry_id)
				' . ($filter ? 'JOIN controlled_vocab_entry_settings cves ON (cves.controlled_vocab_entry_id = cve.controlled_vocab_entry_id)' : '') . '
			WHERE cve.controlled_vocab_id = ?
			' . ($filter ? 'AND cves.setting_name=? AND LOWER(cves.setting_value) LIKE LOWER(?)' : '') . '
			GROUP BY cve.controlled_vocab_entry_id
			ORDER BY seq',
            $params,
            $rangeInfo
        );

        return new DAOResultFactory($result, $this, '_fromRow');
    }

    /**
     * Retrieve controlled vocab entries matching a list of vocab entry IDs
     *
     * @param array $entryIds
     *
     * @return DAOResultFactory
     */
    public function getByIds($entryIds)
    {
        $entryString = join(',', array_map('intval', $entryIds));

        $result = $this->retrieve(
            'SELECT * FROM controlled_vocab_entries WHERE controlled_vocab_entry_id IN (' . $entryString . ')'
        );

        return new DAOResultFactory($result, $this, '_fromRow');
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\user\InterestEntryDAO', '\InterestEntryDAO');
}
