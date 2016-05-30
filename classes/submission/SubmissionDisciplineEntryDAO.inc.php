<?php

/**
 * @file classes/submission/SubmissionDisciplineEntryDAO.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2000-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionDisciplineEntryDAO
 * @ingroup submission
 * @see Submission
 *
 * @brief Operations for retrieving and modifying a submission's disciplines
 */

import('lib.pkp.classes.submission.SubmissionDiscipline');
import('lib.pkp.classes.controlledVocab.ControlledVocabEntryDAO');

class SubmissionDisciplineEntryDAO extends ControlledVocabEntryDAO {
	/**
	 * Constructor
	 */
	function SubmissionDisciplineEntryDAO() {
		parent::ControlledVocabEntryDAO();
	}

	/**
	 * Construct a new data object corresponding to this DAO.
	 * @return PaperTypeEntry
	 */
	function newDataObject() {
		return new SubmissionDiscipline();
	}

	/**
	 * Retrieve an iterator of controlled vocabulary entries matching a
	 * particular controlled vocabulary ID.
	 * @param $controlledVocabId int
	 * @param $rangeInfo DBResultRange
	 * @param $version int
	 * @return object DAOResultFactory containing matching CVE objects
	 */
	function getByControlledVocabId($controlledVocabId, $rangeInfo = null, $version = null) {
		$params = array((int) $controlledVocabId);
		if ($version) $params[] = (int) $version;

		$result = $this->retrieveRange(
			'SELECT cve.* FROM controlled_vocab_entries cve WHERE cve.controlled_vocab_id = ? ' . 
			($version ? ' AND version = ? ' : '') . 
			'ORDER BY seq',
			$params,
			$rangeInfo
		);
		
		return new DAOResultFactory($result, $this, '_fromRow');
	}
}

?>
