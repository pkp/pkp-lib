<?php

/**
 * @file classes/context/FooterLinkDAO.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FooterLinkDAO
 * @ingroup context
 * @see FooterLink
 *
 * @brief Operations for retrieving and modifying FooterLink objects.
 */

import ('lib.pkp.classes.context.FooterLink');

class FooterLinkDAO extends DAO {
	/**
	 * Constructor
	 */
	function FooterLinkDAO() {
		parent::DAO();
	}

	/**
	 * Retrieve a footer link by ID.
	 * @param $footerLinkId int
	 * @param $contextId int optional
	 * @return FooterLink
	 */
	function getById($footerLinkId, $contextId = null) {
		$params = array((int) $footerLinkId);
		if ($contextId) $params[] = (int) $contextId;

		$result = $this->retrieve(
			'SELECT	*
			FROM	footerlinks
			WHERE	footerlink_id = ?
			' . ($contextId?' AND context_id = ?':''),
			$params
		);

		$returner = null;
		if ($result->RecordCount() != 0) {
			$returner = $this->_fromRow($result->GetRowAssoc(false));
		}

		$result->Close();
		return $returner;
	}

	/**
	 * Construct a new data object corresponding to this DAO.
	 * @return FooterLink
	 */
	function newDataObject() {
		return new FooterLink();
	}

	/**
	 * Internal function to return an FooterLink object from a row.
	 * @param $row array
	 * @return FooterLink
	 */
	function _fromRow($row) {
		$footerLink = $this->newDataObject();

		$footerLink->setId($row['footerlink_id']);
		$footerLink->setContextId($row['context_id']);
		$footerLink->setUrl($row['url']);
		$footerLink->setCategoryId($row['footer_category_id']);

		$this->getDataObjectSettings('footerlink_settings', 'footerlink_id', $row['footerlink_id'], $footerLink);

		HookRegistry::call('FooterLinkDAO::_fromRow', array(&$footerLink, &$row));

		return $footerLink;
	}

	/**
	 * Get the list of fields for which data can be localized.
	 * @return array
	 */
	function getLocaleFieldNames() {
		return array('title');
	}

	/**
	 * Update the localized fields for this table
	 * @param $footerLink object
	 */
	function updateLocaleFields($footerLink) {
		$this->updateDataObjectSettings(
			'footerlink_settings', $footerLink,
			array(
				'footerlink_id' => $footerLink->getId()
			)
		);
	}

	/**
	 * Insert a new footer link.
	 * @param $footerLink FooterLink
	 * @return int ID of the inserted link.
	 */
	function insertObject($footerLink) {
		$this->update(
			'INSERT INTO footerlinks
				(context_id, footer_category_id, url)
				VALUES
				(?, ?, ?)',
			array(
				(int) $footerLink->getContextId(),
				(int) $footerLink->getCategoryId(),
				$footerLink->getUrl()
			)
		);

		$footerLink->setId($this->getInsertId());
		$this->updateLocaleFields($footerLink);
		return $footerLink->getId();
	}

	/**
	 * Update an existing link.
	 * @param $footerLink FooterLink
	 */
	function updateObject($footerLink) {
		$returner = $this->update(
			'UPDATE	footerlinks
			SET	context_id = ?,
				footer_category_id = ?,
				url = ?
			WHERE	footerlink_id = ?',
			array(
				(int) $footerLink->getContextId(),
				(int) $footerLink->getCategoryId(),
				$footerLink->getUrl(),
				(int) $footerLink->getId()
			)
		);
		$this->updateLocaleFields($footerLink);
		return $returner;
	}

	/**
	 * Delete a link.
	 * @param $footerLink FooterLink
	 */
	function deleteObject($footerLink) {
		return $this->deleteById(
			$footerLink->getId(),
			$footerLink->getContextId()
		);
	}

	/**
	 * Delete a footer link by ID.
	 * @param $footerLinkId int
	 * @param $contextId int optional
	 */
	function deleteById($footerLinkId, $contextId = null) {
		$params = array((int) $footerLinkId);
		if ($contextId) $params[] = (int) $contextId;

		$this->update(
			'DELETE FROM footerlinks
			WHERE footerlink_id = ?
				' . ($contextId?' AND context_id = ?':''),
			$params
		);

		// If the link was deleted (this validates context_id,
		// if specified), delete any associated settings as well.
		if ($this->getAffectedRows()) {
			return $this->update(
				'DELETE FROM footerlink_settings WHERE footerlink_id = ?',
				array((int) $footerLinkId)
			);
		}
	}

	/**
	 * Delete footer link by context ID.
	 * NOTE: This does not delete dependent entries. It is intended
	 * to be called only when deleting a context.
	 * @param $contextId int
	 */
	function deleteByContextId($contextId) {
		$footerlinks = $this->getByContextId($contextId);
		while ($footerLink = $footerlinks->next()) {
			$this->deleteObject($footerLink, $contextId);
		}
	}

	/**
	 * Retrieve all footerlinks for a footer category.
	 * @return DAOResultFactory containing FooterLink objects
	 */
	function getByCategoryId($categoryId, $contextId = null, $rangeInfo = null) {
		$params = array((int) $categoryId);
		if ($contextId) $params[] = (int) $contextId;

		$result = $this->retrieveRange(
			'SELECT	*
			FROM	footerlinks
			WHERE	footer_category_id = ?
			' . ($contextId?' AND context_id = ?':''),
			$params
		);

		$returner = new DAOResultFactory($result, $this, '_fromRow');
		return $returner;
	}

	/**
	 * Get the ID of the last inserted link.
	 * @return int
	 */
	function getInsertId() {
		return $this->_getInsertId('footerlinks', 'footerlink_id');
	}

	/**
	 * Retrieve the maximum number of links in any category, by context ID.
	 * @param int $contextId
	 * @return int
	 */
	function getLargestCategoryTotalByContextId($contextId) {
		$result = $this->retrieve(
			'SELECT count(*) AS total
			FROM footerlinks
			WHERE context_id = ? GROUP BY footer_category_id
			ORDER BY total DESC LIMIT 1', array((int)$contextId));

		$returner = null;
		if ($result->RecordCount() != 0) {
			$row = $result->GetRowAssoc(false);
			$returner = $row['total'];
		}

		$result->Close();
		return $returner;

	}
}

?>
