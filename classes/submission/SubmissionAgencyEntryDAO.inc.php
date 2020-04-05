<?php

/**
 * @file classes/submission/SubmissionAgencyEntryDAO.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionAgencyEntryDAO
 * @ingroup submission
 * @see Submission
 *
 * @brief Operations for retrieving and modifying a submission's agencies
 */

import('lib.pkp.classes.submission.SubmissionAgency');
import('lib.pkp.classes.controlledVocab.ControlledVocabEntryDAO');

class SubmissionAgencyEntryDAO extends ControlledVocabEntryDAO {

	/**
	 * Construct a new data object corresponding to this DAO.
	 * @return PaperTypeEntry
	 */
	function newDataObject() {
		return new SubmissionAgency();
	}

	/**
	 * Retrieve an iterator of controlled vocabulary entries matching a
	 * particular controlled vocabulary ID.
	 * @param $controlledVocabId int
	 * @param $filter null (Not yet supported)
	 * @return object DAOResultFactory containing matching CVE objects
	 */
	function getByControlledVocabId($controlledVocabId, $rangeInfo = null, $filter = null) {
		assert($filter == null); // Parent class supports this, but this class does not
		$result = $this->retrieveRange(
			'SELECT cve.* FROM controlled_vocab_entries cve WHERE cve.controlled_vocab_id = ? ORDER BY seq',
			array((int) $controlledVocabId),
			$rangeInfo
		);
		return new DAOResultFactory($result, $this, '_fromRow');
	}
}


