<?php

/**
 * @file classes/server/SectionDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SectionDAO
 * @ingroup server
 * @see Section
 *
 * @brief Operations for retrieving and modifying Section objects.
 */

import ('classes.server.Section');
import ('lib.pkp.classes.context.PKPSectionDAO');

class SectionDAO extends PKPSectionDAO {
	var $cache;

	/**
	 * Get the name of the section table in the database
	 *
	 * @return string
	 */
	protected function _getTableName() {
		return 'sections';
	}

	/**
	 * Get the name of the context ID table column
	 *
	 * @return string
	 */
	protected function _getContextIdColumnName() {
		return 'server_id';
	}

	function _cacheMiss($cache, $id) {
		$section = $this->getById($id, null, false);
		$cache->setCache($id, $section);
		return $section;
	}

	function &_getCache() {
		if (!isset($this->cache)) {
			$cacheManager = CacheManager::getManager();
			$this->cache = $cacheManager->getObjectCache('sections', 0, [$this, '_cacheMiss']);
		}
		return $this->cache;
	}

	/**
	 * Retrieve a section by ID.
	 * @param $sectionId int
	 * @param $serverId int Server ID optional
	 * @param $useCache boolean optional
	 * @return Section|null
	 */
	function getById($sectionId, $serverId = null, $useCache = false) {
		if ($useCache) {
			$cache = $this->_getCache();
			$returner = $cache->get($sectionId);
			if ($returner && $serverId != null && $serverId != $returner->getServerId()) $returner = null;
			return $returner;
		}

		$sql = 'SELECT * FROM sections WHERE section_id = ?';
		$params = [(int) $sectionId];
		if ($serverId !== null) {
			$sql .= ' AND server_id = ?';
			$params[] = (int) $serverId;
		}
		$result = $this->retrieve($sql, $params);
		$row = $result->current();
		return $row ? $this->_fromRow((array) $row) : null;
	}

	/**
	 * Retrieve a section by abbreviation.
	 * @param $sectionAbbrev string
	 * @param $serverId int Server ID
	 * @param $locale string optional
	 * @return Section
	 */
	function getByAbbrev($sectionAbbrev, $serverId, $locale = null) {
		$params = ['abbrev', $sectionAbbrev, (int) $serverId];
		if ($locale !== null) $params[] = $locale;

		$result = $this->retrieve(
			'SELECT	s.*
			FROM	sections s, section_settings l
			WHERE	l.section_id = s.section_id AND
				l.setting_name = ? AND
				l.setting_value = ? AND
				s.server_id = ?' .
				($locale!==null?' AND l.locale = ?':''),
			$params
		);
		$row = $result->current();
		return $row ? $this->_fromRow((array) $row) : null;
	}

	/**
	 * Retrieve a section by title.
	 * @param $sectionTitle string
	 * @param $serverId int Server ID
	 * @param $locale string optional
	 * @return Section
	 */
	function getByTitle($sectionTitle, $serverId, $locale = null) {
		$params = ['title', $sectionTitle, (int) $serverId];
		if ($locale !== null) $params[] = $locale;

		$result = $this->retrieve(
			'SELECT	s.*
			FROM	sections s, section_settings l
			WHERE	l.section_id = s.section_id AND
				l.setting_name = ? AND
				l.setting_value = ? AND
				s.server_id = ?' .
				($locale !== null?' AND l.locale = ?':''),
			$params
		);
		$row = $result->current();
		return $row ? $this->_fromRow((array) $row) : null;
	}

	/**
	 * Retrieve section a submission is assigned to.
	 * @param $submissionId int Submission id
	 * @return Section
	 */
	public function getBySubmissionId($submissionId) {
		$result = $this->retrieve(
			'SELECT sections.* FROM sections
			JOIN submissions
			ON (submissions.section_id = sections.section_id)
			WHERE submissions.submission_id = ?',
			[(int) $submissionId]
		);
		$row = $result->current();
		return $row ? $this->_fromRow((array) $row) : null;
	}

	/**
	 * Return a new data object.
	 */
	function newDataObject() {
		return new Section();
	}

