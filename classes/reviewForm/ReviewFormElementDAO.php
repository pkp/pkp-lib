<?php

/**
 * @file classes/reviewForm/ReviewFormElementDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewFormElementDAO
 *
 * @ingroup reviewForm
 *
 * @see ReviewFormElement
 *
 * @brief Operations for retrieving and modifying ReviewFormElement objects.
 *
 */

namespace PKP\reviewForm;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use PKP\db\DAORegistry;
use PKP\db\DAOResultFactory;
use PKP\db\DBResultRange;
use PKP\plugins\Hook;

class ReviewFormElementDAO extends \PKP\db\DAO
{
    /**
     * Retrieve a review form element by ID.
     *
     * @param int $reviewFormElementId Review form element ID
     * @param int $reviewFormId optional
     *
     * @return ReviewFormElement
     */
    public function getById($reviewFormElementId, $reviewFormId = null)
    {
        $params = [(int) $reviewFormElementId];
        if ($reviewFormId) {
            $params[] = (int) $reviewFormId;
        }
        $result = $this->retrieve(
            'SELECT	*
			FROM	review_form_elements
			WHERE	review_form_element_id = ?
			' . ($reviewFormId ? ' AND review_form_id = ?' : ''),
            $params
        );
        $row = $result->current();
        return $row ? $this->_fromRow((array) $row) : null;
    }

    /**
     * Construct a new data object corresponding to this DAO.
     *
     * @return ReviewFormElement
     */
    public function newDataObject()
    {
        return new ReviewFormElement();
    }

    /**
     * Internal function to return a ReviewFormElement object from a row.
     *
     * @param array $row
     *
     * @return ReviewFormElement
     *
     * @hook ReviewFormElementDAO::_fromRow [[&$reviewFormElement, &$row]]
     */
    public function _fromRow($row)
    {
        $reviewFormElement = $this->newDataObject();
        $reviewFormElement->setId($row['review_form_element_id']);
        $reviewFormElement->setReviewFormId($row['review_form_id']);
        $reviewFormElement->setSequence($row['seq']);
        $reviewFormElement->setElementType($row['element_type']);
        $reviewFormElement->setRequired($row['required']);
        $reviewFormElement->setIncluded($row['included']);

        $this->getDataObjectSettings('review_form_element_settings', 'review_form_element_id', $row['review_form_element_id'], $reviewFormElement);

        Hook::call('ReviewFormElementDAO::_fromRow', [&$reviewFormElement, &$row]);

        return $reviewFormElement;
    }

    /**
     * Get the list of fields for which data can be localized.
     */
    public function getLocaleFieldNames(): array
    {
        return ['question', 'description', 'possibleResponses'];
    }

    /**
     * Update the localized fields for this table
     *
     * @param object $reviewFormElement
     */
    public function updateLocaleFields($reviewFormElement)
    {
        $this->updateDataObjectSettings('review_form_element_settings', $reviewFormElement, [
            'review_form_element_id' => (int) $reviewFormElement->getId()
        ]);
    }

    /**
     * Insert a new review form element.
     *
     * @param ReviewFormElement $reviewFormElement
     *
     * @return int Review form element ID
     */
    public function insertObject($reviewFormElement)
    {
        $this->update(
            'INSERT INTO review_form_elements
				(review_form_id, seq, element_type, required, included)
			VALUES
				(?, ?, ?, ?, ?)',
            [
                (int) $reviewFormElement->getReviewFormId(),
                $reviewFormElement->getSequence() == null ? 0 : (float) $reviewFormElement->getSequence(),
                (int) $reviewFormElement->getElementType(),
                (int) $reviewFormElement->getRequired(),
                (int) $reviewFormElement->getIncluded(),
            ]
        );

        $reviewFormElement->setId($this->getInsertId());
        $this->updateLocaleFields($reviewFormElement);
        return $reviewFormElement->getId();
    }

    /**
     * Update an existing review form element.
     *
     * @param ReviewFormElement $reviewFormElement
     */
    public function updateObject($reviewFormElement)
    {
        $returner = $this->update(
            'UPDATE review_form_elements
				SET	review_form_id = ?,
					seq = ?,
					element_type = ?,
					required = ?,
					included = ?
				WHERE	review_form_element_id = ?',
            [
                (int) $reviewFormElement->getReviewFormId(),
                (float) $reviewFormElement->getSequence(),
                (int) $reviewFormElement->getElementType(),
                (int) $reviewFormElement->getRequired(),
                (int) $reviewFormElement->getIncluded(),
                (int) $reviewFormElement->getId()
            ]
        );
        $this->updateLocaleFields($reviewFormElement);
        return $returner;
    }

