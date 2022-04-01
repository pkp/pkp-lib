<?php

/**
 * @file classes/submission/SubmissionDisciplineEntryDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionDisciplineEntryDAO
 * @ingroup submission
 *
 * @see Submission
 *
 * @brief Operations for retrieving and modifying a submission's disciplines
 */

namespace PKP\submission;

use PKP\controlledVocab\ControlledVocabEntryDAO;
use PKP\db\DAOResultFactory;
use PKP\db\DBResultRange;

class SubmissionDisciplineEntryDAO extends ControlledVocabEntryDAO
{
    /**
     * Construct a new data object corresponding to this DAO.
     *
     * @return SubmissionDiscipline
     */
    public function newDataObject()
    {
        return new SubmissionDiscipline();
    }

    /**
     * Retrieve an iterator of controlled vocabulary entries matching a
     * particular controlled vocabulary ID.
     *
     * @param int $controlledVocabId
     * @param null $filter (Not yet supported)
     * @param null|DBResultRange $rangeInfo
     *
     * @return DAOResultFactory matching CVE objects
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
    class_alias('\PKP\submission\SubmissionDisciplineEntryDAO', '\SubmissionDisciplineEntryDAO');
}
