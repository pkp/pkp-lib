<?php

/**
 * @file classes/context/CategoryDAO.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CategoryDAO
 * @ingroup context
 * @see Category
 *
 * @brief Operations for retrieving and modifying Category objects.
 */

import ('lib.pkp.classes.context.Category');

class CategoryDAO extends DAO {
	/**
	 * Retrieve an category by ID.
	 * @param $categoryId int
	 * @param $contextId int optional
	 * @param $parentId int optional
	 * @return Category
	 */
	function getById($categoryId, $contextId = null, $parentId = null) {
		$params = array((int) $categoryId);
		if ($contextId) $params[] = (int) $contextId;
		if ($parentId) $params[] = (int) $parentId;

		$result = $this->retrieve(
			'SELECT	*
			FROM	categories
			WHERE	category_id = ?
			' . ($contextId?' AND context_id = ?':'') . '
			' . ($parentId?' AND parent_id = ?':''),
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
	 * @return Category
	 */
	function getByPath($path, $contextId) {
		$returner = null;
		$result = $this->retrieve(
			'SELECT * FROM categories WHERE path = ? AND context_id = ?',
			array((string) $path, (int) $contextId)
		);

		if ($result->RecordCount() != 0) {
			$returner = $this->_fromRow($result->GetRowAssoc(false));
		}

		$result->Close();
		return $returner;
	}

	/**
	 * Retrieve an category by title.
	 * @param $categoryTitle string
	 * @param $contextId int
	 * @param $locale string optional
	 * @return Category
	 */
	function getByTitle($categoryTitle, $contextId, $locale = null) {
		$params = array('title', $categoryTitle, (int) $contextId);
		if ($locale) $params[] = $locale;

		$result = $this->retrieve(
			'SELECT	a.*
			FROM	categories a,
				category_settings l
			WHERE	l.category_id = a.category_id AND
				l.setting_name = ? AND
				l.setting_value = ?
				AND a.context_id = ?
				' . ($locale?' AND locale = ?':'') . '
			ORDER BY seq',
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
	 * Retrieve categories by publication id
	 *
	 * @param int $publicationId
	 * @return DAOResultFactory
	 */
	public function getByPublicationId($publicationId) {
		$result = $this->retrieve(
			'SELECT	c.*
			FROM categories c
			INNER JOIN publication_categories pc ON (pc.category_id = c.category_id)
			WHERE pc.publication_id = ?',
			(int) $publicationId
		);

		return new DAOResultFactory($result, $this, '_fromRow');
	}

	/**
	 * Check if a category exists with a specified path.
	 * @param $path the path for the category
	 * @return boolean
	 */
	function categoryExistsByPath($path) {
		$result = $this->retrieve(
			'SELECT COUNT(*) FROM categories WHERE path = ?', $path
		);
		$returner = isset($result->fields[0]) && $result->fields[0] == 1 ? true : false;

		$result->Close();
		return $returner;
	}

	/**
	 * Construct a new data object corresponding to this DAO.
	 * @return Category
	 */
	function newDataObject() {
		return new Category();
	}

	/**
	 * Internal function to return an Category object from a row.
	 * @param $row array
	 * @return Category
	 */
	function _fromRow($row) {
		$category = $this->newDataObject();

		$category->setId($row['category_id']);
		$category->setContextId($row['context_id']);
		$category->setParentId($row['parent_id']);
		$category->setPath($row['path']);
		$category->setImage(unserialize($row['image']));
		$category->setSequence($row['seq']);

		$this->getDataObjectSettings('category_settings', 'category_id', $row['category_id'], $category);

		HookRegistry::call('CategoryDAO::_fromRow', array(&$category, &$row));

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
	 * Get a list of additional fields.
	 * @return array
	 */
	function getAdditionalFieldNames() {
		return array_merge(
			parent::getAdditionalFieldNames(),
			array(
				'sortOption',
			)
		);
	}

	/**
	 * Update the localized fields for this table
	 * @param $category object
	 */
	function updateLocaleFields($category) {
		$this->updateDataObjectSettings(
			'category_settings', $category,
			array(
				'category_id' => $category->getId()
			)
		);
	}

	/**
	 * Insert a new category.
	 * @param $category Category
	 * @return int ID of the inserted category.
	 */
	function insertObject($category) {
		$this->update(
			'INSERT INTO categories
				(context_id, parent_id, path, image, seq)
				VALUES
				(?, ?, ?, ?, ?)',
			array(
				(int) $category->getContextId(),
				(int) $category->getParentId(),
				$category->getPath(),
				serialize($category->getImage() ? $category->getImage() : array()),
				(int) $category->getSequence()
			)
		);

		$category->setId($this->getInsertId());
		$this->updateLocaleFields($category);
		return $category->getId();
	}

	/**
	 * Update an existing category.
	 * @param $category Category
	 */
	function updateObject($category) {
		$returner = $this->update(
			'UPDATE	categories
			SET	context_id = ?,
				parent_id = ?,
				path = ?,
				image = ?,
				seq = ?
			WHERE	category_id = ?',
			array(
				(int) $category->getContextId(),
				(int) $category->getParentId(),
				$category->getPath(),
				serialize($category->getImage() ? $category->getImage() : array()),
				(int) $category->getSequence(),
				(int) $category->getId()
			)
		);
		$this->updateLocaleFields($category);
		return $returner;
	}

	/**
	 * Sequentially renumber categories in their sequence order by context ID and optionally parent category ID.
	 * @param $contextId int
	 * @param $parentCategoryId int Optional parent category ID
	 */
	function resequenceCategories($contextId, $parentCategoryId = null) {
		$params = array((int) $contextId);
		if ($parentCategoryId) $params[] = (int) $parentCategoryId;
		$result = $this->retrieve(
			'SELECT category_id FROM categories WHERE context_id = ?' .
			($parentCategoryId?' AND parent_id = ?':''),
			$params
		);

		for ($i=1; !$result->EOF; $i++) {
			list($categoryId) = $result->fields;
			$this->update(
				'UPDATE categories SET seq = ? WHERE category_id = ?',
				array(
					(int) $i,
					(int) $categoryId
				)
			);

			$result->MoveNext();
		}

		$result->Close();
	}

	/**
	 * Delete an category.
	 * @param $category Category
	 */
	function deleteObject($category) {
		return $this->deleteById(
			$category->getId(),
			$category->getContextId()
		);
	}

	/**
	 * Delete an category by ID.
	 * @param $categoryId int
	 * @param $contextId int optional
	 */
	function deleteById($categoryId, $contextId = null) {
		$params = array((int) $categoryId);
		if ($contextId) $params[] = (int) $contextId;

		$this->update(
			'DELETE FROM categories
			WHERE	category_id = ?
				' . ($contextId?' AND context_id = ?':''),
			$params
		);

		// If the category was deleted (this validates context_id,
		// if specified), delete any associated settings as well.
		if ($this->getAffectedRows()) {
			$this->update(
				'DELETE FROM category_settings WHERE category_id = ?',
				array((int) $categoryId)
			);

			// remove any monograph assignments for this category.
			$this->update(
				'DELETE FROM publication_categories WHERE category_id = ?',
				array((int) $categoryId)
			);
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
	 * Assign a publication to a category
	 *
	 * @param int $categoryId
	 * @param int $publicationId
	 */
	public function insertPublicationAssignment($categoryId, $publicationId) {
		$this->update(
			'INSERT INTO publication_categories (category_id, publication_id)
			VALUES (?, ?)',
			array((int) $categoryId, (int) $publicationId)
		);
	}

	/**
	 * Delete the assignment of a category to a publication
	 *
	 * @param int $publicationId
	 */
	public function deletePublicationAssignments($publicationId) {
		$this->update(
			'DELETE FROM publication_categories WHERE publication_id = ?',
			array((int) $publicationId)
		);
	}

	/**
	 * Retrieve all categories for a context.
	 * @param $contextId int Conext ID.
	 * @param $rangeInfo Object Optional range information.
	 * @return DAOResultFactory containing Category ordered by sequence
	 */
	function getByContextId($contextId, $rangeInfo = null) {
		// The strange ORDER BY clause is to return subcategories
		// immediately after their parent category's entry.
		$result = $this->retrieveRange(
			'SELECT	c.*
			FROM	categories c
				LEFT JOIN categories pc ON (pc.category_id = c.parent_id)
			WHERE	c.context_id = ?
			ORDER BY (COALESCE((pc.seq * 8192) + pc.category_id, 0) * 8192) + CASE WHEN pc.category_id IS NULL THEN 8192 * ((c.seq * 8192) + c.category_id) ELSE c.seq END',
			array((int) $contextId)
		);

		return new DAOResultFactory($result, $this, '_fromRow');
	}

	/**
	 * Retrieve the number of categories for a context.
	 * @param $contextId int Context ID.
	 * @return DAOResultFactory containing Category ordered by sequence
	 */
	function getCountByContextId($contextId) {
		$result = $this->retrieve(
			'SELECT	COUNT(*)
			FROM	categories
			WHERE	context_id = ?',
			(int) $contextId
		);

		$returner = $result->fields[0];
		$result->Close();
		return $returner;
	}

	/**
	 * Retrieve all categories for a parent category.
	 * @param $parentId int Parent category ID.
	 * @param $contextId int Context ID (optional).
	 * @param $rangeInfo Object Range info (optional).
	 * @return DAOResultFactory containing Category ordered by sequence
	 */
	function getByParentId($parentId, $contextId = null, $rangeInfo = null) {
		$params = array((int) $parentId);
		if ($contextId) $params[] = (int) $contextId;

		$result = $this->retrieveRange(
			'SELECT	*
			FROM	categories
			WHERE	parent_id = ?
			' . ($contextId?' AND context_id = ?':'') . '
			ORDER BY seq',
			$params
		);
		return new DAOResultFactory($result, $this, '_fromRow');
	}

	/**
	 * Get the ID of the last inserted category.
	 * @return int
	 */
	function getInsertId() {
		return $this->_getInsertId('categories', 'category_id');
	}
}


