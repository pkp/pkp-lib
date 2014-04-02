<?php

/**
 * @file classes/reviewForm/ReviewFormDAO.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ReviewFormDAO
 * @ingroup reviewForm
 * @see ReviewerForm
 *
 * @brief Operations for retrieving and modifying ReviewForm objects.
 *
 */

import('lib.pkp.classes.reviewForm.ReviewForm');

class ReviewFormDAO extends DAO {

	/**
	 * Constructor.
	 */
	function ReviewFormDAO() {
		parent::DAO();
	}

	/**
	 * Retrieve a review form by ID.
	 * @param $reviewFormId int
	 * @param $assocType int optional
	 * @param $assocId int optional
	 * @return ReviewForm
	 */
	function &getReviewForm($reviewFormId, $assocType = null, $assocId = null) {
		$params = array((int) $reviewFormId);
		if ($assocType !== null && $assocId !== null) {
			$params[] = (int) $assocType;
			$params[] = (int) $assocId;
		} else {
			$assocType = $assocId = null;
		}

		$result =& $this->retrieve (
			'SELECT	rf.*
			FROM	review_forms rf
			WHERE	rf.review_form_id = ? ' . (($assocType !== null) ? 'AND rf.assoc_type = ? AND rf.assoc_id = ?' : ''),
			$params
		);

		$returner = null;
		if ($result->RecordCount() != 0) {
			$returner =& $this->_returnReviewFormFromRow($result->GetRowAssoc(false));
		}

		$result->Close();
		unset($result);

		return $returner;
	}

	/**
	 * Retrieve review form complete/incomplete counts by ID.
	 * @param $assocType int
	 * @param $assocId int
	 * @param $completion mixed true for completed forms only, false for incomplete forms only, null for both
	 * @return array
	 */
	function getUseCounts($assocType, $assocId, $completion = null) {
		$params = array((int) $assocType, (int) $assocId);

		$result =& $this->retrieve (
			'SELECT	rf.review_form_id AS review_form_id,
				count(ra.review_form_id) AS rf_count
			FROM	review_forms rf
				LEFT JOIN review_assignments ra ON (
					ra.review_form_id = rf.review_form_id' .
					($completion === true?' AND ra.date_confirmed IS NOT NULL':'') .
					($completion === false?' AND ra.date_notified IS NOT NULL AND ra.date_confirmed IS NULL':'') . '
				)
			WHERE	rf.assoc_type = ? AND rf.assoc_id = ?
			GROUP BY rf.review_form_id',
			$params
		);

		$returner = array();
		while (!$result->EOF) {
			$row = $result->getRowAssoc(false);
			$returner[$row['review_form_id']] = $row['rf_count'];
			$result->MoveNext();
		}
		$result->Close();
		unset($result);

		return $returner;
	}

	/**
	 * Construct a new data object corresponding to this DAO.
	 * @return ReviewForm
	 */
	function newDataObject() {
		return new ReviewForm();
	}

	/**
	 * Internal function to return a ReviewForm object from a row.
	 * @param $row array
	 * @return ReviewForm
	 */
	function &_returnReviewFormFromRow(&$row) {
		$reviewForm = $this->newDataObject();
		$reviewForm->setId($row['review_form_id']);
		$reviewForm->setAssocType($row['assoc_type']);
		$reviewForm->setAssocId($row['assoc_id']);
		$reviewForm->setSequence($row['seq']);
		$reviewForm->setActive($row['is_active']);

		$this->getDataObjectSettings('review_form_settings', 'review_form_id', $row['review_form_id'], $reviewForm);

		HookRegistry::call('ReviewFormDAO::_returnReviewFormFromRow', array(&$reviewForm, &$row));

		return $reviewForm;
	}

	/**
	 * Check if a review form exists with the specified ID.
	 * @param $reviewFormId int
	 * @param $assocType int
	 * @param $assocId int
	 * @return boolean
	 */
	function reviewFormExists($reviewFormId, $assocType, $assocId) {
		$result =& $this->retrieve(
			'SELECT COUNT(*) FROM review_forms WHERE review_form_id = ? AND assoc_type = ? AND assoc_id = ?',
			array($reviewFormId, $assocType, $assocId)
		);
		$returner = isset($result->fields[0]) && $result->fields[0] == 1 ? true : false;

		$result->Close();
		unset($result);

		return $returner;
	}

	/**
	 * Get the list of fields for which data can be localized.
	 * @return array
	 */
	function getLocaleFieldNames() {
		return array('title', 'description');
	}

	/**
	 * Update the localized fields for this table
	 * @param $reviewForm object
	 */
	function updateLocaleFields(&$reviewForm) {
		$this->updateDataObjectSettings('review_form_settings', $reviewForm, array(
			'review_form_id' => $reviewForm->getId()
		));
	}

