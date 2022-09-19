<?php

/**
 * @file classes/reviewForm/ReviewFormResponseDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewFormResponseDAO
 * @ingroup reviewForm
 *
 * @see ReviewFormResponse
 *
 * @brief Operations for retrieving and modifying ReviewFormResponse objects.
 *
 */

namespace PKP\reviewForm;

use PKP\plugins\Hook;

class ReviewFormResponseDAO extends \PKP\db\DAO
{
    /**
     * Retrieve a review form response.
     *
     * @param int $reviewId
     * @param int $reviewFormElementId
     *
     * @return ReviewFormResponse
     */
    public function getReviewFormResponse($reviewId, $reviewFormElementId)
    {
        $result = $this->retrieve(
            'SELECT * FROM review_form_responses WHERE review_id = ? AND review_form_element_id = ?',
            [(int) $reviewId, (int) $reviewFormElementId]
        );
        $row = $result->current();
        return $row ? $this->_returnReviewFormResponseFromRow((array) $row) : null;
    }

    /**
     * Construct a new data object corresponding to this DAO.
     *
     * @return ReviewFormResponse
     */
    public function newDataObject()
    {
        return new ReviewFormResponse();
    }

    /**
     * Internal function to return a ReviewFormResponse object from a row.
     *
     * @param array $row
     *
     * @return ReviewFormResponse
     */
    public function &_returnReviewFormResponseFromRow($row)
    {
        $responseValue = $this->convertFromDB($row['response_value'], $row['response_type']);
        $reviewFormResponse = $this->newDataObject();

        $reviewFormResponse->setReviewId($row['review_id']);
        $reviewFormResponse->setReviewFormElementId($row['review_form_element_id']);
        $reviewFormResponse->setValue($responseValue);
        $reviewFormResponse->setResponseType($row['response_type']);

        Hook::call('ReviewFormResponseDAO::_returnReviewFormResponseFromRow', [&$reviewFormResponse, &$row]);

        return $reviewFormResponse;
    }

    /**
     * Insert a new review form response.
     *
     * @param ReviewFormResponse $reviewFormResponse
     */
    public function insertObject($reviewFormResponse)
    {
        $type = $reviewFormResponse->getResponseType();
        $this->update(
            'INSERT INTO review_form_responses
				(review_form_element_id, review_id, response_type, response_value)
				VALUES
				(?, ?, ?, ?)',
            [
                $reviewFormResponse->getReviewFormElementId(),
                $reviewFormResponse->getReviewId(),
                $type,
                $this->convertToDB($reviewFormResponse->getValue(), $type)
            ]
        );
    }

    /**
     * Update an existing review form response.
     *
     * @param ReviewFormResponse $reviewFormResponse
     */
    public function updateObject($reviewFormResponse)
    {
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
     *
     * @param ReviewFormResponse $reviewFormResponse
     */
    public function deleteObject($reviewFormResponse)
    {
        return $this->deleteById($reviewFormResponse->getReviewId(), $reviewFormResponse->getReviewFormElementId());
    }

    /**
     * Delete a review form response by ID.
     *
     * @param int $reviewId
     * @param int $reviewFormElementId
     */
    public function deleteById($reviewId, $reviewFormElementId)
    {
        $this->update(
            'DELETE FROM review_form_responses WHERE review_id = ? AND review_form_element_id = ?',
            [$reviewId, $reviewFormElementId]
        );
    }

    /**
     * Delete review form responses by review ID
     *
     * @param int $reviewId
     */
    public function deleteByReviewId($reviewId)
    {
        $this->update('DELETE FROM review_form_responses WHERE review_id = ?', [$reviewId]);
    }

    /**
     * Delete group membership by user ID
     *
     * @param int $reviewFormElementId
     */
    public function deleteByReviewFormElementId($reviewFormElementId)
    {
        $this->update('DELETE FROM review_form_responses WHERE review_form_element_id = ?', [$reviewFormElementId]);
    }

    /**
     * Retrieve all review form responses for a review in an associative array.
     *
     * @param int $reviewId
     *
     * @return array review_form_element_id => array(review form response for this element)
     */
    public function getReviewReviewFormResponseValues($reviewId)
    {
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
     *
     * @param int $reviewId
     * @param int $reviewFormElementId optional
     *
     * @return bool
     */
    public function reviewFormResponseExists($reviewId, $reviewFormElementId = null)
    {
        $params = [(int) $reviewId];
        if ($reviewFormElementId !== null) {
            $params[] = $reviewFormElementId;
        }
        $result = $this->retrieve(
            'SELECT COUNT(*) AS row_count FROM review_form_responses WHERE review_id = ?'
            . ($reviewFormElementId !== null ? ' AND review_form_element_id = ?' : ''),
            $params
        );
        $row = $result->current();
        return $row && $row->row_count > 0;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\reviewForm\ReviewFormResponseDAO', '\ReviewFormResponseDAO');
}