	/**
	 * Internal function to return a Section object from a row.
	 * @param $row array
	 * @return Section
	 */
	function _fromRow($row) {
		$section = parent::_fromRow($row);

		$section->setId($row['section_id']);
		$section->setServerId($row['server_id']);
		$section->setMetaIndexed($row['meta_indexed']);
		$section->setMetaReviewed($row['meta_reviewed']);
		$section->setAbstractsNotRequired($row['abstracts_not_required']);
		$section->setHideTitle($row['hide_title']);
		$section->setHideAuthor($row['hide_author']);
		$section->setIsInactive($row['is_inactive']);
		$section->setAbstractWordCount($row['abstract_word_count']);

		$this->getDataObjectSettings('section_settings', 'section_id', $row['section_id'], $section);

		HookRegistry::call('SectionDAO::_fromRow', [&$section, &$row]);

		return $section;
	}

	/**
	 * Get the list of fields for which data can be localized.
	 * @return array
	 */
	function getLocaleFieldNames() {
		return array_merge(
			parent::getLocaleFieldNames(),
			['abbrev', 'identifyType', 'description']
		);
	}

	/**
	 * Get the list of fields for which data can not be localized.
	 * @return array
	 */
	function getAdditionalFieldNames() {
		return array_merge(
			parent::getAdditionalFieldNames(),
			['path']
		);
	}

	/**
	 * Update the localized fields for this table
	 * @param $section object
	 */
	function updateLocaleFields($section) {
		$this->updateDataObjectSettings(
			'section_settings',
			$section,
			['section_id' => $section->getId()]
		);
	}