    /**
     * Delete a review form element.
     *
     * @param reviewFormElement $reviewFormElement
     */
    public function deleteObject($reviewFormElement)
    {
        return $this->deleteById($reviewFormElement->getId());
    }

    /**
     * Delete a review form element by ID.
     *
     * @param int $reviewFormElementId
     */
    public function deleteById($reviewFormElementId)
    {
        $reviewFormResponseDao = DAORegistry::getDAO('ReviewFormResponseDAO'); /** @var ReviewFormResponseDAO $reviewFormResponseDao */
        $reviewFormResponseDao->deleteByReviewFormElementId($reviewFormElementId);

        $this->update('DELETE FROM review_form_element_settings WHERE review_form_element_id = ?', [(int) $reviewFormElementId]);
        return $this->update('DELETE FROM review_form_elements WHERE review_form_element_id = ?', [(int) $reviewFormElementId]);
    }

    /**
     * Delete review form elements by review form ID
     * to be called only when deleting a review form.
     *
     * @param int $reviewFormId
     */
    public function deleteByReviewFormId($reviewFormId)
    {
        $reviewFormElements = $this->getByReviewFormId($reviewFormId);
        while ($reviewFormElement = $reviewFormElements->next()) {
            $this->deleteById($reviewFormElement->getId());
        }
    }

    /**
     * Delete a review form element setting
     *
     * @param int $reviewFormElementId
     * @param string $locale
     */
    public function deleteSetting($reviewFormElementId, $name, $locale = null)
    {
        $params = [(int) $reviewFormElementId, $name];
        if ($locale) {
            $params[] = $locale;
        }

        $this->update(
            'DELETE FROM review_form_element_settings
			WHERE review_form_element_id = ? AND setting_name = ?
			' . ($locale ? ' AND locale = ?' : ''),
            $params
        );
    }

    /**
     * Retrieve all elements for a review form.
     *
     * @param int $reviewFormId
     * @param ?DBResultRange $rangeInfo (optional)
     * @param bool $included True for only included comments; false for non-included; null for both
     *
     * @return DAOResultFactory<ReviewFormElement> containing ReviewFormElements ordered by sequence
     */
    public function getByReviewFormId($reviewFormId, $rangeInfo = null, $included = null)
    {
        $q = DB::table('review_form_elements', 'rfe')
            ->where('rfe.review_form_id', '=', $reviewFormId)
            ->when($included !== null, fn (Builder $q) => $q->where('rfe.included', '=', $included))
            ->select('rfe.*')
            ->orderBy('rfe.seq');
        $result = $this->retrieveRange($q, [], $rangeInfo);
        return new DAOResultFactory($result, $this, '_fromRow', [], $q, [], $rangeInfo);
    }

    /**
     * Retrieve ids of all required elements for a review form.
     *
     * @param int $reviewFormId
     * return array
     */
    public function getRequiredReviewFormElementIds($reviewFormId)
    {
        $result = $this->retrieve(
            'SELECT review_form_element_id FROM review_form_elements WHERE review_form_id = ? AND required = 1 ORDER BY seq',
            [(int) $reviewFormId]
        );

        $requiredReviewFormElementIds = [];
        foreach ($result as $row) {
            $requiredReviewFormElementIds[] = $row->review_form_element_id;
        }
        return $requiredReviewFormElementIds;
    }

    /**
     * Check if a review form element exists with the specified ID.
     *
     * @param int $reviewFormElementId
     * @param int $reviewFormId optional
     *
     * @return bool
     */
    public function reviewFormElementExists($reviewFormElementId, $reviewFormId = null)
    {
        $params = [(int) $reviewFormElementId];
        if ($reviewFormId) {
            $params[] = (int) $reviewFormId;
        }

        $result = $this->retrieve(
            'SELECT	COUNT(*) AS row_count
			FROM	review_form_elements
			WHERE	review_form_element_id = ?
				' . ($reviewFormId ? ' AND review_form_id = ?' : ''),
            $params
        );
        $row = $result->current();
        return $row ? $row->row_count == 1 : false;
    }

    /**
     * Sequentially renumber a review form elements in their sequence order.
     *
     * @param int $reviewFormId
     */
    public function resequenceReviewFormElements($reviewFormId)
    {
        $result = $this->retrieve(
            'SELECT review_form_element_id FROM review_form_elements WHERE review_form_id = ? ORDER BY seq',
            [(int) $reviewFormId]
        );

        for ($i = 1; $row = $result->current(); $i++) {
            $this->update('UPDATE review_form_elements SET seq = ? WHERE review_form_element_id = ?', [$i, $row->review_form_element_id]);
            $result->next();
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\reviewForm\ReviewFormElementDAO', '\ReviewFormElementDAO');
}
