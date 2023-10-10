<?php

/**
 * @file classes/reviewForm/ReviewFormDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewFormDAO
 *
 * @ingroup reviewForm
 *
 * @see ReviewerForm
 *
 * @brief Operations for retrieving and modifying ReviewForm objects.
 *
 */

namespace PKP\reviewForm;

use PKP\db\DAORegistry;
use PKP\db\DAOResultFactory;
use PKP\db\DBResultRange;
use PKP\plugins\Hook;

class ReviewFormDAO extends \PKP\db\DAO
{
    /**
     * Retrieve a review form by ID.
     *
     * @param int $reviewFormId
     * @param int $assocType optional
     * @param int $assocId optional
     *
     * @return ReviewForm
     */
    public function getById($reviewFormId, $assocType = null, $assocId = null)
    {
        $params = [(int) $reviewFormId];
        if ($assocType) {
            $params[] = (int) $assocType;
            $params[] = (int) $assocId;
        }

        $result = $this->retrieve(
            'SELECT	rf.*,
                (SELECT COUNT(*) FROM review_assignments ra WHERE ra.date_completed IS NOT NULL AND ra.declined <> 1 AND ra.review_form_id = rf.review_form_id) AS complete_count,
                (SELECT COUNT(*) FROM review_assignments ra WHERE ra.date_completed IS NULL AND ra.declined <> 1 AND ra.review_form_id = rf.review_form_id) AS incomplete_count
            FROM review_forms rf
            WHERE rf.review_form_id = ? AND rf.assoc_type = ? AND rf.assoc_id = ?',
            $params
        );
        $row = $result->current();
        return $row ? $this->_fromRow((array) $row) : null;
    }

    /**
     * Construct a new data object corresponding to this DAO.
     *
     * @return ReviewForm
     */
    public function newDataObject()
    {
        return new ReviewForm();
    }

    /**
     * Internal function to return a ReviewForm object from a row.
     *
     * @param array $row
     *
     * @return ReviewForm
     */
    public function _fromRow($row)
    {
        $reviewForm = $this->newDataObject();
        $reviewForm->setId($row['review_form_id']);
        $reviewForm->setAssocType($row['assoc_type']);
        $reviewForm->setAssocId($row['assoc_id']);
        $reviewForm->setSequence($row['seq']);
        $reviewForm->setActive($row['is_active']);
        $reviewForm->setCompleteCount($row['complete_count']);
        $reviewForm->setIncompleteCount($row['incomplete_count']);

        $this->getDataObjectSettings('review_form_settings', 'review_form_id', $row['review_form_id'], $reviewForm);

        Hook::call('ReviewFormDAO::_fromRow', [&$reviewForm, &$row]);

        return $reviewForm;
    }

    /**
     * Check if a review form exists with the specified ID.
     *
     * @param int $reviewFormId
     * @param int $assocType
     * @param int $assocId
     *
     * @return bool
     */
    public function reviewFormExists($reviewFormId, $assocType, $assocId)
    {
        $result = $this->retrieve(
            'SELECT COUNT(*) AS row_count FROM review_forms WHERE review_form_id = ? AND assoc_type = ? AND assoc_id = ?',
            [(int) $reviewFormId, (int) $assocType, (int) $assocId]
        );
        $row = $result->current();
        return $row ? $row->row_count == 1 : false;
    }

    /**
     * Get the list of fields for which data can be localized.
     *
     * @return array
     */
    public function getLocaleFieldNames()
    {
        return ['title', 'description'];
    }

    /**
     * Update the localized fields for this table
     *
     * @param object $reviewForm
     */
    public function updateLocaleFields(&$reviewForm)
    {
        $this->updateDataObjectSettings('review_form_settings', $reviewForm, [
            'review_form_id' => $reviewForm->getId()
        ]);
    }

    /**
     * Insert a new review form.
     *
     * @param ReviewForm $reviewForm
     */
    public function insertObject($reviewForm)
    {
        $this->update(
            'INSERT INTO review_forms
				(assoc_type, assoc_id, seq, is_active)
				VALUES
				(?, ?, ?, ?)',
            [
                (int) $reviewForm->getAssocType(),
                (int) $reviewForm->getAssocId(),
                $reviewForm->getSequence() == null ? 0 : (float) $reviewForm->getSequence(),
                $reviewForm->getActive() ? 1 : 0
            ]
        );

        $reviewForm->setId($this->getInsertId());
        $this->updateLocaleFields($reviewForm);

        return $reviewForm->getId();
    }

    /**
     * Update an existing review form.
     *
     * @param ReviewForm $reviewForm
     */
    public function updateObject($reviewForm)
    {
        $returner = $this->update(
            'UPDATE review_forms
				SET
					assoc_type = ?,
					assoc_id = ?,
					seq = ?,
					is_active = ?
				WHERE review_form_id = ?',
            [
                (int) $reviewForm->getAssocType(),
                (int) $reviewForm->getAssocId(),
                (float) $reviewForm->getSequence(),
                $reviewForm->getActive() ? 1 : 0,
                (int) $reviewForm->getId()
            ]
        );

        $this->updateLocaleFields($reviewForm);

        return $returner;
    }