	/**
	 * Insert a new section.
	 * @param $section Section
	 * @return int new Section ID
	 */
	function insertObject($section) {
		$this->update(
			'INSERT INTO sections
				(server_id, review_form_id, seq, meta_indexed, meta_reviewed, abstracts_not_required, editor_restricted, hide_title, hide_author, is_inactive, abstract_word_count)
				VALUES
				(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
			[
				(int)$section->getServerId(),
				(int)$section->getReviewFormId(),
				(float) $section->getSequence(),
				$section->getMetaIndexed() ? 1 : 0,
				$section->getMetaReviewed() ? 1 : 0,
				$section->getAbstractsNotRequired() ? 1 : 0,
				$section->getEditorRestricted() ? 1 : 0,
				$section->getHideTitle() ? 1 : 0,
				$section->getHideAuthor() ? 1 : 0,
				$section->getIsInactive() ? 1 : 0,
				(int) $section->getAbstractWordCount()
			]
		);

		$section->setId($this->getInsertId());
		$this->updateLocaleFields($section);
		return $section->getId();
	}

	/**
	 * Update an existing section.
	 * @param $section Section
	 */
	function updateObject($section) {
		$this->update(
			'UPDATE sections
				SET
					review_form_id = ?,
					seq = ?,
					meta_indexed = ?,
					meta_reviewed = ?,
					abstracts_not_required = ?,
					editor_restricted = ?,
					hide_title = ?,
					hide_author = ?,
					is_inactive = ?,
					abstract_word_count = ?
				WHERE section_id = ?',
			[
				(int) $section->getReviewFormId(),
				(float) $section->getSequence(),
				(int) $section->getMetaIndexed(),
				(int) $section->getMetaReviewed(),
				(int) $section->getAbstractsNotRequired(),
				(int) $section->getEditorRestricted(),
				(int) $section->getHideTitle(),
				(int) $section->getHideAuthor(),
				(int) $section->getIsInactive(),
				$this->nullOrInt($section->getAbstractWordCount()),
				(int) $section->getId()
			]
		);
		$this->updateLocaleFields($section);
	}

	/**
	 * Delete a section by ID.
	 * @param $sectionId int Section ID
	 * @param $contextId int optional
	 */
	function deleteById($sectionId, $contextId = null) {
		$subEditorsDao = DAORegistry::getDAO('SubEditorsDAO');
		$subEditorsDao->deleteBySubmissionGroupId($sectionId, ASSOC_TYPE_SECTION, $contextId);

		// Remove articles from this section
		$submissionDao = DAORegistry::getDAO('SubmissionDAO'); /* @var $submissionDao SubmissionDAO */
		$submissionDao->removeSubmissionsFromSection($sectionId);

		if (isset($contextId) && !$this->sectionExists($sectionId, $contextId)) return false;
		$this->update('DELETE FROM section_settings WHERE section_id = ?', [(int) $sectionId]);
		$this->update('DELETE FROM sections WHERE section_id = ?', [(int) $sectionId]);
	}

	/**
	 * Delete sections by server ID
	 * NOTE: This does not delete dependent entries EXCEPT from subeditor_submission_group. It is intended
	 * to be called only when deleting a server.
	 * @param $serverId int Server ID
	 */
	function deleteByServerId($serverId) {
		$this->deleteByContextId($serverId);
	}

	/**
	 * Retrieve an array associating all section editor IDs with
	 * arrays containing the sections they edit.
	 * @param $serverId int Server ID
	 * @return array editorId => array(sections they edit)
	 */
	function getEditorSections($serverId) {
		$result = $this->retrieve(
			'SELECT s.*, se.user_id AS editor_id FROM subeditor_submission_group ssg, sections s WHERE ssg.assoc_id = s.section_id AND ssg.assoc_type = ? AND s.server_id = ssg.context_id AND s.server_id = ?',
			[(int) ASSOC_TYPE_SECTION, (int) $serverId]
		);

		$returner = [];
		foreach ($result as $row) {
			$section = $this->_fromRow((array) $row);
			if (!isset($returner[$row->editor_id])) {
				$returner[$row->editor_id] = [$section];
			} else {
				$returner[$row->editor_id][] = $section;
			}
		}
		return $returner;
	}

	/**
	 * Retrieve all sections for a server.
	 * @param $serverId int Server ID
	 * @param $rangeInfo DBResultRange optional
	 * @return DAOResultFactory containing Sections ordered by sequence
	 */
	function getByServerId($serverId, $rangeInfo = null) {
		return $this->getByContextId($serverId, $rangeInfo);
	}

	/**
	 * Retrieve all sections for a server.
	 * @param $serverId int Server ID
	 * @param $rangeInfo DBResultRange optional
	 * @param $submittableOnly boolean optional. Whether to return only sections
	 *  that can be submitted to by anyone.
	 * @return DAOResultFactory containing Sections ordered by sequence
	 */
	 function getByContextId($serverId, $rangeInfo = null, $submittableOnly = false) {
		 return new DAOResultFactory(
			 $this->retrieveRange(
				'SELECT * FROM sections WHERE server_id = ? ' . ($submittableOnly ? ' AND editor_restricted = 0' : '') . ' ORDER BY seq',
				[(int) $serverId],
				$rangeInfo
			),
			$this,
			'_fromRow'
		);
	}

	/**
	 * Retrieve all sections.
	 * @param $rangeInfo DBResultRange optional
	 * @return DAOResultFactory containing Sections ordered by server ID and sequence
	 */
	function getAll($rangeInfo = null) {
		return new DAOResultFactory(
			$this->retrieveRange(
				'SELECT * FROM sections ORDER BY server_id, seq',
				[], $rangeInfo
			),
			$this,
			'_fromRow'
		);
	}

	/**
	 * Retrieve all empty (without articles) section ids for a server.
	 * @param $serverId int Server ID
	 * @return array
	 */
	function getEmptyByServerId($serverId) {
		$result = $this->retrieve(
			'SELECT s.section_id AS section_id FROM sections s LEFT JOIN submissions a ON (a.section_id = s.section_id) WHERE a.section_id IS NULL AND s.server_id = ?',
			[(int) $serverId]
		);

		$returner = [];
		foreach ($result as $row) $returner[] = $row->section_id;
		return $returner;
	}

	/**
	 * Check if a section exists with the specified ID.
	 * @param $sectionId int Section ID
	 * @param $serverId int Server ID
	 * @return boolean
	 */
	function sectionExists($sectionId, $serverId) {
		$result = $this->retrieve(
			'SELECT COUNT(*) AS row_count FROM sections WHERE section_id = ? AND server_id = ?',
			[(int) $sectionId, (int) $serverId]
		);
		$row = $result->current();
		return $row ? $row->row_count == 1 : false;
	}

	/**
	 * Sequentially renumber sections in their sequence order.
	 * @param $serverId int Server ID
	 */
	function resequenceSections($serverId) {
		$result = $this->retrieve(
			'SELECT section_id FROM sections WHERE server_id = ? ORDER BY seq',
			[(int) $serverId]
		);

		$i=0;
		foreach ($result as $row) {
			$this->update(
				'UPDATE sections SET seq = ? WHERE section_id = ?',
				[++$i, $row->section_id]
			);
		}
	}

	/**
	 * Get the ID of the last inserted section.
	 * @return int
	 */
	function getInsertId() {
		return $this->_getInsertId('sections', 'section_id');
	}
}
