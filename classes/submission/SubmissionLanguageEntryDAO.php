<?php

/**
 * @file classes/submission/SubmissionLanguageEntryDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionLanguageEntryDAO
 *
 * @ingroup submission
 *
 * @see Submission
 * 
 * @deprecated 3.5
 *
 * @brief Operations for retrieving and modifying a submission's languages
 */

namespace PKP\submission;

use PKP\controlledVocab\ControlledVocabEntryDAO;
use PKP\db\DAOResultFactory;
use PKP\db\DBResultRange;

class SubmissionLanguageEntryDAO extends ControlledVocabEntryDAO
{
    /**
     * Construct a new data object corresponding to this DAO.
     *
     * @return submissionLanguage
     */
    public function newDataObject()
    {
        return new SubmissionLanguage();
    }

    /**
     * Retrieve an iterator of controlled vocabulary entries matching a
     * particular controlled vocabulary ID.
     *
     * @param int $controlledVocabId
     * @param mixed $filter (Not yet supported)
     * @param ?DBResultRange $rangeInfo
     *
     * @return DAOResultFactory<SubmissionLanguage> Object containing matching CVE objects
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
    class_alias('\PKP\submission\SubmissionLanguageEntryDAO', '\SubmissionLanguageEntryDAO');
}
