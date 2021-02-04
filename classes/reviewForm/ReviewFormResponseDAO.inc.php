<?php

/**
 * @file classes/reviewForm/ReviewFormResponseDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewFormResponseDAO
 * @ingroup reviewForm
 * @see ReviewFormResponse
 *
 * @brief Operations for retrieving and modifying ReviewFormResponse objects.
 *
 */

import ('lib.pkp.classes.reviewForm.ReviewFormResponse');

class ReviewFormResponseDAO extends DAO {

	/**
	 * Retrieve a review form response.
	 * @param $reviewId int
	 * @param $reviewFormElementId int
	 * @return ReviewFormResponse
	 */
	function getReviewFormResponse($reviewId, $reviewFormElementId) {
		$result = $this->retrieve(
			'SELECT * FROM review_form_responses WHERE review_id = ? AND review_form_element_id = ?',
			[(int) $reviewId, (int) $reviewFormElementId]
		);
		$row = $result->current();
		return $row ? $this->_returnReviewFormResponseFromRow((array) $row) : null;
	}

	/**
	 * Construct a new data object corresponding to this DAO.
	 * @return ReviewFormResponse
	 */
	function newDataObject() {
		return new ReviewFormResponse();
	}

	/**
	 * Internal function to return a ReviewFormResponse object from a row.
	 * @param $row array
	 * @return ReviewFormResponse
	 */
	function &_returnReviewFormResponseFromRow($row) {
		$responseValue = $this->convertFromDB($row['response_value'], $row['response_type']);
		$reviewFormResponse = $this->newDataObject();

		$reviewFormResponse->setReviewId($row['review_id']);
		$reviewFormResponse->setReviewFormElementId($row['review_form_element_id']);
		$reviewFormResponse->setValue($responseValue);
		$reviewFormResponse->setResponseType($row['response_type']);

		HookRegistry::call('ReviewFormResponseDAO::_returnReviewFormResponseFromRow', array(&$reviewFormResponse, &$row));

		return $reviewFormResponse;
	}

	/**
	 * Insert a new review form response.
	 * @param $reviewFormResponse ReviewFormResponse
	 */
	function insertObject($reviewFormResponse) {
		$this->update(
			'INSERT INTO review_form_responses
				(review_form_element_id, review_id, response_type, response_value)
				VALUES
				(?, ?, ?, ?)',
			[
				$reviewFormResponse->getReviewFormElementId(),
				$reviewFormResponse->getReviewId(),
				$reviewFormResponse->getResponseType(),
				$this->convertToDB($reviewFormResponse->getValue(), $reviewFormResponse->getResponseType())
			]
		);
	}

	/**
	 * Update an existing review form response.
	 * @param $reviewFormResponse ReviewFormResponse
	 */
	function updateObject($reviewFormResponse) {
		$type = $reviewFormResponse->getResponseType();
		$this->update(
			'UPDATE review_form_responses
				SET
					response_type = ?,
					response_value = ?
				WHERE review_form_element_id = ? AND review_id = ?',
			[
				$reviewFormResponse->getResponseType(),
				$this->convertToDB($reviewFormResponse->getValue(), $type),
				$reviewFormResponse->getReviewFormElementId(),
				$reviewFormResponse->getReviewId()
			]
		);
	}

	/**
	 * Delete a review form response.
	 * @param $reviewFormResponse ReviewFormResponse
	 */
	function deleteObject($reviewFormResponse) {
		return $this->deleteById($reviewFormResponse->getReviewId(), $reviewFormResponse->getReviewFormElementId());
	}

	/**
	 * Delete a review form response by ID.
	 * @param $reviewId int
	 * @param $reviewFormElementId int
	 */
	function deleteById($reviewId, $reviewFormElementId) {
		$this->update(
			'DELETE FROM review_form_responses WHERE review_id = ? AND review_form_element_id = ?',
			[$reviewId, $reviewFormElementId]
		);
	}

	/**
	 * Delete review form responses by review ID
	 * @param $reviewId int
	 */
	function deleteByReviewId($reviewId) {
		$this->update('DELETE FROM review_form_responses WHERE review_id = ?', [$reviewId]);
	}

	/**
	 * Delete group membership by user ID
	 * @param $reviewFormElementId int
	 */
	function deleteByReviewFormElementId($reviewFormElementId) {
		$this->update('DELETE FROM review_form_responses WHERE review_form_element_id = ?', [$reviewFormElementId]);
	}

	/**
	 * Retrieve all review form responses for a review in an associative array.
	 * @param $reviewId int
	 * @return array review_form_element_id => array(review form response for this element)
	 */
	function getReviewReviewFormResponseValues($reviewId) {
		$result = $this->retrieveRange('SELECT * FROM review_form_responses WHERE review_id = ?', [(int) $reviewId]);
		$returner = [];
		foreach ($result as $row) {
			$reviewFormResponse = $this->_returnReviewFormResponseFromRow((array) $row);
			$returner[$reviewFormResponse->getReviewFormElementId()] = $reviewFormResponse->getValue();
		}
		return $returner;
	}

	/**
	 * Check if a review form response for the review.
	 * @param $reviewId int
	 * @param $reviewFormElementId int optional
	 * @return boolean
	 */
	function reviewFormResponseExists($reviewId, $reviewFormElementId = null) {
		$params = [(int) $reviewId];
		if ($reviewFormElementId !== null) $params[] = $reviewFormElementId;
		$result = $this->retrieve(
			'SELECT COUNT(*) AS row_count FROM review_form_responses WHERE review_id = ?'
			. ($reviewFormElementId !== null ? ' AND review_form_element_id = ?' : ''),
			$params
		);
		$row = $result->current();
		return $row && $row->row_count > 0;
	}
}


