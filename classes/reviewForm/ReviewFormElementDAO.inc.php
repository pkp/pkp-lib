<?php

/**
 * @file classes/reviewForm/ReviewFormElementDAO.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ReviewFormElementDAO
 * @ingroup reviewForm
 * @see ReviewFormElement
 *
 * @brief Operations for retrieving and modifying ReviewFormElement objects.
 *
 */

import ('lib.pkp.classes.reviewForm.ReviewFormElement');

class ReviewFormElementDAO extends DAO {
	/**
	 * Constructor
	 */
	function ReviewFormElementDAO() {
		parent::DAO();
	}

	/**
	 * Retrieve a review form element by ID.
	 * @param $reviewFormElementId int
	 * @return ReviewFormElement
	 */
	function &getReviewFormElement($reviewFormElementId) {
		$result =& $this->retrieve(
			'SELECT * FROM review_form_elements WHERE review_form_element_id = ?', $reviewFormElementId
		);

		$returner = null;
		if ($result->RecordCount() != 0) {
			$returner =& $this->_returnReviewFormElementFromRow($result->GetRowAssoc(false));
		}

		$result->Close();
		unset($result);

		return $returner;
	}

	/**
	 * Construct a new data object corresponding to this DAO.
	 * @return ReviewFormElement
	 */
	function newDataObject() {
		return new ReviewFormElement();
	}

	/**
	 * Internal function to return a ReviewFormElement object from a row.
	 * @param $row array
	 * @return ReviewFormElement
	 */
	function &_returnReviewFormElementFromRow(&$row) {
		$reviewFormElement = $this->newDataObject();
		$reviewFormElement->setId($row['review_form_element_id']);
		$reviewFormElement->setReviewFormId($row['review_form_id']);
		$reviewFormElement->setSequence($row['seq']);
		$reviewFormElement->setElementType($row['element_type']);
		$reviewFormElement->setRequired($row['required']);
		$reviewFormElement->setIncluded($row['included']);

		$this->getDataObjectSettings('review_form_element_settings', 'review_form_element_id', $row['review_form_element_id'], $reviewFormElement);

		HookRegistry::call('ReviewFormElementDAO::_returnReviewFormElementFromRow', array(&$reviewFormElement, &$row));

		return $reviewFormElement;
	}

	/**
	 * Get the list of fields for which data can be localized.
	 * @return array
	 */
	function getLocaleFieldNames() {
		return parent::getLocaleFieldNames() + array('question', 'possibleResponses');
	}

	/**
	 * Update the localized fields for this table
	 * @param $reviewFormElement object
	 */
	function updateLocaleFields(&$reviewFormElement) {
		$this->updateDataObjectSettings('review_form_element_settings', $reviewFormElement, array(
			'review_form_element_id' => $reviewFormElement->getId()
		));
	}

	/**
	 * Insert a new review form element.
	 * @param $reviewFormElement ReviewFormElement
	 */
	function insertObject(&$reviewFormElement) {
		$this->update(
			'INSERT INTO review_form_elements
				(review_form_id, seq, element_type, required, included)
				VALUES
				(?, ?, ?, ?, ?)',
			array(
				$reviewFormElement->getReviewFormId(),
				$reviewFormElement->getSequence() == null ? 0 : $reviewFormElement->getSequence(),
				$reviewFormElement->getElementType(),
				$reviewFormElement->getRequired() ? 1 : 0,
				$reviewFormElement->getIncluded() ? 1 : 0
			)
		);

		$reviewFormElement->setId($this->getInsertReviewFormElementId());
		$this->updateLocaleFields($reviewFormElement);
		return $reviewFormElement->getId();
	}

	/**
	 * Update an existing review form element.
	 * @param $reviewFormElement ReviewFormElement
	 */
	function updateObject(&$reviewFormElement) {
		$returner = $this->update(
			'UPDATE review_form_elements
				SET
					review_form_id = ?,
					seq = ?,
					element_type = ?,
					required = ?,
					included = ?
				WHERE	review_form_element_id = ?',
			array(
				$reviewFormElement->getReviewFormId(),
				$reviewFormElement->getSequence(),
				$reviewFormElement->getElementType(),
				$reviewFormElement->getRequired(),
				$reviewFormElement->getIncluded(),
				$reviewFormElement->getId()
			)
		);
		$this->updateLocaleFields($reviewFormElement);
		return $returner;
	}

	/**
	 * Delete a review form element.
	 * @param $reviewFormElement reviewFormElement
	 */
	function deleteObject(&$reviewFormElement) {
		return $this->deleteById($reviewFormElement->getId());
	}

	/**
	 * Delete a review form element by ID.
	 * @param $reviewFormElementId int
	 */
	function deleteById($reviewFormElementId) {
		$reviewFormResponseDao =& DAORegistry::getDAO('ReviewFormResponseDAO');
		$reviewFormResponseDao->deleteByReviewFormElementId($reviewFormElementId);

		$this->update('DELETE FROM review_form_element_settings WHERE review_form_element_id = ?', array($reviewFormElementId));
		return $this->update('DELETE FROM review_form_elements WHERE review_form_element_id = ?', array($reviewFormElementId));
	}