	/**
	 * Insert a new review form.
	 * @param $reviewForm ReviewForm
	 */
	function insertObject(&$reviewForm) {
		$this->update(
			'INSERT INTO review_forms
				(assoc_type, assoc_id, seq, is_active)
				VALUES
				(?, ?, ?, ?)',
			array(
				$reviewForm->getAssocType(),
				$reviewForm->getAssocId(),
				$reviewForm->getSequence() == null ? 0 : $reviewForm->getSequence(),
				$reviewForm->getActive() ? 1 : 0
			)
		);

		$reviewForm->setId($this->getInsertReviewFormId());
		$this->updateLocaleFields($reviewForm);

		return $reviewForm->getId();
	}

	/**
	 * Update an existing review form.
	 * @param $reviewForm ReviewForm
	 */
	function updateObject(&$reviewForm) {
		$returner = $this->update(
			'UPDATE review_forms
				SET
					assoc_type = ?,
					assoc_id = ?,
					seq = ?,
					is_active = ?
				WHERE review_form_id = ?',
			array(
				$reviewForm->getAssocType(),
				$reviewForm->getAssocId(),
				$reviewForm->getSequence(),
				$reviewForm->getActive(),
				$reviewForm->getId()
			)
		);

		$this->updateLocaleFields($reviewForm);

		return $returner;
	}

	/**
	 * Delete a review form.
	 * @param $reviewForm ReviewForm
	 */
	function deleteObject(&$reviewForm) {
		return $this->deleteById($reviewForm->getId());
	}

	/**
	 * Delete a review form by Id.
	 * @param $reviewFormId int
	 */
	function deleteById($reviewFormId) {
		$reviewFormElementDao =& DAORegistry::getDAO('ReviewFormElementDAO');
		$reviewFormElementDao->deleteByReviewFormId($reviewFormId);

		$this->update('DELETE FROM review_form_settings WHERE review_form_id = ?', array($reviewFormId));
		return $this->update('DELETE FROM review_forms WHERE review_form_id = ?', array($reviewFormId));
	}

	/**
	 * Delete all review forms by assoc Id.
	 * @param $assocType int
	 * @param $assocId int
	 */
	function deleteByAssocId($assocType, $assocId) {
		$reviewForms = $this->getByAssocId($assocType, $assocId);

		while (!$reviewForms->eof()) {
			$reviewForm =& $reviewForms->next();
			$this->deleteById($reviewForm->getId());
		}
	}

	/**
	 * Get all review forms by assoc id.
	 * @param $assocType int
	 * @param $assocId int
	 * @param $rangeInfo RangeInfo (optional)
	 * @return DAOResultFactory containing matching ReviewForms
	 */
	function &getByAssocId($assocType, $assocId, $rangeInfo = null) {
		$result =& $this->retrieveRange(
			'SELECT	*
			FROM	review_forms
			WHERE	assoc_type = ? AND assoc_id = ?
			ORDER BY seq',
			array((int) $assocType, (int) $assocId), $rangeInfo
		);

		$returner = new DAOResultFactory($result, $this, '_returnReviewFormFromRow');
		return $returner;
	}

	/**
	 * Get active review forms for an associated object.
	 * @param $assocType int
	 * @param $assocId int
	 * @param $rangeInfo object RangeInfo object (optional)
	 * @return DAOResultFactory containing matching ReviewForms
	 */
	function &getActiveByAssocId($assocType, $assocId, $rangeInfo = null) {
		$result =& $this->retrieveRange(
			'SELECT	rf.*
			FROM	review_forms rf
				LEFT JOIN review_assignments rac ON (
					rac.review_form_id = rf.review_form_id AND
					rac.date_confirmed IS NOT NULL
				)
				LEFT JOIN review_assignments rai ON (
					rai.review_form_id = rf.review_form_id AND
					rai.date_notified IS NOT NULL AND
					rai.date_confirmed IS NULL
				)
			WHERE	rf.assoc_type = ? AND assoc_id = ? AND rf.is_active = 1
			GROUP BY rf.assoc_type, rf.assoc_id, rf.review_form_id, rf.seq, rf.is_active
			ORDER BY rf.seq',
			array((int) $assocType, (int) $assocId), $rangeInfo
		);

		$returner = new DAOResultFactory($result, $this, '_returnReviewFormFromRow');
		return $returner;
	}

	/**
	 * Get used review forms for an associated object.
	 * @param $assocType int
	 * @param $assocId int
	 * @param $rangeInfo object RangeInfo object (optional)
	 * @return DAOResultFactory containing matching ReviewForms
	 */
	function &getUsedByAssocId($assocType, $assocId, $rangeInfo = null) {
		$result =& $this->retrieveRange(
			'SELECT	rf.*
			FROM	review_forms rf
				LEFT JOIN review_assignments rac ON (
					rac.review_form_id = rf.review_form_id AND
					rac.date_confirmed IS NOT NULL
				)
				LEFT JOIN review_assignments rai ON (
					rai.review_form_id = rf.review_form_id AND
					rai.date_notified IS NOT NULL AND
					rai.date_confirmed IS NULL
				)
			WHERE	rf.assoc_type = ? AND rf.assoc_id = ? AND rf.is_active = 1
			GROUP BY rf.assoc_type, rf.assoc_id, rf.review_form_id, rf.seq, rf.is_active
			HAVING COUNT(rac.review_id) > 0 OR COUNT(rai.review_id) > 0
			ORDER BY rf.seq',
			array((int) $assocType, (int) $assocId), $rangeInfo
		);

		$returner = new DAOResultFactory($result, $this, '_returnReviewFormFromRow');
		return $returner;
	}

