<?php

/**
 * @file classes/context/FooterCategoryDAO.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FooterCategoryDAO
 * @ingroup context
 * @see FooterCategory
 *
 * @brief Operations for retrieving and modifying FooterCategory objects.
 */

import ('lib.pkp.classes.context.FooterCategory');

class FooterCategoryDAO extends DAO {
	/**
	 * Constructor
	 */
	function FooterCategoryDAO() {
		parent::DAO();
	}

	/**
	 * Retrieve a category by ID.
	 * @param $categoryId int
	 * @param $contextId int optional
	 * @return FooterCategory
	 */
	function getById($categoryId, $contextId = null) {
		$params = array((int) $categoryId);
		if ($contextId) $params[] = (int) $contextId;

		$result = $this->retrieve(
			'SELECT *
			FROM footer_categories
			WHERE footer_category_id = ?
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
	 * Retrieve a category by path.
	 * @param $path string
	 * @param $contextId int
	 * @return FooterCategory
	 */
	function getByPath($path, $contextId) {
		$returner = null;
		$result = $this->retrieve(
			'SELECT * FROM footer_categories WHERE path = ? AND context_id = ?',
			array((string) $path, (int) $contextId)
		);

		if ($result->RecordCount() != 0) {
			$returner = $this->_fromRow($result->GetRowAssoc(false));
		}

		$result->Close();
		return $returner;
	}

	/**
	 * Check if a category exists with a specified path.
	 * @param $path string the path for the category
	 * @param $contextId int the context (optional)
	 * @return boolean
	 */
	function categoryExistsByPath($path, $contextId = null) {
		$params = array($path);
		if ($contextId) $params[] = (int) $contextId;

		$result = $this->retrieve(
			'SELECT COUNT(*) FROM footer_categories WHERE path = ?
			' . ($contextId?' AND context_id = ?':''),
			$params
		);
		$returner = isset($result->fields[0]) && $result->fields[0] == 1 ? true : false;

		$result->Close();
		return $returner;
	}

	/**
	 * Construct a new data object corresponding to this DAO.
	 * @return FooterCategory
	 */
	function newDataObject() {
		return new FooterCategory();
	}

	/**
	 * Internal function to return a FooterCategory object from a row.
	 * @param $row array
	 * @return FooterCategory
	 */
	function _fromRow($row) {
		$category = $this->newDataObject();

		$category->setId($row['footer_category_id']);
		$category->setContextId($row['context_id']);
		$category->setPath($row['path']);

		$this->getDataObjectSettings('footer_category_settings', 'footer_category_id', $row['footer_category_id'], $category);

		HookRegistry::call('FooterCategoryDAO::_fromRow', array(&$category, &$row));

		return $category;
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
	 * @param $category object
	 */
	function updateLocaleFields($category) {
		$this->updateDataObjectSettings(
			'footer_category_settings', $category,
			array(
				'footer_category_id' => $category->getId()
			)
		);
	}

	/**
	 * Insert a new category.
	 * @param $category FooterCategory
	 * @return int ID of the inserted category.
	 */
	function insertObject($category) {
		$this->update(
			'INSERT INTO footer_categories
				(context_id, path)
				VALUES
				(?, ?)',
			array(
				(int) $category->getContextId(),
				$category->getPath()
			)
		);

		$category->setId($this->getInsertId());
		$this->updateLocaleFields($category);
		return $category->getId();
	}

	/**
	 * Update an existing category.
	 * @param $category FooterCategory
	 */
	function updateObject($category) {
		$returner = $this->update(
			'UPDATE footer_categories
			SET	context_id = ?,
				path = ?
			WHERE footer_category_id = ?',
			array(
				(int) $category->getContextId(),
				$category->getPath(),
				(int) $category->getId()
			)
		);
		$this->updateLocaleFields($category);
		return $returner;
	}

	/**
	 * Delete a category.
	 * @param $category FooterCategory
	 */
	function deleteObject($category) {
		return $this->deleteById(
			$category->getId(),
			$category->getContextId()
		);
	}

	/**
	 * Delete a category by ID.
	 * @param $categoryId int
	 * @param $contextId int optional
	 */
	function deleteById($categoryId, $contextId = null) {
		$params = array((int) $categoryId);
		if ($contextId) $params[] = (int) $contextId;

		$this->update(
			'DELETE FROM footer_categories
			WHERE footer_category_id = ?
				' . ($contextId?' AND context_id = ?':''),
			$params
		);

		// If the category was deleted (this validates context_id,
		// if specified), delete any associated settings as well.
		if ($this->getAffectedRows()) {
			$this->update(
				'DELETE FROM footer_category_settings WHERE footer_category_id = ?',
				array((int) $categoryId)
			);

			return true;
		}
	}

	/**
	 * Delete category by context ID
	 * NOTE: This does not delete dependent entries. It is intended
	 * to be called only when deleting a context.
	 * @param $contextId int
	 */
	function deleteByContextId($contextId) {
		$categories = $this->getByContextId($contextId);
		while ($category = $categories->next()) {
			$this->deleteObject($category, $contextId);
		}
	}

	/**
	 * Retrieve all categories for a context.
	 * @param $contextId int Context ID
	 * @param $rangeInfo Object Optional range info for results paging
	 * @return DAOResultFactory containing FooterCategory ordered by sequence
	 */
	function getByContextId($contextId, $rangeInfo = null) {
		$result = $this->retrieveRange(
			'SELECT *
			FROM footer_categories
			WHERE context_id = ?',
			array((int) $contextId)
		);

		return new DAOResultFactory($result, $this, '_fromRow');
	}

	/**
	 * Retrieve all categories for a context that have links.
	 * @return DAOResultFactory containing FooterCategory ordered by sequence
	 */
	function getNotEmptyByContextId($contextId, $rangeInfo = null) {
		$result = $this->retrieveRange(
			'SELECT DISTINCT f.footer_category_id, f.*
			FROM  footer_categories f, footerlinks fl
			WHERE f.footer_category_id = fl.footer_category_id AND f.context_id = ?',
			array((int) $contextId)
		);

		return new DAOResultFactory($result, $this, '_fromRow');
	}

	/**
	 * Retrieve the number of categories for a context.
	 * @return int
	 */
	function getCountByContextId($contextId) {
		$result = $this->retrieve(
			'SELECT COUNT(*)
			FROM footer_categories
			WHERE context_id = ?',
			(int) $contextId
		);

		$returner = $result->fields[0];

		$result->Close();
		return $returner;
	}

	/**
	 * Get the ID of the last inserted category.
	 * @return int
	 */
	function getInsertId() {
		return $this->_getInsertId('footer_categories', 'footer_category_id');
	}
}

?>
