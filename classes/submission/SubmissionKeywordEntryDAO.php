<?php

/**
 * @file classes/submission/SubmissionKeywordEntryDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionKeywordEntryDAO
 * @ingroup submission
 *
 * @see Submission
 *
 * @brief Operations for retrieving and modifying a submission's keywords
 */

namespace PKP\submission;

use PKP\controlledVocab\ControlledVocabEntryDAO;
use PKP\db\DAOResultFactory;

class SubmissionKeywordEntryDAO extends ControlledVocabEntryDAO
{
    /**
     * Construct a new data object corresponding to this DAO.
     *
     * @return SubmissionKeyword
     */
    public function newDataObject()
    {
        return new SubmissionKeyword();
    }

    /**
     * Retrieve an iterator of controlled vocabulary entries matching a
     * particular controlled vocabulary ID.
     *
     * @param int $controlledVocabId
     * @param null $filter (Not yet supported)
     * @param null|mixed $rangeInfo
     *
     * @return object DAOResultFactory containing matching CVE objects
     */
    public function getByControlledVocabId($controlledVocabId, $rangeInfo = null, $filter = null)
    {
        assert($filter == null); // Parent class supports this, but this class does not
        $result = $this->retrieveRange(
            'SELECT cve.* FROM controlled_vocab_entries cve WHERE cve.controlled_vocab_id = ? ORDER BY seq',
            [(int) $controlledVocabId],
            $rangeInfo
        );

        return new DAOResultFactory($result, $this, '_fromRow');
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\submission\SubmissionKeywordEntryDAO', '\SubmissionKeywordEntryDAO');
}