	/**
	 * Get unused review forms for an associated object.
	 * @param $assocType int
	 * @param $assocId int
	 * @param $rangeInfo object RangeInfo object (optional)
	 * @return DAOResultFactory containing matching ReviewForms
	 */
	function &getUnusedByAssocId($assocType, $assocId, $rangeInfo = null) {
		$result =& $this->retrieveRange(
			'SELECT	rf.*
			FROM	review_forms rf
				LEFT JOIN review_assignments rac ON (
					rac.review_form_id = rf.review_form_id AND
					rac.date_confirmed IS NOT NULL
				)
				LEFT JOIN review_assignments rai ON (
					rai.review_form_id = rf.review_form_id AND
					rai.date_notified IS NOT NULL AND
					rai.date_confirmed IS NULL
				)
			WHERE	rf.assoc_type = ? AND rf.assoc_id = ?
			GROUP BY rf.assoc_type, rf.assoc_id, rf.review_form_id, rf.seq, rf.is_active
			HAVING COUNT(rac.review_id) = 0 AND COUNT(rai.review_id) = 0
			ORDER BY rf.seq',
			array((int) $assocType, (int) $assocId), $rangeInfo
		);

		$returner = new DAOResultFactory($result, $this, '_returnReviewFormFromRow');
		return $returner;
	}

	/**
	 * Retrieve the IDs and titles of all review forms in an associative array.
	 * @param $assocType int
	 * @param $assocId int
	 * @param $used int
	 * @return array
	 */
	function &getTitlesByAssocId($assocType, $assocId, $used) {
		$reviewFormTitles = array();

		if ($used) {
			$reviewForms =& $this->getUsedByAssocId($assocType, $assocId);
		} else {
			$reviewForms =& $this->getUnusedByAssocId($assocType, $assocId);
		}
		while (($reviewForm =& $reviewForms->next())) {
			$reviewFormTitles[$reviewForm->getId()] = $reviewForm->getLocalizedTitle();
			unset($reviewForm);
		}

		return $reviewFormTitles;
	}

	/**
	 * Check if a review form exists with the specified ID.
	 * @param $reviewFormId int
	 * @param $assocType int optional
	 * @param $assocId int optional
	 * @return boolean
	 */
	function unusedReviewFormExists($reviewFormId, $assocType = null, $assocId = null) {
		$params = array((int) $reviewFormId);
		if ($assocType !== null && $assocId !== null) {
			$params[] = (int) $assocType;
			$params[] = (int) $assocId;
		} else {
			$assocType = $assocId = null;
		}

		$result =& $this->retrieve (
			'SELECT	rf.review_form_id,
				COUNT(rac.review_id) AS complete_count,
				COUNT(rai.review_id) AS incomplete_count
			FROM	review_forms rf
				LEFT JOIN review_assignments rac ON (
					rac.review_form_id = rf.review_form_id AND
					rac.date_confirmed IS NOT NULL
				)
				LEFT JOIN review_assignments rai ON (
					rai.review_form_id = rf.review_form_id AND
					rai.date_notified IS NOT NULL AND
					rai.date_confirmed IS NULL
				)
			WHERE	rf.review_form_id = ?' . ($assocType !== null ? ' AND rf.assoc_type = ? AND rf.assoc_id = ?':'') . '
			GROUP BY rf.review_form_id
			HAVING COUNT(rac.review_id) = 0 AND COUNT(rai.review_id) = 0',
			$params
		);

		$returner = $result->RecordCount() != 0;

		$result->Close();
		unset($result);

		return $returner;
	}

	/**
	 * Sequentially renumber review form in their sequence order.
	 * @param $assocType int
	 * @param $assocId int
	 */
	function resequenceReviewForms($assocType, $assocId) {
		$result =& $this->retrieve(
			'SELECT review_form_id FROM review_forms WHERE assoc_type = ? AND assoc_id = ? ORDER BY seq',
			array((int) $assocType, (int) $assocId)
		);

		for ($i=1; !$result->EOF; $i++) {
			list($reviewFormId) = $result->fields;
			$this->update(
				'UPDATE review_forms SET seq = ? WHERE review_form_id = ?',
				array(
					$i,
					$reviewFormId
				)
			);

			$result->MoveNext();
		}

		$result->Close();
		unset($result);
	}

	/**
	 * Get the ID of the last inserted review form.
	 * @return int
	 */
	function getInsertReviewFormId() {
		return $this->getInsertId('review_forms', 'review_form_id');
	}

	/** DEPRECATED **/

	function insertReviewForm(&$reviewForm) {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		$this->insertObject($reviewForm);
	}
	function updateReviewForm(&$reviewForm) {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		$this->updateObject($reviewForm);
	}
	function deleteReviewForm(&$reviewForm) {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		$this->deleteObject($reviewForm);
	}
	function deleteReviewFormById($reviewFormId, $arg = null) {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		$this->deleteById($reviewFormId);
	}
}

?>