    /**
     * Delete a review form.
     *
     * @param ReviewForm $reviewForm
     */
    public function deleteObject($reviewForm)
    {
        return $this->deleteById($reviewForm->getId());
    }

    /**
     * Delete a review form by Id.
     *
     * @param int $reviewFormId
     */
    public function deleteById($reviewFormId)
    {
        $reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO'); /** @var ReviewFormElementDAO $reviewFormElementDao */
        $reviewFormElementDao->deleteByReviewFormId($reviewFormId);

        $this->update('DELETE FROM review_form_settings WHERE review_form_id = ?', [(int) $reviewFormId]);
        $this->update('DELETE FROM review_forms WHERE review_form_id = ?', [(int) $reviewFormId]);
    }

    /**
     * Delete all review forms by assoc Id.
     *
     * @param int $assocType
     * @param int $assocId
     */
    public function deleteByAssoc($assocType, $assocId)
    {
        $reviewForms = $this->getByAssocId($assocType, $assocId);

        while ($reviewForm = $reviewForms->next()) {
            $this->deleteById($reviewForm->getId());
        }
    }

    /**
     * Get all review forms by assoc id.
     *
     * @param int $assocType
     * @param int $assocId
     * @param ?DBResultRange $rangeInfo (optional)
     *
     * @return DAOResultFactory<ReviewForm> Object containing matching ReviewForms
     */
    public function getByAssocId($assocType, $assocId, $rangeInfo = null)
    {
        $result = $this->retrieveRange(
            'SELECT rf.*,
                (SELECT COUNT(*) FROM review_assignments ra WHERE ra.date_completed IS NOT NULL AND ra.declined <> 1 AND ra.review_form_id = rf.review_form_id) AS complete_count,
                (SELECT COUNT(*) FROM review_assignments ra WHERE ra.date_completed IS NULL AND ra.declined <> 1 AND ra.review_form_id = rf.review_form_id) AS incomplete_count
            FROM	review_forms rf
            WHERE   rf.assoc_type = ? AND rf.assoc_id = ?
            ORDER BY rf.seq',
            [(int) $assocType, (int) $assocId],
            $rangeInfo
        );

        return new DAOResultFactory($result, $this, '_fromRow');
    }

    /**
     * Get active review forms for an associated object.
     *
     * @param int $assocType
     * @param int $assocId
     * @param ?DBResultRange $rangeInfo (optional)
     *
     * @return DAOResultFactory<ReviewForm> containing matching ReviewForms
     */
    public function getActiveByAssocId($assocType, $assocId, $rangeInfo = null)
    {
        $result = $this->retrieveRange(
            'SELECT rf.*,
                (SELECT COUNT(*) FROM review_assignments ra WHERE ra.date_completed IS NOT NULL AND ra.declined <> 1 AND ra.review_form_id = rf.review_form_id) AS complete_count,
                (SELECT COUNT(*) FROM review_assignments ra WHERE ra.date_completed IS NULL AND ra.declined <> 1 AND ra.review_form_id = rf.review_form_id) AS incomplete_count
                FROM    review_forms rf
                WHERE	rf.assoc_type = ? AND rf.assoc_id = ? AND rf.is_active = 1
                ORDER BY rf.seq',
            [(int) $assocType, (int) $assocId],
            $rangeInfo
        );

        return new DAOResultFactory($result, $this, '_fromRow');
    }

    /**
     * Check if a review form exists with the specified ID.
     *
     * @param int $reviewFormId
     * @param int $assocType optional
     * @param int $assocId optional
     *
     * @return bool
     */
    public function unusedReviewFormExists($reviewFormId, $assocType = null, $assocId = null)
    {
        $reviewForm = $this->getById($reviewFormId, $assocType, $assocId);
        if (!$reviewForm) {
            return false;
        }
        if ($reviewForm->getCompleteCount() != 0 || $reviewForm->getIncompleteCount() != 0) {
            return false;
        }
        return true;
    }

    /**
     * Sequentially renumber review form in their sequence order.
     *
     * @param int $assocType
     * @param int $assocId
     */
    public function resequenceReviewForms($assocType, $assocId)
    {
        $result = $this->retrieve('SELECT review_form_id FROM review_forms WHERE assoc_type = ? AND assoc_id = ? ORDER BY seq', [(int) $assocType, (int) $assocId]);

        for ($i = 1; $row = $result->current(); $i++) {
            $this->update('UPDATE review_forms SET seq = ? WHERE review_form_id = ?', [$i, $row->review_form_id]);
            $result->next();
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\reviewForm\ReviewFormDAO', '\ReviewFormDAO');
}