	/**
	 * Delete review form elements by review form ID
	 * to be called only when deleting a review form.
	 * @param $reviewFormId int
	 */
	function deleteByReviewFormId($reviewFormId) {
		$reviewFormElements =& $this->getReviewFormElements($reviewFormId);
		foreach ($reviewFormElements as $reviewFormElementId => $reviewFormElement) {
			$this->deleteById($reviewFormElementId);
		}
	}

	/**
	 * Delete a review form element setting
	 * @param $reviewFormElementId int
	 * @param $settingName string
	 * @param $locale string
	 */
	function deleteSetting($reviewFormElementId, $name, $locale = null) {
		$params = array($reviewFormElementId, $name);
		$sql = 'DELETE FROM review_form_element_settings WHERE review_form_element_id = ? AND setting_name = ?';
		if ($locale !== null) {
			$params[] = $locale;
			$sql .= ' AND locale = ?';
		}

		return $this->update($sql, $params);
	}

	/**
	 * Retrieve all elements for a review form.
	 * @param $reviewFormId int
	 * @return array ReviewFormElements ordered by sequence
	 */
	function &getReviewFormElements($reviewFormId) {
		$reviewFormElements = array();

		$result =& $this->retrieve(
			'SELECT * FROM review_form_elements WHERE review_form_id = ? ORDER BY seq',
			$reviewFormId
		);

		while (!$result->EOF) {
			$reviewFormElement =& $this->_returnReviewFormElementFromRow($result->GetRowAssoc(false));
			$reviewFormElements[$reviewFormElement->getId()] = $reviewFormElement;
			$result->MoveNext();
		}

		$result->Close();
		unset($result);

		return $reviewFormElements;
	}

	/**
	 * Retrieve all elements for a review form.
	 * @param $reviewFormId int
	 * @param $rangeInfo object RangeInfo object (optional)
	 * @return DAOResultFactory containing ReviewFormElements ordered by sequence
	 */
	function &getReviewFormElementsByReviewForm($reviewFormId, $rangeInfo = null) {
		$result =& $this->retrieveRange(
			'SELECT * FROM review_form_elements WHERE review_form_id = ? ORDER BY seq',
			$reviewFormId, $rangeInfo
		);

		$returner = new DAOResultFactory($result, $this, '_returnReviewFormElementFromRow');
		return $returner;
	}

	/**
	 * Retrieve ids of all required elements for a review form.
	 * @param $reviewFormId int
	 * return array
	 */
	function getRequiredReviewFormElementIds($reviewFormId) {
		$result =& $this->retrieve(
			'SELECT review_form_element_id FROM review_form_elements WHERE review_form_id = ? AND required = 1 ORDER BY seq',
			$reviewFormId
		);

		$requiredReviewFormElementIds = array();

		while (!$result->EOF) {
			$requiredReviewFormElementIds[] = $result->fields[0];
			$result->MoveNext();
		}

		$result->Close();
		unset($result);

		return $requiredReviewFormElementIds;
	}

	/**
	 * Check if a review form element exists with the specified ID.
	 * @param $reviewFormElementId int
	 * @param $reviewFormId int optional
	 * @return boolean
	 */
	function reviewFormElementExists($reviewFormElementId, $reviewFormId = null) {
		$sql = 'SELECT COUNT(*) FROM review_form_elements WHERE review_form_element_id = ?';
		$params = array($reviewFormElementId);
		if ($reviewFormId !== null) {
			$sql .= ' AND review_form_id = ?';
			$params[] = $reviewFormId;
		}
		$result =& $this->retrieve($sql, $params);

		$returner = isset($result->fields[0]) && $result->fields[0] == 1 ? true : false;

		$result->Close();
		unset($result);

		return $returner;
	}

	/**
	 * Sequentially renumber a review form elements in their sequence order.
	 * @param $reviewFormId int
	 */
	function resequenceReviewFormElements($reviewFormId) {
		$result =& $this->retrieve(
			'SELECT review_form_element_id FROM review_form_elements WHERE review_form_id = ? ORDER BY seq', $reviewFormId
		);

		for ($i=1; !$result->EOF; $i++) {
			list($reviewFormElementId) = $result->fields;
			$this->update(
				'UPDATE review_form_elements SET seq = ? WHERE review_form_element_id = ?',
				array(
					$i,
					$reviewFormElementId
				)
			);

			$result->MoveNext();
		}

		$result->Close();
		unset($result);
	}

	/**
	 * Get the ID of the last inserted review form element.
	 * @return int
	 */
	function getInsertReviewFormElementId() {
		return $this->getInsertId('review_form_elements', 'review_form_element_id');
	}

	/** DEPRECATED **/

	function insertReviewFormElement(&$reviewFormElement) {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		$this->insertObject($reviewFormElement);
	}

	function updateReviewFormElement(&$reviewFormElement) {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		$this->updateObject($reviewFormElement);
	}

	function deleteReviewFormElement(&$reviewFormElement) {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		return $this->deleteObject($reviewFormElement);
	}

	function deleteReviewFormElementById($reviewFormElementId) {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		return $this->deleteById($reviewFormElementId);
	}

	function deleteReviewFormElementsByReviewForm($reviewFormId) {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		return $this->deleteByReviewFormId($reviewFormId);
	}
}

?>
